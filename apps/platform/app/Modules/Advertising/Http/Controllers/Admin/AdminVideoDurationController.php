<?php

namespace App\Modules\Advertising\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVideoAdDurationBoundsRequest;
use App\Modules\Advertising\Models\VideoAdDurationBounds;
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
 * Gestion admin des bornes de durée vidéo (Lot 4, instruction explicite du
 * fondateur 2026-07-30). Gouvernée par
 * `advertising.manage_video_duration_bounds` — mirroir exact
 * d'{@see AdminInterestTaxonomyController}. Une nouvelle borne remplace la
 * précédente (`state = retired`), jamais une suppression physique.
 */
class AdminVideoDurationController extends Controller
{
    use ResolvesStaffVisibility;

    private const CAPABILITY_KEY = 'advertising.manage_video_duration_bounds';

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

        $resolved = $this->resolveStaffSubject($request, 'admin/video-duration-bounds', $deniedProps);

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
            return Inertia::render('admin/video-duration-bounds', $deniedProps($authorization->reason->code));
        }

        $current = VideoAdDurationBounds::query()->where('state', 'active')->first();

        return Inertia::render('admin/video-duration-bounds', [
            'access' => ['allowed' => true, 'reason' => null],
            'bounds' => $current === null ? null : [
                'min_seconds' => $current->min_seconds,
                'max_seconds' => $current->max_seconds,
                'version' => $current->version,
            ],
        ]);
    }

    public function store(StoreVideoAdDurationBoundsRequest $request): JsonResponse
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

        $bounds = DB::transaction(function () use ($request): VideoAdDurationBounds {
            $previous = VideoAdDurationBounds::query()->where('state', 'active')->first();

            if ($previous !== null) {
                $previous->forceFill(['state' => 'retired', 'effective_to' => now()])->save();
            }

            return VideoAdDurationBounds::create([
                'min_seconds' => $request->validated('min_seconds'),
                'max_seconds' => $request->validated('max_seconds'),
                'version' => 1 + (int) VideoAdDurationBounds::query()->max('version'),
                'state' => 'active',
            ]);
        });

        return response()->json([
            'min_seconds' => $bounds->min_seconds,
            'max_seconds' => $bounds->max_seconds,
            'version' => $bounds->version,
        ], 201);
    }

    private function resourceContext(Environment $environment): ResourceContext
    {
        return new ResourceContext(
            resourceType: 'advertising.video_ad_duration_bounds',
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
