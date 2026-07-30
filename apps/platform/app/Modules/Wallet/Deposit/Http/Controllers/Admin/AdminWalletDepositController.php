<?php

namespace App\Modules\Wallet\Deposit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Modules\Alerts\Http\Controllers\Admin\AdminAlertsController;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Wallet\Deposit\Enums\DepositState;
use App\Modules\Wallet\Deposit\Models\Deposit;
use App\Modules\Wallet\Deposit\Models\DepositWebhookEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Destination admin de supervision des dépôts Wallet GeniusPay (TD-0008-D,
 * AMD-0017 ; ecosystem/wallet/05). Consultation seule, gouvernée par
 * `wallet_deposit.review` — deux files : dépôts en `unknown_reconciliation`
 * (ecosystem/wallet/05 §5, jamais présentés comme réussis ni échoués) et
 * webhooks GeniusPay à signature invalide (§4 point 3, rejetés 401 et
 * journalisés). Aucune action de résolution n'est exposée par ce lot
 * (aucune contre-écriture, aucune relance provider) — même limite déjà
 * documentée pour « Contestations de restitution »
 * ({@see AdminAlertsController}).
 *
 * La lecture passe par le moteur d'autorisation complet : portée, période,
 * politique, assurance de session et audit sont appliqués avant toute donnée.
 * Les files sont paginées pour qu'un afflux de webhooks invalides ne puisse
 * jamais produire une réponse Inertia non bornée.
 */
class AdminWalletDepositController extends Controller
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
            'disputedDeposits' => $this->emptySection($reason),
            'invalidWebhooks' => $this->emptySection($reason),
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/wallet-deposits', $deniedProps);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        $environment = $this->currentEnvironment();
        $authorization = $this->authorizationGate->evaluate(
            $this->authorizationRequestFactory->make(
                subject: $resolved['subject'],
                capabilityKey: 'wallet_deposit.review',
                operation: Operation::Read,
                resource: new ResourceContext(
                    resourceType: 'wallet.deposit',
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

        // Un effet masked ne peut pas être appliqué honnêtement à cet écran
        // tant qu'un contrat de masquage champ par champ n'est pas défini :
        // refus fermé plutôt qu'exposition complète par défaut.
        $canReview = in_array($authorization->decision, [
            AuthorizationDecision::Allowed,
            AuthorizationDecision::AllowedReadOnly,
        ], true);

        if (! $canReview) {
            return Inertia::render('admin/wallet-deposits', $deniedProps($authorization->reason->code));
        }

        $access = ['allowed' => true, 'reason' => null];

        return Inertia::render('admin/wallet-deposits', [
            'disputedDeposits' => ['access' => $access, ...$this->disputedDeposits()],
            'invalidWebhooks' => ['access' => $access, ...$this->invalidSignatureWebhooks()],
        ]);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int|string|null>}
     */
    private function disputedDeposits(): array
    {
        $paginator = Deposit::query()
            ->where('state', DepositState::UnknownReconciliation->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['*'], 'deposit_page')
            ->withQueryString();

        $items = collect($paginator->items())
            ->map(fn (Deposit $deposit): array => [
                'deposit_id' => $deposit->id,
                'person_id' => $deposit->person_id,
                'country_code' => $deposit->country_code,
                'currency' => $deposit->currency,
                'amount' => $deposit->amount,
                'fees_amount' => $deposit->fees_amount,
                'net_amount' => $deposit->net_amount,
                'provider' => $deposit->provider,
                'provider_reference' => $deposit->provider_reference,
                'failure_reason' => $deposit->failure_reason,
                'created_at' => $deposit->created_at->toIso8601String(),
                'updated_at' => $deposit->updated_at->toIso8601String(),
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
        $paginator = DepositWebhookEvent::query()
            ->where('signature_valid', false)
            ->orderBy('received_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['*'], 'webhook_page')
            ->withQueryString();

        $items = collect($paginator->items())
            ->map(fn (DepositWebhookEvent $event): array => [
                'webhook_event_id' => $event->id,
                'provider' => $event->provider,
                'event_type' => $event->event_type,
                'wallet_deposit_id' => $event->wallet_deposit_id,
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
