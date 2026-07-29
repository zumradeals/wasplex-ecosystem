<?php

namespace App\Modules\Alerts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Enums\PublicationStatus;
use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Détail d'un dossier (mission P008-A §14) : statut et historique public
 * sûr uniquement — jamais le dossier source complet. Un dossier sans
 * publication active reste visible seulement à son auteur (`view_self`) ;
 * un tiers ne voit que la projection publiée, le cas échéant.
 */
class AlertCaseController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
    ) {}

    public function show(Request $request, AlertCase $case): Response
    {
        $publication = $case->publications()
            ->where('status', PublicationStatus::Published)
            ->orderByDesc('version')
            ->first();

        $isOwner = false;

        try {
            $subject = $this->subjectResolver->resolve($request);
            $personId = $subject->personAccountLink->person_id;
            $environment = Environment::tryFrom(app()->environment()) ?? Environment::Production;

            $authorizationRequest = $this->authorizationRequestFactory->make(
                subject: $subject,
                capabilityKey: 'alert_case.view_self',
                operation: Operation::Read,
                resource: new ResourceContext(
                    resourceType: 'alerts.case',
                    resourceId: $case->id,
                    organizationId: null,
                    ownerPersonId: $personId,
                    countryCode: null,
                    territoryCodes: [],
                    environment: $environment,
                ),
                environment: $environment,
            );

            $this->authorizationGate->authorize($authorizationRequest);
            $isOwner = $case->author_person_account_link_id === $subject->personAccountLink->id;
        } catch (SubjectResolutionFailedException|AuthorizationOutcomeException) {
            $isOwner = false;
        }

        if ($publication === null && ! $isOwner) {
            abort(404);
        }

        return Inertia::render('alerts/show', [
            'case' => [
                'case_id' => $case->id,
                'nature' => $case->nature->value,
                'category' => $case->category->value,
                'state' => $case->state,
                'created_at' => $case->created_at->toIso8601String(),
                'closed_at' => $case->closed_at?->toIso8601String(),
                'is_owner' => $isOwner,
            ],
            'publication' => $publication === null ? null : [
                'title' => $publication->title,
                'summary' => $publication->summary,
                'approximate_zone' => $publication->approximate_zone,
                'published_at' => $publication->published_at?->toIso8601String(),
            ],
            'history' => $isOwner
                ? $case->events()->orderBy('occurred_at')->get()->map(fn ($event): array => [
                    'event_type' => $event->event_type,
                    'to_state' => $event->to_state,
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                ])->values()->all()
                : [],
        ]);
    }
}
