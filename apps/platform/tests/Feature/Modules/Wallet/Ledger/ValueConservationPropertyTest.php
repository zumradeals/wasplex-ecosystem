<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Models\Account;
use App\Modules\Wallet\Ledger\Models\LedgerTransaction;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use App\Modules\Wallet\Ledger\Services\TransactionIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ADR-0003 §17 : « quelle que soit la séquence valide d'opérations, le
 * ledger reste équilibré ». Aucune bibliothèque de test fondé sur les
 * propriétés (type Eris/QuickCheck) n'est installée dans ce dépôt, et ce
 * noyau ne justifie pas d'en ajouter une pour ce seul test (P004-A §5 :
 * aucune nouvelle dépendance sans nécessité démontrée). Cette classe
 * reproduit donc l'esprit d'un test fondé sur les propriétés par une série
 * de cas représentatifs générés pseudo-aléatoirement mais de façon
 * déterministe (graine fixe) : plusieurs séquences variées de transactions
 * valides (nombre de postings, comptes, montants, devises, présence ou non
 * d'une contre-écriture), en vérifiant après chaque étape que la valeur
 * totale du ledger reste nulle par devise.
 */
class ValueConservationPropertyTest extends LedgerTestCase
{
    use RefreshDatabase;

    private const SEED = 424242;

    private const SEQUENCES = 20;

    private const MAX_STEPS_PER_SEQUENCE = 8;

    public function test_value_is_conserved_across_many_representative_valid_sequences(): void
    {
        mt_srand(self::SEED);

        $accountsByCurrency = [
            'XOF' => $this->makeAccountPool('XOF'),
            'EUR' => $this->makeAccountPool('EUR'),
        ];

        for ($sequence = 0; $sequence < self::SEQUENCES; $sequence++) {
            $currency = mt_rand(0, 1) === 0 ? 'XOF' : 'EUR';
            $accounts = $accountsByCurrency[$currency];
            $steps = mt_rand(2, self::MAX_STEPS_PER_SEQUENCE);

            /** @var list<LedgerTransaction> $postedInThisSequence */
            $postedInThisSequence = [];

            for ($step = 0; $step < $steps; $step++) {
                // Une chance sur quatre, si une transaction précédente
                // existe dans cette séquence, de la corriger par
                // contre-écriture plutôt que de comptabiliser un nouveau
                // mouvement indépendant.
                if ($postedInThisSequence !== [] && mt_rand(1, 4) === 1) {
                    $original = $postedInThisSequence[array_rand($postedInThisSequence)];
                    $reversal = $this->reversalOf($original);
                    $posted = $this->poster()->reverse($original, $reversal, "Correction représentative #{$sequence}.{$step}");
                } else {
                    $intent = $this->randomBalancedIntent($accounts, $currency);
                    $posted = $this->poster()->post($intent);
                }

                $postedInThisSequence[] = $posted;

                $this->assertGlobalConservation($currency, "séquence {$sequence}, étape {$step}");
            }
        }
    }

    /**
     * @return list<Account>
     */
    private function makeAccountPool(string $currency): array
    {
        $suffix = strtolower($currency);

        return [
            $this->makeAccount("coverage.property_{$suffix}", AccountNature::Asset, AccountPurpose::Coverage, $currency),
            $this->makeAccount("user_rights.property_{$suffix}", AccountNature::Liability, AccountPurpose::UserRights, $currency),
            $this->makeAccount("wasplex_own_resources.property_{$suffix}", AccountNature::Revenue, AccountPurpose::WasplexOwnResources, $currency),
            $this->makeAccount("tax_and_fees.property_{$suffix}", AccountNature::Expense, AccountPurpose::TaxAndFees, $currency),
        ];
    }

    /**
     * @param  list<Account>  $accounts
     */
    private function randomBalancedIntent(array $accounts, string $currency): TransactionIntent
    {
        $total = mt_rand(1, 50) * 100;
        $debitSplits = $this->randomPartition($total, mt_rand(1, 2));
        $creditSplits = $this->randomPartition($total, mt_rand(1, 2));

        $postings = [];

        foreach ($debitSplits as $amount) {
            $postings[] = new PostingLine(
                $accounts[array_rand($accounts)]->id,
                PostingDirection::Debit,
                $amount,
                $currency,
                'Mouvement représentatif (débit)',
            );
        }

        foreach ($creditSplits as $amount) {
            $postings[] = new PostingLine(
                $accounts[array_rand($accounts)]->id,
                PostingDirection::Credit,
                $amount,
                $currency,
                'Mouvement représentatif (crédit)',
            );
        }

        return $this->makeIntent(postings: $postings, idempotencyKey: (string) Str::uuid());
    }

    private function reversalOf(LedgerTransaction $original): TransactionIntent
    {
        $postings = $original->postings->map(function ($posting): PostingLine {
            $opposite = $posting->direction === PostingDirection::Debit ? PostingDirection::Credit : PostingDirection::Debit;

            return new PostingLine($posting->account_id, $opposite, $posting->amount, $posting->currency, 'Contre-écriture représentative');
        })->all();

        return $this->makeIntent(postings: $postings, idempotencyKey: 'reversal-of-'.$original->id.'-'.Str::uuid());
    }

    /**
     * @return list<int>
     */
    private function randomPartition(int $total, int $parts): array
    {
        if ($parts === 1) {
            return [$total];
        }

        $cut = mt_rand(1, $total - 1);

        return [$cut, $total - $cut];
    }

    private function assertGlobalConservation(string $currency, string $context): void
    {
        $totals = DB::table('ledger.postings')
            ->where('currency', $currency)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END), 0)::bigint as total_debit")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END), 0)::bigint as total_credit")
            ->first();

        $this->assertSame(
            (int) $totals->total_debit,
            (int) $totals->total_credit,
            "conservation de la valeur violée pour {$currency} après {$context}."
        );
    }
}
