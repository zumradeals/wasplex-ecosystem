<?php

namespace App\Modules\Wallet\Ledger\Services;

use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Models\LedgerTransaction;
use App\Modules\Wallet\Ledger\Models\Posting;
use App\Modules\Wallet\Ledger\Services\Exceptions\CurrencyMismatchException;
use App\Modules\Wallet\Ledger\Services\Exceptions\DirectReversalRefusedException;
use App\Modules\Wallet\Ledger\Services\Exceptions\IdempotencyConflictException;
use App\Modules\Wallet\Ledger\Services\Exceptions\InsufficientPostingsException;
use App\Modules\Wallet\Ledger\Services\Exceptions\NonPositiveAmountException;
use App\Modules\Wallet\Ledger\Services\Exceptions\UnbalancedTransactionException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Point d'entrée unique de comptabilisation du Wallet (ADR-0003 §7, §14) :
 * aucun module ne poste jamais directement dans `ledger.accounts`,
 * `ledger.ledger_transactions` ou `ledger.postings`. Les modèles de ce
 * module restent utilisables pour la lecture (projections, requêtes), mais
 * seule cette classe crée des lignes de transaction et de posting.
 *
 * Vérifie l'équilibre, la cohérence de devise, la positivité des montants et
 * l'idempotence avant toute écriture, puis comptabilise dans une transaction
 * PostgreSQL atomique. Les mêmes garanties sont reprises en base par les
 * déclencheurs de la migration `add_ledger_immutability_and_integrity_triggers`
 * (défense en profondeur, sur le modèle de Governance/Authorization).
 */
class LedgerPoster
{
    /**
     * Comptabilise une transaction ordinaire. Refuse toute intention déjà
     * marquée comme contre-écriture : voir {@see reverse()}.
     *
     * @throws DirectReversalRefusedException
     * @throws InsufficientPostingsException
     * @throws NonPositiveAmountException
     * @throws CurrencyMismatchException
     * @throws UnbalancedTransactionException
     * @throws IdempotencyConflictException
     */
    public function post(TransactionIntent $intent): LedgerTransaction
    {
        if ($intent->reversesTransactionId !== null || $intent->reversalReason !== null) {
            throw new DirectReversalRefusedException(
                'post() ne comptabilise jamais de contre-écriture ; utilisez reverse() pour toute correction (ADR-0003 §11)'
            );
        }

        return $this->postInternal($intent);
    }

    /**
     * Seule voie de correction d'une transaction déjà comptabilisée
     * (ADR-0003 §11) : une contre-écriture explicite, liée à l'originale par
     * référence, jamais une modification de celle-ci. L'original reste
     * inchangé et visible ; la contre-écriture et l'original sont tous deux
     * consultables après coup.
     *
     * @throws \InvalidArgumentException Motif manquant.
     * @throws InsufficientPostingsException
     * @throws NonPositiveAmountException
     * @throws CurrencyMismatchException
     * @throws UnbalancedTransactionException
     * @throws IdempotencyConflictException
     */
    public function reverse(LedgerTransaction $original, TransactionIntent $reversal, string $reason): LedgerTransaction
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('un motif est requis pour toute contre-écriture (ADR-0003 §11)');
        }

        return $this->postInternal($reversal->withReversalOf($original->id, $reason));
    }

    private function postInternal(TransactionIntent $intent): LedgerTransaction
    {
        $this->assertStructurallyValid($intent);

        $fingerprint = $this->fingerprint($intent);

        $existing = $this->findByIdempotency($intent->idempotencyScope, $intent->idempotencyKey);
        if ($existing !== null) {
            return $this->resolveReplay($existing, $fingerprint);
        }

        try {
            return DB::transaction(function () use ($intent, $fingerprint): LedgerTransaction {
                $transaction = LedgerTransaction::create([
                    'type' => $intent->type,
                    'business_date' => $intent->businessDate,
                    'accounting_date' => $intent->accountingDate,
                    'source_module' => $intent->sourceModule,
                    'source_reference' => $intent->sourceReference,
                    'configuration_key' => $intent->configurationKey,
                    'configuration_version' => $intent->configurationVersion,
                    'idempotency_scope' => $intent->idempotencyScope,
                    'idempotency_key' => $intent->idempotencyKey,
                    'idempotency_fingerprint' => $fingerprint,
                    'correlation_id' => $intent->correlationId,
                    'authored_by' => $intent->authoredBy,
                    'evidence_reference' => $intent->evidenceReference,
                    'reverses_transaction_id' => $intent->reversesTransactionId,
                    'reversal_reason' => $intent->reversalReason,
                ]);

                foreach ($intent->postings as $line) {
                    Posting::create([
                        'ledger_transaction_id' => $transaction->id,
                        'account_id' => $line->accountId,
                        'direction' => $line->direction,
                        'amount' => $line->amount,
                        'currency' => $line->currency,
                        'dimensions' => $line->dimensions,
                        'label' => $line->label,
                    ]);
                }

                return $transaction->fresh();
            });
        } catch (QueryException $exception) {
            if (! $this->isIdempotencyUniqueViolation($exception)) {
                throw $exception;
            }

            // Une transaction concurrente a comptabilisé la même clé
            // d'idempotence entre notre lecture et notre écriture : ce
            // n'est pas une anomalie, c'est la garantie d'unicité
            // (ADR-0003 §10) qui vient de jouer son rôle. On récupère son
            // résultat plutôt que de propager l'erreur SQL.
            $raceWinner = $this->findByIdempotency($intent->idempotencyScope, $intent->idempotencyKey);

            if ($raceWinner === null) {
                throw $exception;
            }

            return $this->resolveReplay($raceWinner, $fingerprint);
        }
    }

    private function assertStructurallyValid(TransactionIntent $intent): void
    {
        if (count($intent->postings) < 2) {
            throw new InsufficientPostingsException(
                'une transaction comptable exige au moins deux postings (architecture/05)'
            );
        }

        $currencies = [];
        foreach ($intent->postings as $line) {
            if ($line->amount <= 0) {
                throw new NonPositiveAmountException(
                    "le posting sur le compte {$line->accountId} porte un montant non strictement positif ({$line->amount})"
                );
            }

            $currencies[$line->currency] = true;
        }

        if (count($currencies) > 1) {
            throw new CurrencyMismatchException(
                'une transaction ne peut mélanger deux devises entre ses postings (ADR-0003 §5) : '.implode(', ', array_keys($currencies))
            );
        }

        $currency = array_key_first($currencies);
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($intent->postings as $line) {
            if ($line->direction === PostingDirection::Debit) {
                $totalDebit += $line->amount;
            } else {
                $totalCredit += $line->amount;
            }
        }

        if ($totalDebit !== $totalCredit) {
            throw new UnbalancedTransactionException(
                "la somme des débits ({$totalDebit}) doit égaler la somme des crédits ({$totalCredit}) pour {$currency} (ADR-0003 §1, §17)"
            );
        }
    }

    /**
     * Empreinte du contenu économique de l'intention (ADR-0003 §10) :
     * exclut délibérément `correlationId` et `authoredBy`, qui identifient
     * l'appel technique et peuvent légitimement varier d'une tentative à
     * l'autre sans que l'intention comptable rejouée ne change de sens. Les
     * postings sont triés pour que deux intentions équivalentes composées
     * dans un ordre différent produisent la même empreinte.
     */
    private function fingerprint(TransactionIntent $intent): string
    {
        $postings = array_map(
            fn (PostingLine $line): array => $line->toCanonicalArray(),
            $intent->postings,
        );

        usort($postings, function (array $a, array $b): int {
            return [$a['account_id'], $a['direction'], $a['amount'], $a['currency'], $a['label']]
                <=> [$b['account_id'], $b['direction'], $b['amount'], $b['currency'], $b['label']];
        });

        $canonical = [
            'type' => $intent->type,
            'business_date' => $intent->businessDate->toDateString(),
            'accounting_date' => $intent->accountingDate->toDateString(),
            'source_module' => $intent->sourceModule,
            'source_reference' => $intent->sourceReference,
            'configuration_key' => $intent->configurationKey,
            'configuration_version' => $intent->configurationVersion,
            'evidence_reference' => $intent->evidenceReference,
            'reverses_transaction_id' => $intent->reversesTransactionId,
            'reversal_reason' => $intent->reversalReason,
            'postings' => $postings,
        ];

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * `protected` uniquement pour permettre à la suite de tests de simuler
     * de façon déterministe la perte d'une course à l'idempotence (le
     * SELECT préalable ne voit pas encore une ligne concurrente qui vient
     * de se valider) sans dépendre d'une vraie concurrence multi-connexion
     * dans ce cas précis — voir `IdempotentReplayTest`.
     */
    protected function findByIdempotency(string $scope, string $key): ?LedgerTransaction
    {
        return LedgerTransaction::query()
            ->where('idempotency_scope', $scope)
            ->where('idempotency_key', $key)
            ->first();
    }

    private function resolveReplay(LedgerTransaction $existing, string $fingerprint): LedgerTransaction
    {
        if (! hash_equals($existing->idempotency_fingerprint, $fingerprint)) {
            throw new IdempotencyConflictException(
                "la clé d'idempotence {$existing->idempotency_scope}/{$existing->idempotency_key} est déjà associée à un contenu différent (ADR-0003 §10)"
            );
        }

        return $existing;
    }

    private function isIdempotencyUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return $sqlState === '23505'
            && str_contains($exception->getMessage(), 'ledger_transactions_idempotency_unique');
    }
}
