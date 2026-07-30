<?php

namespace App\Modules\Advertising\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSectorClassificationRequest;
use App\Modules\Advertising\Models\SectorClassification;
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
use Carbon\CarbonImmutable;
use Illuminate\Database\Grammar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestion admin de la matrice de classification des secteurs (Lot 9, véto
 * du dirigeant 2026-07-30). Gouvernée par
 * `advertising.manage_sector_classifications` — mirroir d'autorisation
 * exact d'{@see AdminVideoDurationController}. Contrairement aux bornes de
 * durée vidéo (une seule ligne active pour toute la plateforme), l'index
 * unique partiel ici porte sur (country_code, sector) : plusieurs paires
 * restent actives simultanément, publier une nouvelle version n'en retire
 * qu'une seule. `sector_class = forbidden` n'est jamais accepté par cet
 * écran (voir `StoreSectorClassificationRequest`) — une interdiction
 * constitutionnelle se décide par amendement, jamais par une
 * configuration courante.
 */
class AdminSectorClassificationController extends Controller
{
    use ResolvesStaffVisibility;

    private const CAPABILITY_KEY = 'advertising.manage_sector_classifications';

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
            'classifications' => [],
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/sector-classifications', $deniedProps);

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
            return Inertia::render('admin/sector-classifications', $deniedProps($authorization->reason->code));
        }

        return Inertia::render('admin/sector-classifications', [
            'access' => ['allowed' => true, 'reason' => null],
            'classifications' => SectorClassification::query()
                ->orderBy('country_code')
                ->orderBy('sector')
                ->orderByDesc('version')
                ->get()
                ->map(fn (SectorClassification $classification): array => $this->present($classification))
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreSectorClassificationRequest $request): JsonResponse
    {
        $authorization = $this->authorize($request);

        if ($authorization instanceof JsonResponse) {
            return $authorization;
        }

        $countryCode = $request->validated('country_code');
        $sector = $request->validated('sector');

        $classification = DB::transaction(function () use ($request, $countryCode, $sector): SectorClassification {
            $previous = SectorClassification::query()
                ->where('country_code', $countryCode)
                ->where('sector', $sector)
                ->where('state', 'active')
                ->first();

            if ($previous !== null) {
                $previous->forceFill(['state' => 'retired', 'effective_to' => $this->effectiveToAfter($previous)])->save();
            }

            $nextVersion = 1 + (int) SectorClassification::query()
                ->where('country_code', $countryCode)
                ->where('sector', $sector)
                ->max('version');

            return SectorClassification::create([
                'country_code' => $countryCode,
                'sector' => $sector,
                'version' => $nextVersion,
                'sector_class' => $request->validated('sector_class'),
                'minimum_age' => $request->validated('minimum_age'),
                'required_evidence' => $request->validated('required_evidence') ?? [],
                'warnings' => $request->validated('warnings') ?? [],
                'allowed_formats' => $request->validated('allowed_formats') ?? [],
                'allowed_targeting' => $request->validated('allowed_targeting') ?? [],
                // `frequency_rules` n'est jamais lu/appliqué nulle part
                // aujourd'hui (hors périmètre, voir le plan du Lot 9) —
                // toujours vide ici. Explicite plutôt que confié au défaut
                // du modèle : `??=` dans `SectorClassification::booted()`
                // ne déclenche jamais l'accesseur `frequencyRules()` (qui
                // encode en objet JSON) puisque la lecture d'un attribut
                // non défini renvoie déjà `[]`, jamais `null` — un défaut
                // muet qui laisse la colonne NOT NULL sans valeur. Même
                // contournement explicite que `AdvertisingTestCase::
                // makeSectorClassification()` et `AdvertisingDemoConfigurationSeeder`.
                'frequency_rules' => [],
                'review_level' => $request->validated('review_level'),
                'minimum_approvals' => $request->validated('minimum_approvals'),
                'state' => 'active',
            ]);
        });

        return response()->json($this->present($classification), 201);
    }

    public function retire(Request $request, SectorClassification $sectorClassification): JsonResponse
    {
        $authorization = $this->authorize($request);

        if ($authorization instanceof JsonResponse) {
            return $authorization;
        }

        $sectorClassification->forceFill(['state' => 'retired', 'effective_to' => $this->effectiveToAfter($sectorClassification)])->save();

        return response()->json($this->present($sectorClassification));
    }

    /**
     * `sector_classifications_period_check` exige effective_to >
     * effective_from. Eloquent sérialise toujours une date en `Y-m-d H:i:s`
     * (précision à la seconde, {@see Grammar::getDateFormat()}
     * — jamais la vraie précision microseconde de la colonne Postgres) :
     * la contrainte est donc évaluée sur les valeurs tronquées à la
     * seconde, jamais sur les instances Carbon en mémoire (qui peuvent
     * porter des microsecondes différentes alors que leur seconde tronquée
     * est identique). Comparaison sur le format réellement persisté, pas
     * sur `lessThanOrEqualTo()` — la seule garantie fiable ici.
     */
    private function effectiveToAfter(SectorClassification $classification): Carbon|CarbonImmutable
    {
        $effectiveTo = now();
        $dateFormat = $classification->getDateFormat();

        return $effectiveTo->format($dateFormat) <= $classification->effective_from->format($dateFormat)
            ? $classification->effective_from->copy()->addSecond()
            : $effectiveTo;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SectorClassification $classification): array
    {
        return [
            'id' => $classification->id,
            'country_code' => $classification->country_code,
            'sector' => $classification->sector,
            'version' => $classification->version,
            'sector_class' => $classification->sector_class->value,
            'minimum_age' => $classification->minimum_age,
            'required_evidence' => $classification->required_evidence,
            'warnings' => $classification->warnings,
            'allowed_formats' => $classification->allowed_formats,
            'allowed_targeting' => $classification->allowed_targeting,
            'review_level' => $classification->review_level->value,
            'minimum_approvals' => $classification->minimum_approvals,
            'state' => $classification->state->value,
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
            resourceType: 'advertising.sector_classification',
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
