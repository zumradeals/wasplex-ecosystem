<?php

namespace App\Modules\Advertising\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInterestTaxonomyEntryRequest;
use App\Modules\Advertising\Models\InterestTaxonomyEntry;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestion admin de la référence des centres d'intérêt du profil
 * publicitaire (véto du dirigeant, 2026-07-30). Gouvernée par
 * `advertising.manage_interest_taxonomy` — remplace, pour le personnel
 * habilité, la commande `advertising:manage-interest-taxonomy` (conservée,
 * toujours utilisable en secours).
 *
 * Aucune ligne n'est jamais supprimée physiquement : une entrée retirée
 * passe à `state = retired` (reste consultable sur les profils qui
 * l'avaient déjà choisie), jamais effacée.
 */
class AdminInterestTaxonomyController extends Controller
{
    use ResolvesStaffVisibility;

    private const CAPABILITY_KEY = 'advertising.manage_interest_taxonomy';

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
    ) {}

    public function index(Request $request): Response
    {
        $deniedProps = fn (string $reason): array => [
            'access' => ['allowed' => false, 'reason' => $reason],
            'entries' => [],
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/interest-taxonomy', $deniedProps);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        $environment = $this->currentEnvironment();
        $authorization = $this->authorizationGate->evaluate(
            $this->authorizationRequestFactory->make(
                subject: $resolved['subject'],
                capabilityKey: self::CAPABILITY_KEY,
                operation: Operation::Write,
                resource: $this->resourceContext($environment),
                environment: $environment,
            ),
        );

        if ($authorization->decision !== AuthorizationDecision::Allowed) {
            return Inertia::render('admin/interest-taxonomy', $deniedProps($authorization->reason->code));
        }

        return Inertia::render('admin/interest-taxonomy', [
            'access' => ['allowed' => true, 'reason' => null],
            'entries' => InterestTaxonomyEntry::query()
                ->orderBy('label')
                ->get(['id', 'code', 'label', 'state'])
                ->toArray(),
        ]);
    }

    public function store(StoreInterestTaxonomyEntryRequest $request): JsonResponse
    {
        $authorization = $this->authorize($request);

        if ($authorization instanceof JsonResponse) {
            return $authorization;
        }

        $entry = InterestTaxonomyEntry::create([
            'code' => $request->validated('code'),
            'label' => $request->validated('label'),
            'state' => 'active',
        ]);

        return response()->json(['id' => $entry->id, 'code' => $entry->code, 'label' => $entry->label, 'state' => $entry->state], 201);
    }

    public function toggle(Request $request, InterestTaxonomyEntry $interestTaxonomyEntry): JsonResponse
    {
        $authorization = $this->authorize($request);

        if ($authorization instanceof JsonResponse) {
            return $authorization;
        }

        $interestTaxonomyEntry->forceFill([
            'state' => $interestTaxonomyEntry->state === 'active' ? 'retired' : 'active',
        ])->save();

        return response()->json([
            'id' => $interestTaxonomyEntry->id,
            'code' => $interestTaxonomyEntry->code,
            'label' => $interestTaxonomyEntry->label,
            'state' => $interestTaxonomyEntry->state,
        ]);
    }

    private function authorize(Request $request): ?JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: self::CAPABILITY_KEY,
            operation: Operation::Write,
            resource: $this->resourceContext($this->currentEnvironment()),
            environment: $this->currentEnvironment(),
        );

        try {
            $this->authorizationGate->authorize($authorizationRequest);
        } catch (AuthorizationOutcomeException $exception) {
            return $this->failureResponder->forOutcome($exception);
        }

        return null;
    }

    private function resourceContext(Environment $environment): ResourceContext
    {
        return new ResourceContext(
            resourceType: 'advertising.interest_taxonomy_entry',
            resourceId: null,
            organizationId: null,
            ownerPersonId: null,
            countryCode: null,
            territoryCodes: [],
            environment: $environment,
        );
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
