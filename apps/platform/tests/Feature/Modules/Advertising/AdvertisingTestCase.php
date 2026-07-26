<?php

namespace Tests\Feature\Modules\Advertising;

use App\Models\User;
use App\Modules\Advertising\Enums\ConfigurationState;
use App\Modules\Advertising\Enums\ReviewLevel;
use App\Modules\Advertising\Enums\SectorClass;
use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Models\AudienceSegmentSizeThreshold;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\SectorClassification;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Advertising\Services\CampaignService;
use App\Modules\Advertising\Services\CampaignVersionService;
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
use Tests\TestCase;

abstract class AdvertisingTestCase extends TestCase
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

    protected function makeBeneficiary(): PersonAccountLink
    {
        return $this->activeLinkFor($this->makeUser('beneficiaire-'.Str::uuid().'@example.com'));
    }

    protected function makeAdvertiserProfile(?PersonAccountLink $representative = null): AdvertiserProfile
    {
        return AdvertiserProfile::create([
            'legal_name' => 'Annonceur de test SARL',
            'legal_identifier' => 'RCCM-'.Str::random(8),
            'country_code' => 'CI',
            'representative_person_account_link_id' => ($representative ?? $this->makeRepresentative())->id,
            'licenses' => [],
            'territories' => ['CI'],
            'status' => 'active',
        ]);
    }

    protected function makeSectorClassification(
        string $sector = 'retail',
        ReviewLevel $reviewLevel = ReviewLevel::Standard,
        SectorClass $sectorClass = SectorClass::StandardAuthorization,
        int $minimumApprovals = 1,
    ): SectorClassification {
        return SectorClassification::create([
            'country_code' => 'CI',
            'sector' => $sector,
            'version' => 1,
            'sector_class' => $sectorClass,
            'minimum_age' => null,
            'required_evidence' => [],
            'warnings' => [],
            'allowed_formats' => ['banner'],
            'allowed_targeting' => ['country', 'age_range'],
            'frequency_rules' => [],
            'review_level' => $reviewLevel,
            'minimum_approvals' => $minimumApprovals,
            'state' => ConfigurationState::Active,
        ]);
    }

    protected function makeCampaign(?AdvertiserProfile $advertiser = null, string $currency = 'XOF'): Campaign
    {
        return app(CampaignService::class)->createCampaign(
            $advertiser ?? $this->makeAdvertiserProfile(),
            'campaign-'.Str::uuid(),
            $currency,
        );
    }

    protected function proposeAndApproveVersion(
        Campaign $campaign,
        ?SectorClassification $sector = null,
        ?PersonAccountLink $author = null,
        ?PersonAccountLink $approver = null,
    ): CampaignVersion {
        $service = app(CampaignVersionService::class);

        $version = $service->propose(
            campaign: $campaign,
            sector: $sector ?? $this->makeSectorClassification(),
            creations: ['headline' => 'Titre de test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://annonceur.example.com'],
            territory: ['CI'],
            author: $author ?? $this->makeRepresentative(),
        );

        return $service->approve($version, $approver);
    }

    protected function makeActiveSizeThreshold(int $minimumSize = 500): AudienceSegmentSizeThreshold
    {
        return AudienceSegmentSizeThreshold::create([
            'version' => 1,
            'minimum_size' => $minimumSize,
            'state' => ConfigurationState::Active,
        ]);
    }

    protected function budgetService(): CampaignBudgetService
    {
        return app(CampaignBudgetService::class);
    }

    protected function fundCampaign(Campaign $campaign, int $amount): void
    {
        $this->budgetService()->fund($campaign, $amount, 'funding-'.Str::uuid(), (string) Str::uuid());
    }

    /**
     * Octroie une capacité personnel Wasplex (P0-Admin) via le vrai cycle
     * `GrantManager` (proposition → activation), jamais une écriture
     * directe dans `governance.grants` — même discipline que le reste de
     * cette fabrique de test. Portée `resource_type` seule (aucun
     * `resource_ids`) : une habilitation personnel couvre toute ressource
     * de ce type, jamais une liste figée.
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
