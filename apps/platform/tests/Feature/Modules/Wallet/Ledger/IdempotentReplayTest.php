<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Models\LedgerTransaction;
use App\Modules\Wallet\Ledger\Services\Exceptions\IdempotencyConflictException;
use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ADR-0003 §10 : la même intention avec la même empreinte retourne le
 * résultat existant sans second effet comptable ; la même clé avec un
 * contenu différent est rejetée.
 */
class IdempotentReplayTest extends LedgerTestCase
{
    use RefreshDatabase;

    public function test_replaying_the_same_idempotency_key_with_identical_content_returns_the_existing_transaction(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('replay_same');
        $key = 'campaign-event-replay-same';

        $first = $this->poster()->post(
            $this->debitCreditIntent($debitAccount, $creditAccount, 1_000, idempotencyKey: $key)
        );

        $second = $this->poster()->post(
            $this->debitCreditIntent($debitAccount, $creditAccount, 1_000, idempotencyKey: $key)
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('ledger.ledger_transactions', 1);
        $this->assertDatabaseCount('ledger.postings', 2);
    }

    /**
     * L'équivalence de contenu ne dépend pas de l'ordre de composition des
     * postings : l'empreinte est calculée sur une représentation triée.
     */
    public function test_replaying_with_postings_listed_in_a_different_order_still_counts_as_identical_content(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('replay_reordered');
        $key = 'campaign-event-replay-reordered';

        $first = $this->poster()->post($this->makeIntent(
            postings: [
                new PostingLine($debitAccount->id, PostingDirection::Debit, 1_000, 'XOF', 'Débit'),
                new PostingLine($creditAccount->id, PostingDirection::Credit, 1_000, 'XOF', 'Crédit'),
            ],
            idempotencyKey: $key,
        ));

        $second = $this->poster()->post($this->makeIntent(
            postings: [
                new PostingLine($creditAccount->id, PostingDirection::Credit, 1_000, 'XOF', 'Crédit'),
                new PostingLine($debitAccount->id, PostingDirection::Debit, 1_000, 'XOF', 'Débit'),
            ],
            idempotencyKey: $key,
        ));

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('ledger.ledger_transactions', 1);
    }

    public function test_the_same_idempotency_key_with_different_content_is_rejected(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('replay_conflict');
        $key = 'campaign-event-replay-conflict';

        $this->poster()->post(
            $this->debitCreditIntent($debitAccount, $creditAccount, 1_000, idempotencyKey: $key)
        );

        $this->expectException(IdempotencyConflictException::class);

        $this->poster()->post(
            $this->debitCreditIntent($debitAccount, $creditAccount, 2_000, idempotencyKey: $key)
        );
    }

    public function test_a_different_idempotency_scope_with_the_same_key_is_independent(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('replay_scope');
        $key = 'shared-key';

        $first = $this->poster()->post(
            $this->debitCreditIntent($debitAccount, $creditAccount, 1_000, idempotencyScope: 'scope_a', idempotencyKey: $key)
        );

        $second = $this->poster()->post(
            $this->debitCreditIntent($debitAccount, $creditAccount, 1_000, idempotencyScope: 'scope_b', idempotencyKey: $key)
        );

        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('ledger.ledger_transactions', 2);
    }

    /**
     * Simule, de façon déterministe, la branche de secours de
     * {@see LedgerPoster::postInternal()} : une transaction concurrente a
     * déjà comptabilisé la même clé entre notre lecture et notre écriture.
     * Le SELECT préalable est forcé à ne rien voir une seule fois (comme il
     * le ferait réellement si l'autre session validait juste après cette
     * lecture) ; le service doit alors intercepter la violation d'unicité
     * PostgreSQL sur l'INSERT et renvoyer la transaction existante au lieu
     * de laisser filtrer l'erreur SQL. La garantie que Postgres refuse
     * réellement un double INSERT concurrent, elle, est démontrée
     * séparément par {@see ConcurrencyTest} avec deux connexions distinctes.
     */
    public function test_the_service_recovers_transparently_when_it_loses_the_idempotency_race(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('lost_race');
        $key = 'lost-race-key';

        $winner = $this->poster()->post(
            $this->debitCreditIntent($debitAccount, $creditAccount, 1_000, idempotencyKey: $key)
        );

        $poster = new class extends LedgerPoster
        {
            public bool $forceMiss = false;

            protected function findByIdempotency(string $scope, string $key): ?LedgerTransaction
            {
                if ($this->forceMiss) {
                    $this->forceMiss = false;

                    return null;
                }

                return parent::findByIdempotency($scope, $key);
            }
        };
        $poster->forceMiss = true;

        $result = $poster->post(
            $this->debitCreditIntent($debitAccount, $creditAccount, 1_000, idempotencyKey: $key)
        );

        $this->assertSame($winner->id, $result->id);
        $this->assertDatabaseCount('ledger.ledger_transactions', 1);
        $this->assertDatabaseCount('ledger.postings', 2);
    }
}
