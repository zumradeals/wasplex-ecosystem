<?php

namespace Tests\Feature\Modules\Alerts\Services;

use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Enums\PublicationStatus;
use App\Modules\Alerts\Models\Publication;
use App\Modules\Alerts\Services\CaseModerationService;
use App\Modules\Alerts\Services\Exceptions\InvalidCaseTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Alerts\AlertsTestCase;

class CaseModerationServiceTest extends AlertsTestCase
{
    use RefreshDatabase;

    public function test_publishing_creates_a_minimized_publication_never_containing_source_fields(): void
    {
        $reviewer = $this->makeRepresentative();
        $case = $this->makeCommunityCase(state: CommunityCaseState::UnderReview);

        $service = app(CaseModerationService::class);
        $case = $service->publish(
            $case,
            $reviewer,
            'Sac perdu',
            'Un sac a été perdu dans le quartier.',
            'Abidjan (zone approximative)',
            ['title' => true, 'summary' => true],
            (string) Str::uuid(),
        );

        $this->assertSame(CommunityCaseState::Published->value, $case->state);

        $publication = Publication::query()->where('case_id', $case->id)->firstOrFail();
        $this->assertSame(PublicationStatus::Published, $publication->status);

        $publicationArray = $publication->toArray();
        $this->assertArrayNotHasKey('exact_location', $publicationArray);
        $this->assertArrayNotHasKey('source_description', $publicationArray);
        $this->assertArrayNotHasKey('recall_phone', $publicationArray);
    }

    /**
     * Aucune publication n'est jamais automatique dans ce lot, quelle que
     * soit la catégorie (CaseModerationService::publish() exige toujours un
     * appel humain explicite depuis under_review) : une catégorie sensible
     * suit exactement le même chemin — ce test vérifie que
     * `requiresReinforcedReview()` identifie correctement les catégories
     * concernées, pour que l'écran de modération puisse afficher
     * l'avertissement (AMD-0007 §8 ; ecosystem/alertes/02 §6).
     */
    public function test_sensitive_categories_are_flagged_for_reinforced_review(): void
    {
        $this->assertTrue(CaseCategory::MissingPerson->requiresReinforcedReview());
        $this->assertTrue(CaseCategory::FoundPerson->requiresReinforcedReview());
        $this->assertTrue(CaseCategory::StolenVehicle->requiresReinforcedReview());
        $this->assertTrue(CaseCategory::LostDocument->requiresReinforcedReview());
        $this->assertFalse(CaseCategory::LostItem->requiresReinforcedReview());
    }

    public function test_publishing_a_case_still_in_draft_is_refused(): void
    {
        $reviewer = $this->makeRepresentative();
        $case = $this->makeCommunityCase(state: CommunityCaseState::Draft);

        $this->expectException(InvalidCaseTransitionException::class);

        app(CaseModerationService::class)->publish($case, $reviewer, 'x', 'x', null, [], (string) Str::uuid());
    }

    public function test_rejecting_a_case_records_the_reason_and_closes_it(): void
    {
        $reviewer = $this->makeRepresentative();
        $case = $this->makeCommunityCase(state: CommunityCaseState::UnderReview);

        $case = app(CaseModerationService::class)->reject($case, $reviewer, 'Preuve insuffisante.', (string) Str::uuid());

        $this->assertSame(CommunityCaseState::Rejected->value, $case->state);
        $this->assertSame('Preuve insuffisante.', $case->closure_reason);
        $this->assertNotNull($case->closed_at);
    }

    public function test_withdrawing_a_published_case_withdraws_its_active_publication_too(): void
    {
        $reviewer = $this->makeRepresentative();
        $case = $this->makeCommunityCase(state: CommunityCaseState::UnderReview);
        $case = app(CaseModerationService::class)->publish($case, $reviewer, 'x', 'x', null, [], (string) Str::uuid());

        $case = app(CaseModerationService::class)->withdraw($case, $reviewer, 'Retiré à la demande du déclarant.', (string) Str::uuid());

        $this->assertSame(CommunityCaseState::Withdrawn->value, $case->state);
        $publication = Publication::query()->where('case_id', $case->id)->firstOrFail();
        $this->assertSame(PublicationStatus::Withdrawn, $publication->status);
    }
}
