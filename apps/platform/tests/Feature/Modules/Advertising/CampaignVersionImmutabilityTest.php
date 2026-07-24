<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Services\CampaignVersionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0010 §2, §3 ; P005-A §3.B : une CampaignVersion approuvée ne peut
 * voir aucun de ses champs sémantiques modifiés en base — sur le modèle
 * exact de `SemanticImmutabilityTest` (Governance/Authorization).
 */
class CampaignVersionImmutabilityTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_an_approved_version_refuses_semantic_field_update(): void
    {
        $campaign = $this->makeCampaign();
        $version = $this->proposeAndApproveVersion($campaign);

        $this->expectException(QueryException::class);

        DB::table('advertising.campaign_versions')
            ->where('id', $version->id)
            ->update(['creations' => json_encode(['headline' => 'Titre modifié'])]);
    }

    public function test_an_approved_version_refuses_physical_deletion(): void
    {
        $campaign = $this->makeCampaign();
        $version = $this->proposeAndApproveVersion($campaign);

        $this->expectException(QueryException::class);

        DB::table('advertising.campaign_versions')->where('id', $version->id)->delete();
    }

    public function test_an_approved_version_still_allows_a_state_transition_alone(): void
    {
        $campaign = $this->makeCampaign();
        $version = $this->proposeAndApproveVersion($campaign);

        app(CampaignVersionService::class)->suspend($version);

        $this->assertSame(CampaignVersionState::Suspended, $version->fresh()->state);
    }

    public function test_a_modification_of_an_approved_version_always_creates_a_new_version(): void
    {
        $campaign = $this->makeCampaign();
        $sector = $this->makeSectorClassification();
        $original = $this->proposeAndApproveVersion($campaign, $sector);

        $service = app(CampaignVersionService::class);
        $revised = $service->propose(
            campaign: $campaign,
            sector: $sector,
            creations: ['headline' => 'Titre révisé'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://annonceur.example.com/nouvelle-page'],
            territory: ['CI'],
            author: $original->author,
        );
        $service->approve($revised, $this->makeRepresentative());

        $this->assertNotSame($original->id, $revised->id);
        $this->assertSame(2, $revised->version);
        $this->assertSame(CampaignVersionState::Retired, $original->fresh()->state);
        $this->assertSame(CampaignVersionState::Approved, $revised->fresh()->state);

        // L'ancienne version reste intacte et consultable, jamais réécrite.
        $this->assertSame('Titre de test', $original->fresh()->creations['headline']);
    }

    public function test_a_draft_version_can_still_be_freely_composed(): void
    {
        $campaign = $this->makeCampaign();
        $sector = $this->makeSectorClassification();
        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $sector,
            creations: ['headline' => 'Brouillon'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://annonceur.example.com'],
            territory: ['CI'],
            author: $this->makeRepresentative(),
        );

        // Une version draft n'est pas encore protégée par le déclencheur :
        // une mise à jour directe reste possible tant qu'elle n'est pas
        // approuvée.
        DB::table('advertising.campaign_versions')
            ->where('id', $version->id)
            ->update(['creations' => json_encode(['headline' => 'Brouillon modifié'])]);

        $this->assertSame('Brouillon modifié', $version->fresh()->creations['headline']);
        $this->assertSame(CampaignVersionState::Draft, CampaignVersion::find($version->id)->state);
    }
}
