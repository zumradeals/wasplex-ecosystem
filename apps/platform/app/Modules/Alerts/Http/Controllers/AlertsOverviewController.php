<?php

namespace App\Modules\Alerts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Alerts\Projections\PublicAlertFeedProjection;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthenticatedSubject;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Destination mobile `/alerts` (UX-0001 §19, §22 ; mission P008-A §14) :
 * alertes publiées, territorialement pertinentes, et « Mes déclarations ».
 * Le SOS reste atteignable en une action depuis cet écran (mission §14),
 * mais sa création elle-même passe par {@see SosReportController}, jamais
 * cette page.
 */
class AlertsOverviewController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly PublicAlertFeedProjection $publicFeed,
    ) {}

    public function index(Request $request): Response
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            $subject = null;
        }

        // Aucun filtrage territorial dans ce lot : aucune géolocalisation
        // n'est devinée depuis l'IP ou l'appareil (TD, voir dossier final).
        $published = $this->publicFeed->published(countryCode: null)
            ->map(fn ($publication): array => [
                'publication_id' => $publication->id,
                'title' => $publication->title,
                'summary' => $publication->summary,
                'approximate_zone' => $publication->approximate_zone,
                'category' => $publication->case->category->value,
                'published_at' => $publication->published_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('alerts/index', [
            'published' => $published,
            'my_declarations' => $this->myDeclarationsFor($subject),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function myDeclarationsFor(?AuthenticatedSubject $subject): array
    {
        if ($subject === null) {
            return [];
        }

        $personId = $subject->personAccountLink->person_id;
        $environment = Environment::tryFrom(app()->environment()) ?? Environment::Production;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'alert_case.view_self',
            operation: Operation::Read,
            resource: new ResourceContext(
                resourceType: 'alerts.case',
                resourceId: null,
                organizationId: null,
                ownerPersonId: $personId,
                countryCode: null,
                territoryCodes: [],
                environment: $environment,
            ),
            environment: $environment,
        );

        try {
            $this->authorizationGate->authorize($authorizationRequest);
        } catch (AuthorizationOutcomeException) {
            return [];
        }

        return AlertCase::query()
            ->where('author_person_account_link_id', $subject->personAccountLink->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (AlertCase $case): array => [
                'case_id' => $case->id,
                'nature' => $case->nature->value,
                'category' => $case->category->value,
                'state' => $case->state,
                'created_at' => $case->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
