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
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Services\RegistersUserIdentity;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class AdvertisingTestCase extends TestCase
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
}
