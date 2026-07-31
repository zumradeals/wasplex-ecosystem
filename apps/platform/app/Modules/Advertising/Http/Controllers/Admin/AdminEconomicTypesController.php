<?php

namespace App\Modules\Advertising\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Requests\StoreEconomicTypeRequest;
use App\Modules\Advertising\Models\EconomicType;
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
 * Gestion admin des trois types économiques (instruction explicite du
 * fondateur, 2026-07-31 ; docs/02 §3, §8). Gouvernée par
 * `advertising.manage_economic_types` — mirroir d'autorisation exact
 * d'{@see AdminSectorClassificationController}. Chaque publication crée une
 * nouvelle version (jamais une mutation en place, docs/02 §8 « historique
 * des anciennes versions ») et retire l'ancienne version active de la même
 * `stable_key`.
 */
class AdminEconomicTypesController extends Controller
{
    use ResolvesStaffVisibility;

    private const CAPABILITY_KEY = 'advertising.manage_economic_types';

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
            'economicTypes' => [],
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/economic-types', $deniedProps);

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
            return Inertia::render('admin/economic-types', $deniedProps($authorization->reason->code));
        }

        return Inertia::render('admin/economic-types', [
            'access' => ['allowed' => true, 'reason' => null],
            'economicTypes' => EconomicType::query()
                ->orderBy('stable_key')
                ->orderByDesc('version')
                ->get()
                ->map(fn (EconomicType $type): array => $this->present($type))
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreEconomicTypeRequest $request): JsonResponse
    {
        $authorization = $this->authorize($request);

        if ($authorization instanceof JsonResponse) {
            return $authorization;
        }

        $stableKey = $request->validated('stable_key');
        $isDefault = (bool) $request->validated('is_default');

        $type = DB::transaction(function () use ($request, $stableKey, $isDefault): EconomicType {
            $previous = EconomicType::query()
                ->where('stable_key', $stableKey)
                ->where('state', 'active')
                ->first();

            if ($previous !== null) {
                $previous->forceFill(['state' => 'retired', 'effective_to' => now()])->save();
            }

            // Un seul type par défaut actif à la fois (index partiel
            // `economic_types_one_active_default`) : retirer le statut par
            // défaut de tout autre type avant de publier celui-ci, jamais
            // en compter deux simultanément.
            if ($isDefault) {
                EconomicType::query()
                    ->where('is_default', true)
                    ->where('state', 'active')
                    ->where('stable_key', '!=', $stableKey)
                    ->update(['is_default' => false]);
            }

            $nextVersion = 1 + (int) EconomicType::query()
                ->where('stable_key', $stableKey)
                ->max('version');

            return EconomicType::create([
                'stable_key' => $stableKey,
                'name' => $request->validated('name'),
                'version' => $nextVersion,
                'user_share_percentage' => $request->validated('user_share_percentage'),
                'monthly_quota' => $request->validated('monthly_quota'),
                'is_default' => $isDefault,
                'state' => 'active',
            ]);
        });

        return response()->json($this->present($type), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EconomicType $type): array
    {
        return [
            'id' => $type->id,
            'stable_key' => $type->stable_key,
            'name' => $type->name,
            'version' => $type->version,
            'user_share_percentage' => $type->user_share_percentage,
            'monthly_quota' => $type->monthly_quota,
            'is_default' => $type->is_default,
            'state' => $type->state->value,
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
            resourceType: 'advertising.economic_type',
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
