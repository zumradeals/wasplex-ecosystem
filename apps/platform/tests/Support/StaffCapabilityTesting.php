<?php

namespace Tests\Support;

use App\Models\User;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Enums\LinkOrigin;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Services\RegistersUserIdentity;
use Illuminate\Support\Str;

/**
 * Fabrique de test partagée pour tout écran du portail personnel Wasplex
 * (P0-Admin) gouverné par une capacité (ADR-0004) : constituer un sujet
 * réel et lui octroyer une capacité via le vrai cycle `GrantManager`
 * (proposition → activation), jamais une écriture directe dans
 * `governance.grants`. Extrait de `Tests\Feature\Modules\Advertising\
 * AdvertisingTestCase` (seul module à en avoir eu besoin jusqu'ici) au
 * moment où un deuxième module (Governance/Configuration) en a eu besoin
 * à son tour — même discipline de frontières que
 * `App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility`.
 */
trait StaffCapabilityTesting
{
    /**
     * Depuis P007, `LinkOrigin::Registration` (le défaut) émet
     * automatiquement les grants `user.base` — `LinkOrigin::Migration`
     * contourne délibérément l'octroi automatique (`RegistersUserIdentity`)
     * pour les tests qui doivent encore constituer un sujet réellement sans
     * aucun grant.
     */
    protected function makeUser(string $email, LinkOrigin $origin = LinkOrigin::Registration): User
    {
        return app(RegistersUserIdentity::class)->register([
            'name' => 'Utilisateur '.$email,
            'email' => $email,
            'password' => 'password',
        ], $origin);
    }

    protected function activeLinkFor(User $user): PersonAccountLink
    {
        return PersonAccountLink::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    protected function makeRepresentative(): PersonAccountLink
    {
        return $this->activeLinkFor($this->makeUser('representant-'.Str::uuid().'@example.com'));
    }

    /**
     * Octroie une capacité personnel Wasplex (P0-Admin) via le vrai cycle
     * `GrantManager` (proposition → activation), jamais une écriture
     * directe dans `governance.grants`. Portée `resource_type` seule
     * (aucun `resource_ids`) : une habilitation personnel couvre toute
     * ressource de ce type, jamais une liste figée.
     */
    protected function grantStaffCapability(PersonAccountLink $subject, string $capabilityKey, string $resourceType): Grant
    {
        $capability = CapabilityDefinition::query()
            ->where('stable_key', $capabilityKey)
            ->where('state', 'active')
            ->firstOrFail();

        $policy = PolicyVersion::create([
            'stable_key' => 'test_policy_'.$capabilityKey.'_'.Str::uuid(),
            'version' => 1,
            'state' => 'active',
            'checksum' => hash('sha256', $capabilityKey.random_int(1, PHP_INT_MAX)),
            'effective_from' => now(),
        ]);

        $author = $this->activeLinkFor($this->makeUser('grant-author-'.Str::uuid().'@example.com'));
        $approver = $this->activeLinkFor($this->makeUser('grant-approver-'.Str::uuid().'@example.com'));

        $manager = app(GrantManager::class);
        $correlationId = (string) Str::uuid();

        $grant = $manager->propose(
            subject: $subject,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['resource_type' => $resourceType]),
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

        return $manager->activate($grant, $author, $approver, $correlationId);
    }
}
