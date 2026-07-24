<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\ReviewLevel;
use App\Modules\Advertising\Services\CampaignVersionService;
use App\Modules\Advertising\Services\Exceptions\IndependentApproverRequiredException;
use App\Modules\Advertising\Services\Exceptions\SelfApprovalRefusedException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ADR-0010 §5 : l'auteur d'une campagne ne peut jamais être son propre
 * approbateur pour une campagne à risque élevé — même matrice que
 * `GrantManager::activate()` (P003-B3, TD-0001-A).
 */
class CampaignVersionSeparationOfDutiesTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_enhanced_review_level_requires_an_independent_approver(): void
    {
        $campaign = $this->makeCampaign();
        $sector = $this->makeSectorClassification(reviewLevel: ReviewLevel::Enhanced);
        $author = $this->makeRepresentative();

        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign, sector: $sector,
            creations: ['headline' => 'Titre'], expectedEvent: ['format' => 'banner'],
            destination: ['url' => 'https://annonceur.example.com'], territory: ['CI'],
            author: $author,
        );

        $this->expectException(IndependentApproverRequiredException::class);

        app(CampaignVersionService::class)->approve($version, null);
    }

    public function test_enhanced_review_level_refuses_self_approval(): void
    {
        $campaign = $this->makeCampaign();
        $sector = $this->makeSectorClassification(reviewLevel: ReviewLevel::Enhanced);
        $author = $this->makeRepresentative();

        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign, sector: $sector,
            creations: ['headline' => 'Titre'], expectedEvent: ['format' => 'banner'],
            destination: ['url' => 'https://annonceur.example.com'], territory: ['CI'],
            author: $author,
        );

        $this->expectException(SelfApprovalRefusedException::class);

        app(CampaignVersionService::class)->approve($version, $author);
    }

    public function test_enhanced_review_level_accepts_a_distinct_approver(): void
    {
        $campaign = $this->makeCampaign();
        $sector = $this->makeSectorClassification(reviewLevel: ReviewLevel::Enhanced);
        $author = $this->makeRepresentative();
        $approver = $this->makeRepresentative();

        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign, sector: $sector,
            creations: ['headline' => 'Titre'], expectedEvent: ['format' => 'banner'],
            destination: ['url' => 'https://annonceur.example.com'], territory: ['CI'],
            author: $author,
        );

        $approved = app(CampaignVersionService::class)->approve($version, $approver);

        $this->assertSame($approver->id, $approved->approver_person_account_link_id);
    }

    public function test_standard_review_level_does_not_require_an_approver(): void
    {
        $campaign = $this->makeCampaign();
        $sector = $this->makeSectorClassification(reviewLevel: ReviewLevel::Standard);
        $author = $this->makeRepresentative();

        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign, sector: $sector,
            creations: ['headline' => 'Titre'], expectedEvent: ['format' => 'banner'],
            destination: ['url' => 'https://annonceur.example.com'], territory: ['CI'],
            author: $author,
        );

        $approved = app(CampaignVersionService::class)->approve($version, null);

        $this->assertNull($approved->approver_person_account_link_id);
    }

    /**
     * Défense en profondeur inconditionnelle (contrainte
     * `campaign_versions_author_not_approver_check`) : même pour un niveau
     * de revue standard, un approbateur transmis ne peut jamais être
     * l'auteur.
     */
    public function test_self_approval_is_refused_even_at_standard_review_level(): void
    {
        $campaign = $this->makeCampaign();
        $sector = $this->makeSectorClassification(reviewLevel: ReviewLevel::Standard);
        $author = $this->makeRepresentative();

        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign, sector: $sector,
            creations: ['headline' => 'Titre'], expectedEvent: ['format' => 'banner'],
            destination: ['url' => 'https://annonceur.example.com'], territory: ['CI'],
            author: $author,
        );

        $this->expectException(SelfApprovalRefusedException::class);

        app(CampaignVersionService::class)->approve($version, $author);
    }
}
