<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Projections\AccountBalanceProjection;
use App\Modules\Wallet\Ledger\Services\Exceptions\DirectReversalRefusedException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ADR-0003 §11 : la seule voie de correction d'une transaction déjà
 * comptabilisée est une contre-écriture explicite, liée à l'originale par
 * référence. L'original, la correction et le résultat restent tous visibles
 * et auditables.
 */
class ReversalTest extends LedgerTestCase
{
    use RefreshDatabase;

    public function test_a_correction_uses_an_explicit_reversal_and_never_modifies_the_original(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('reversal_basic');
        $original = $this->poster()->post($this->debitCreditIntent($debitAccount, $creditAccount, 1_000));

        $reversalIntent = $this->debitCreditIntent($creditAccount, $debitAccount, 1_000, idempotencyKey: 'reversal-of-'.$original->id);
        $reversal = $this->poster()->reverse($original, $reversalIntent, 'Montant saisi par erreur');

        // L'original reste intact et visible.
        $original->refresh();
        $this->assertNull($original->reverses_transaction_id);
        $this->assertNull($original->reversal_reason);
        $this->assertCount(2, $original->postings);

        // La contre-écriture référence explicitement l'original et porte un motif.
        $this->assertSame($original->id, $reversal->reverses_transaction_id);
        $this->assertSame('Montant saisi par erreur', $reversal->reversal_reason);
        $this->assertNotSame($original->id, $reversal->id);

        // Les deux transactions, et leurs quatre postings, restent
        // consultables : rien n'est jamais masqué.
        $this->assertDatabaseCount('ledger.ledger_transactions', 2);
        $this->assertDatabaseCount('ledger.postings', 4);

        // L'effet net des deux mouvements combinés est nul : la contre-écriture
        // annule bien l'original plutôt que de le dupliquer.
        $projection = app(AccountBalanceProjection::class);
        $this->assertSame(0, $projection->currentBalance($debitAccount->fresh()));
        $this->assertSame(0, $projection->currentBalance($creditAccount->fresh()));
    }

    public function test_post_refuses_a_transaction_intent_that_already_declares_a_reversal(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('reversal_direct_refused');
        $original = $this->poster()->post($this->debitCreditIntent($debitAccount, $creditAccount, 1_000));

        $intent = $this->debitCreditIntent($creditAccount, $debitAccount, 1_000)->withReversalOf($original->id, 'Tentative directe');

        $this->expectException(DirectReversalRefusedException::class);

        $this->poster()->post($intent);
    }

    public function test_reverse_requires_a_non_empty_reason(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('reversal_empty_reason');
        $original = $this->poster()->post($this->debitCreditIntent($debitAccount, $creditAccount, 1_000));

        $reversalIntent = $this->debitCreditIntent($creditAccount, $debitAccount, 1_000);

        $this->expectException(\InvalidArgumentException::class);

        $this->poster()->reverse($original, $reversalIntent, '   ');
    }
}
