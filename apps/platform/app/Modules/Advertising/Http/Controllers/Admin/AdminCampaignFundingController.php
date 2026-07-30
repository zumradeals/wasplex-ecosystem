<?php

namespace App\Modules\Advertising\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\CampaignFundingState;
use App\Modules\Advertising\Models\CampaignFunding;
use App\Modules\Advertising\Models\CampaignFundingWebhookEvent;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Wallet\Deposit\Http\Controllers\Admin\AdminWalletDepositController;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Destination admin de supervision des financements de campagne GeniusPay
 * (véto du dirigeant, 2026-07-30 ; mirroir exact de
 * {@see AdminWalletDepositController},
 * même doctrine TD-0008-D). Consultation seule, gouvernée par
 * `campaign_funding.review` — deux files : financements en
 * `unknown_reconciliation` et webhooks GeniusPay à signature invalide.
 * Aucune action de résolution n'est exposée par ce lot.
 */
class AdminCampaignFundingController extends Controller
{
    use ResolvesStaffVisibility;

    private const PER_PAGE = 50;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
    ) {}

    public function index(Request $request): Response
    {
        $deniedProps = fn (string $reason): array => [
            'disputedFundings' => $this->emptySection($reason),
            'invalidWebhooks' => $this->emptySection($reason),
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/campaign-fundings', $deniedProps);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        $environment = $this->currentEnvironment();
        $authorization = $this->authorizationGate->evaluate(
            $this->authorizationRequestFactory->make(
                subject: $resolved['subject'],
                capabilityKey: 'campaign_funding.review',
                operation: Operation::Read,
                resource: new ResourceContext(
                    resourceType: 'advertising.campaign',
                    resourceId: null,
                    organizationId: null,
                    ownerPersonId: null,
                    countryCode: null,
                    territoryCodes: [],
                    environment: $environment,
                ),
                environment: $environment,
            ),
        );

        $canReview = in_array($authorization->decision, [
            AuthorizationDecision::Allowed,
            AuthorizationDecision::AllowedReadOnly,
        ], true);

        if (! $canReview) {
            return Inertia::render('admin/campaign-fundings', $deniedProps($authorization->reason->code));
        }

        $access = ['allowed' => true, 'reason' => null];

        return Inertia::render('admin/campaign-fundings', [
            'disputedFundings' => ['access' => $access, ...$this->disputedFundings()],
            'invalidWebhooks' => ['access' => $access, ...$this->invalidSignatureWebhooks()],
        ]);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int|string|null>}
     */
    private function disputedFundings(): array
    {
        $paginator = CampaignFunding::query()
            ->where('state', CampaignFundingState::UnknownReconciliation->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['*'], 'funding_page')
            ->withQueryString();

        $items = collect($paginator->items())
            ->map(fn (CampaignFunding $funding): array => [
                'campaign_funding_id' => $funding->id,
                'campaign_id' => $funding->campaign_id,
                'currency' => $funding->currency,
                'amount' => $funding->amount,
                'fees_amount' => $funding->fees_amount,
                'net_amount' => $funding->net_amount,
                'provider' => $funding->provider,
                'provider_reference' => $funding->provider_reference,
                'failure_reason' => $funding->failure_reason,
                'created_at' => $funding->created_at->toIso8601String(),
                'updated_at' => $funding->updated_at->toIso8601String(),
            ])
            ->values()
            ->all();

        return ['items' => $items, 'pagination' => $this->pagination($paginator)];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int|string|null>}
     */
    private function invalidSignatureWebhooks(): array
    {
        $paginator = CampaignFundingWebhookEvent::query()
            ->where('signature_valid', false)
            ->orderBy('received_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['*'], 'webhook_page')
            ->withQueryString();

        $items = collect($paginator->items())
            ->map(fn (CampaignFundingWebhookEvent $event): array => [
                'webhook_event_id' => $event->id,
                'provider' => $event->provider,
                'event_type' => $event->event_type,
                'campaign_funding_id' => $event->campaign_funding_id,
                'received_at' => $event->received_at->toIso8601String(),
                'processing_result' => $event->processing_result,
            ])
            ->values()
            ->all();

        return ['items' => $items, 'pagination' => $this->pagination($paginator)];
    }

    /**
     * @return array{access: array{allowed: false, reason: string}, items: array<never>, pagination: array<string, int|string|null>}
     */
    private function emptySection(string $reason): array
    {
        return [
            'access' => ['allowed' => false, 'reason' => $reason],
            'items' => [],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => self::PER_PAGE,
                'total' => 0,
                'previous_url' => null,
                'next_url' => null,
            ],
        ];
    }

    /**
     * @template TModel of CampaignFunding|CampaignFundingWebhookEvent
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @return array{current_page: int, last_page: int, per_page: int, total: int, previous_url: ?string, next_url: ?string}
     */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'previous_url' => $paginator->previousPageUrl(),
            'next_url' => $paginator->nextPageUrl(),
        ];
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
