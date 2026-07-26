<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\CampaignVersionFavorite;
use App\Modules\Advertising\Models\CampaignVersionLike;
use App\Modules\Advertising\Models\CampaignVersionShare;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Lot 3 Phase A (menu vertical du Feed — j'aime, favori, intention de
 * partage, décision de Koné 2026-07-26). Signaux sociaux purs : chaque
 * test vérifie explicitement l'absence de tout effet Ledger, en plus du
 * comportement fonctionnel (bascule idempotente, unicité, compteurs
 * exacts, 401/403 sûrs).
 */
class CampaignVersionSocialEngagementRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function approvedVersion(): CampaignVersion
    {
        $campaign = $this->makeCampaign();

        return $this->proposeAndApproveVersion($campaign);
    }

    private function assertNoLedgerOrBillingEffect(): void
    {
        $this->assertSame(0, DB::table('ledger.ledger_transactions')->count());
        $this->assertSame(0, QualifiedEvent::query()->count());
    }

    // --- j'aime -----------------------------------------------------

    public function test_like_an_unauthenticated_subject_receives_401(): void
    {
        $version = $this->approvedVersion();

        $response = $this->postJson("/advertising/campaign-versions/{$version->id}/like");

        $response->assertStatus(401);
        $this->assertSame(0, CampaignVersionLike::query()->count());
    }

    public function test_like_a_subject_without_a_grant_receives_a_safe_403(): void
    {
        $version = $this->approvedVersion();
        $user = $this->makeUser('no-grant-like-'.Str::uuid().'@example.com', LinkOrigin::Migration);

        $response = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/like");

        $response->assertStatus(403);
        $response->assertJsonStructure(['decision', 'reason', 'correlation_id']);
        $this->assertSame('no_active_grant', $response->json('reason'));
        foreach (['grant_id', 'policy_stable_key', 'policy_version', 'stable_key', 'capability_definition'] as $forbiddenFragment) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenFragment, $response->getContent());
        }
        $this->assertSame(0, CampaignVersionLike::query()->count());
    }

    public function test_like_toggles_on_then_off_and_removes_the_row(): void
    {
        $version = $this->approvedVersion();
        $user = $this->makeUser('liker-'.Str::uuid().'@example.com');

        $first = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/like");
        $first->assertStatus(200);
        $this->assertTrue($first->json('liked'));
        $this->assertSame(1, $first->json('likes_count'));
        $this->assertSame(1, CampaignVersionLike::query()->count());

        $second = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/like");
        $second->assertStatus(200);
        $this->assertFalse($second->json('liked'));
        $this->assertSame(0, $second->json('likes_count'));
        $this->assertSame(0, CampaignVersionLike::query()->count());

        $this->assertNoLedgerOrBillingEffect();
    }

    public function test_like_enforces_one_per_person_per_target(): void
    {
        $version = $this->approvedVersion();
        $user = $this->makeUser('unique-liker-'.Str::uuid().'@example.com');
        $link = $this->activeLinkFor($user);

        $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/like")->assertStatus(200);

        // Toute tentative de doublon direct (contournant la bascule
        // applicative) est refusée par la contrainte d'unicité du schéma,
        // pas seulement par la logique métier.
        $this->expectException(QueryException::class);
        CampaignVersionLike::create([
            'campaign_version_id' => $version->id,
            'person_account_link_id' => $link->id,
        ]);
    }

    public function test_like_counts_are_exact_across_multiple_people(): void
    {
        $version = $this->approvedVersion();
        $userA = $this->makeUser('liker-a-'.Str::uuid().'@example.com');
        $userB = $this->makeUser('liker-b-'.Str::uuid().'@example.com');
        $userC = $this->makeUser('liker-c-'.Str::uuid().'@example.com');

        $this->actingAs($userA)->postJson("/advertising/campaign-versions/{$version->id}/like")->assertStatus(200);
        $this->actingAs($userB)->postJson("/advertising/campaign-versions/{$version->id}/like")->assertStatus(200);
        $third = $this->actingAs($userC)->postJson("/advertising/campaign-versions/{$version->id}/like");

        $third->assertStatus(200);
        $this->assertSame(3, $third->json('likes_count'));
        $this->assertSame(3, CampaignVersionLike::query()->where('campaign_version_id', $version->id)->count());
    }

    // --- favori -------------------------------------------------------

    public function test_favorite_toggles_on_then_off(): void
    {
        $version = $this->approvedVersion();
        $user = $this->makeUser('favoriter-'.Str::uuid().'@example.com');

        $first = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/favorite");
        $first->assertStatus(200);
        $this->assertTrue($first->json('favorited'));
        $this->assertSame(1, $first->json('favorites_count'));

        $second = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/favorite");
        $second->assertStatus(200);
        $this->assertFalse($second->json('favorited'));
        $this->assertSame(0, $second->json('favorites_count'));
        $this->assertSame(0, CampaignVersionFavorite::query()->count());

        $this->assertNoLedgerOrBillingEffect();
    }

    public function test_favorite_an_unauthenticated_subject_receives_401(): void
    {
        $version = $this->approvedVersion();

        $response = $this->postJson("/advertising/campaign-versions/{$version->id}/favorite");

        $response->assertStatus(401);
        $this->assertSame(0, CampaignVersionFavorite::query()->count());
    }

    public function test_favorite_a_subject_without_a_grant_receives_a_safe_403(): void
    {
        $version = $this->approvedVersion();
        $user = $this->makeUser('no-grant-favorite-'.Str::uuid().'@example.com', LinkOrigin::Migration);

        $response = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/favorite");

        $response->assertStatus(403);
        $this->assertSame('no_active_grant', $response->json('reason'));
    }

    // --- partage --------------------------------------------------------

    public function test_share_records_a_new_event_each_time_never_a_toggle(): void
    {
        $version = $this->approvedVersion();
        $user = $this->makeUser('sharer-'.Str::uuid().'@example.com');

        $first = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/share");
        $second = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/share");

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame(1, $first->json('shares_count'));
        $this->assertSame(2, $second->json('shares_count'));
        $this->assertSame(2, CampaignVersionShare::query()->where('campaign_version_id', $version->id)->count());

        $this->assertNoLedgerOrBillingEffect();
    }

    public function test_share_an_unauthenticated_subject_receives_401(): void
    {
        $version = $this->approvedVersion();

        $response = $this->postJson("/advertising/campaign-versions/{$version->id}/share");

        $response->assertStatus(401);
        $this->assertSame(0, CampaignVersionShare::query()->count());
    }

    public function test_share_a_subject_without_a_grant_receives_a_safe_403(): void
    {
        $version = $this->approvedVersion();
        $user = $this->makeUser('no-grant-share-'.Str::uuid().'@example.com', LinkOrigin::Migration);

        $response = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/share");

        $response->assertStatus(403);
        $this->assertSame('no_active_grant', $response->json('reason'));
    }

    // --- routes ---------------------------------------------------------

    public function test_social_routes_are_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('campaign-versions/{campaignVersion}/like', $webRoutes);
        $this->assertStringContainsString('campaign-versions/{campaignVersion}/favorite', $webRoutes);
        $this->assertStringContainsString('campaign-versions/{campaignVersion}/share', $webRoutes);
        $this->assertTrue(Route::has('advertising.campaign-versions.like'));
        $this->assertTrue(Route::has('advertising.campaign-versions.favorite'));
        $this->assertTrue(Route::has('advertising.campaign-versions.share'));
    }
}
