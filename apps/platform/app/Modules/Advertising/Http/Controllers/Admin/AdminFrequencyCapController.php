<?php

namespace App\Modules\Advertising\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFrequencyCapBoundsRequest;
use App\Modules\Advertising\Models\FrequencyCapBounds;
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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestion admin du plafond de revisionnage gratuit (instruction explicite
 * du fondateur, 2026-07-31). Gouvernée par
 * `advertising.manage_frequency_cap` — mirroir exact
 * d'{@see AdminVideoDurationController}. Une nouvelle borne remplace la
 * précédente (`state = retired`), jamais une suppression physique.
 */
class AdminFrequencyCapController extends Controller
{
    use ResolvesStaffVisibility;

    private const CAPABILITY_KEY = 'advertising.manage_frequency_cap';

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
            'bounds' => null,
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/frequency-cap', $deniedProps);

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
            return Inertia::render('admin/frequency-cap', $deniedProps($authorization->reason->code));
        }

        $current = FrequencyCapBounds::query()->where('state', 'active')->first();

        return Inertia::render('admin/frequency-cap', [
            'access' => ['allowed' => true, 'reason' => null],
            'bounds' => $current === null ? null : $this->present($current),
        ]);
    }

    public function store(StoreFrequencyCapBoundsRequest $request): JsonResponse
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

        $bounds = DB::transaction(function () use ($request): FrequencyCapBounds {
            $previous = FrequencyCapBounds::query()->where('state', 'active')->first();

            if ($previous !== null) {
                $previous->forceFill(['state' => 'retired', 'effective_to' => now()])->save();
            }

            return FrequencyCapBounds::create([
                'daily_free_view_limit' => $request->validated('daily_free_view_limit'),
                'lifetime_free_view_limit' => $request->validated('lifetime_free_view_limit'),
                'version' => 1 + (int) FrequencyCapBounds::query()->max('version'),
                'state' => 'active',
            ]);
        });

        return response()->json($this->present($bounds), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(FrequencyCapBounds $bounds): array
    {
        return [
            'daily_free_view_limit' => $bounds->daily_free_view_limit,
            'lifetime_free_view_limit' => $bounds->lifetime_free_view_limit,
            'version' => $bounds->version,
        ];
    }

    private function resourceContext(Environment $environment): ResourceContext
    {
        return new ResourceContext(
            resourceType: 'advertising.frequency_cap_bounds',
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
