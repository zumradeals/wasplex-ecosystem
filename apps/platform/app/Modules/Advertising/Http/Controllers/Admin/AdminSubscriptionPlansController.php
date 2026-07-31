<?php

namespace App\Modules\Advertising\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Requests\StoreSubscriptionPlanRequest;
use App\Modules\Advertising\Models\EconomicType;
use App\Modules\Advertising\Models\SubscriptionPlan;
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
 * Gestion admin des plans d'abonnement (instruction explicite du
 * fondateur, 2026-07-31 ; docs/02 §2, §8). Gouvernée par
 * `advertising.manage_subscription_plans` — mirroir exact
 * d'{@see AdminEconomicTypesController}.
 */
class AdminSubscriptionPlansController extends Controller
{
    use ResolvesStaffVisibility;

    private const CAPABILITY_KEY = 'advertising.manage_subscription_plans';

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
            'plans' => [],
            'economicTypes' => [],
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/subscription-plans', $deniedProps);

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
            return Inertia::render('admin/subscription-plans', $deniedProps($authorization->reason->code));
        }

        return Inertia::render('admin/subscription-plans', [
            'access' => ['allowed' => true, 'reason' => null],
            'plans' => SubscriptionPlan::query()
                ->with('economicType')
                ->orderBy('stable_key')
                ->orderByDesc('version')
                ->get()
                ->map(fn (SubscriptionPlan $plan): array => $this->present($plan))
                ->values()
                ->all(),
            'economicTypes' => EconomicType::query()
                ->where('state', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray(),
        ]);
    }

    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        $authorization = $this->authorize($request);

        if ($authorization instanceof JsonResponse) {
            return $authorization;
        }

        $stableKey = $request->validated('stable_key');

        $economicType = EconomicType::query()
            ->where('id', $request->validated('economic_type_id'))
            ->where('state', 'active')
            ->first();

        if ($economicType === null) {
            return response()->json(['errors' => ['economic_type_id' => ["Ce type économique n'existe pas ou n'est plus actif."]]], 422);
        }

        $plan = DB::transaction(function () use ($request, $stableKey, $economicType): SubscriptionPlan {
            $previous = SubscriptionPlan::query()
                ->where('stable_key', $stableKey)
                ->where('state', 'active')
                ->first();

            if ($previous !== null) {
                $previous->forceFill(['state' => 'retired', 'effective_to' => now()])->save();
            }

            $nextVersion = 1 + (int) SubscriptionPlan::query()
                ->where('stable_key', $stableKey)
                ->max('version');

            return SubscriptionPlan::create([
                'stable_key' => $stableKey,
                'name' => $request->validated('name'),
                'version' => $nextVersion,
                'price_amount' => $request->validated('price_amount'),
                'currency' => strtoupper((string) $request->validated('currency')),
                'duration_days' => $request->validated('duration_days'),
                'economic_type_id' => $economicType->id,
                'state' => 'active',
            ]);
        });

        return response()->json($this->present($plan->load('economicType')), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'stable_key' => $plan->stable_key,
            'name' => $plan->name,
            'version' => $plan->version,
            'price_amount' => $plan->price_amount,
            'currency' => $plan->currency,
            'duration_days' => $plan->duration_days,
            'economic_type_id' => $plan->economic_type_id,
            'economic_type_name' => $plan->economicType->name,
            'state' => $plan->state->value,
        ];
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
            resourceType: 'advertising.subscription_plan',
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
