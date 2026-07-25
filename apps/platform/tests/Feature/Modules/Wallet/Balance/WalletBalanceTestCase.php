<?php

namespace Tests\Feature\Modules\Wallet\Balance;

use App\Models\User;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Services\RegistersUserIdentity;
use App\Modules\Wallet\Balance\Services\PersonLedgerAccounts;
use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Enums\AccountStatus;
use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Models\Account;
use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use App\Modules\Wallet\Ledger\Services\TransactionIntent;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class WalletBalanceTestCase extends TestCase
{
    protected function makeUser(string $email): User
    {
        return app(RegistersUserIdentity::class)->register([
            'name' => 'Utilisateur '.$email,
            'email' => $email,
            'password' => 'password',
        ]);
    }

    protected function activeLinkFor(User $user): PersonAccountLink
    {
        return PersonAccountLink::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function realCapability(): CapabilityDefinition
    {
        return CapabilityDefinition::query()
            ->where('stable_key', 'wallet.view')
            ->where('state', 'active')
            ->firstOrFail();
    }

    protected function grantWalletView(PersonAccountLink $subject): Grant
    {
        $policy = PolicyVersion::create([
            'stable_key' => 'test_policy_wallet_view',
            'version' => 1,
            'state' => 'active',
            'checksum' => hash('sha256', 'wallet.view'.random_int(1, PHP_INT_MAX)),
            'effective_from' => now(),
        ]);

        $author = $this->activeLinkFor($this->makeUser('grant-author-'.Str::uuid().'@example.com'));

        $manager = app(GrantManager::class);
        $correlationId = (string) Str::uuid();

        $grant = $manager->propose(
            subject: $subject,
            capability: $this->realCapability(),
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $author,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: $correlationId,
        );

        return $manager->activate($grant, $author, null, $correlationId);
    }

    /**
     * Crédite le compte `user_rights` disponible d'une personne, en dehors
     * de tout cycle Publicité : la contrepartie est un simple compte
     * d'actif jetable, propre à ce test, jamais partagé avec un plan de
     * comptes métier réel (même esprit que `LedgerTestCase::makeAccountPair()`).
     */
    protected function creditAvailable(string $personId, int $amount, string $currency = 'XOF'): void
    {
        $counterparty = Account::create([
            'code' => 'coverage.wallet_balance_test.'.str_replace('-', '', (string) Str::uuid()),
            'nature' => AccountNature::Asset,
            'purpose' => AccountPurpose::Coverage,
            'legal_entity' => 'wasplex',
            'country_code' => 'CI',
            'currency' => $currency,
            'module' => 'wallet',
            'compartment' => null,
            'status' => AccountStatus::Active,
            'movement_restrictions' => [],
        ]);

        $available = app(PersonLedgerAccounts::class)->available($personId, $currency);

        app(LedgerPoster::class)->post(new TransactionIntent(
            type: 'wallet_balance_test_credit',
            businessDate: now(),
            accountingDate: now(),
            sourceModule: 'wallet_balance_test',
            sourceReference: 'credit-'.Str::uuid(),
            idempotencyScope: 'wallet_balance_test.credit',
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
            authoredBy: 'wallet.balance.test_suite',
            postings: [
                new PostingLine($counterparty->id, PostingDirection::Debit, $amount, $currency, 'Crédit de test'),
                new PostingLine($available->id, PostingDirection::Credit, $amount, $currency, 'Crédit de test'),
            ],
        ));
    }
}
