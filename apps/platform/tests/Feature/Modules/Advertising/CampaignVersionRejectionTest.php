<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Services\CampaignVersionService;
use App\Modules\Advertising\Services\Exceptions\CampaignVersionNotApprovableException;
use App\Modules\Advertising\Services\Exceptions\SelfApprovalRefusedException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Refus d'une CampaignVersion en revue (P005-C). `CampaignVersionState`
 * n'a pas d'état `rejected` (ADR-0010 §3) : l'interprétation retenue,
 * documentée au dossier de validation P005-C, est un retour à `draft` pour
 * révision par l'auteur, motif enregistré sur la ligne elle-même
 * (`rejection_reason`, `rejected_at`) — aucun nouvel état, aucune nouvelle
 * table.
 *
 * Contrairement à la soumission et à l'approbation, ce lot n'expose aucune
 * route HTTP pour le refus (non demandé par la mission ; même précédent que
 * `CampaignVersionService::suspend()`/`retire()`, déjà écrites par P005-A
 * sans route dédiée) : ces tests exercent directement le service, sur le
 * modèle de `CampaignVersionSeparationOfDutiesTest`.
 */
class CampaignVersionRejectionTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_rejecting_an_in_review_version_returns_it_to_draft_with_a_reason_and_keeps_the_original_content_consultable(): void
    {
        $campaign = $this->makeCampaign();
        $sector = $this->makeSectorClassification();
        $author = $this->makeRepresentative();
        $reviewer = $this->makeRepresentative();

        $service = app(CampaignVersionService::class);

        $version = $service->propose(
            campaign: $campaign,
            sector: $sector,
            creations: ['headline' => 'Titre original'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://annonceur.example.com'],
            territory: ['CI'],
            author: $author,
        );
        $service->submitForReview($version);

        $rejected = $service->reject($version, $reviewer, 'preuve de propriété manquante');

        $this->assertSame(CampaignVersionState::Draft, $rejected->state);
        $this->assertSame('preuve de propriété manquante', $rejected->rejection_reason);
        $this->assertNotNull($rejected->rejected_at);

        // L'ancienne preuve reste conservée, jamais supprimée ni écrasée
        // (ecosystem/publicite/02-preuves-moderation-et-destinations.md §2) :
        // le contenu original reste intact et consultable sur la même ligne.
        $stillThere = CampaignVersion::find($version->id);
        $this->assertNotNull($stillThere);
        $this->assertSame('Titre original', $stillThere->creations['headline']);
        $this->assertSame($version->id, $stillThere->id);
    }

    public function test_the_author_cannot_reject_their_own_version(): void
    {
        $campaign = $this->makeCampaign();
        $sector = $this->makeSectorClassification();
        $author = $this->makeRepresentative();

        $service = app(CampaignVersionService::class);

        $version = $service->propose(
            campaign: $campaign, sector: $sector,
            creations: ['headline' => 'Titre'], expectedEvent: ['format' => 'banner'],
            destination: ['url' => 'https://annonceur.example.com'], territory: ['CI'],
            author: $author,
        );
        $service->submitForReview($version);

        $this->expectException(SelfApprovalRefusedException::class);

        $service->reject($version, $author, 'motif quelconque');
    }

    public function test_only_an_in_review_version_can_be_rejected(): void
    {
        $campaign = $this->makeCampaign();
        $sector = $this->makeSectorClassification();
        $author = $this->makeRepresentative();
        $reviewer = $this->makeRepresentative();

        $service = app(CampaignVersionService::class);

        $draft = $service->propose(
            campaign: $campaign, sector: $sector,
            creations: ['headline' => 'Titre'], expectedEvent: ['format' => 'banner'],
            destination: ['url' => 'https://annonceur.example.com'], territory: ['CI'],
            author: $author,
        );

        $this->expectException(CampaignVersionNotApprovableException::class);

        $service->reject($draft, $reviewer, 'motif quelconque');
    }
}
