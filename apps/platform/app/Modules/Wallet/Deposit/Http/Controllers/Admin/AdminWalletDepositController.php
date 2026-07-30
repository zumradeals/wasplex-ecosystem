<?php

namespace App\Modules\Wallet\Deposit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Modules\Alerts\Http\Controllers\Admin\AdminAlertsController;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Wallet\Deposit\Enums\DepositState;
use App\Modules\Wallet\Deposit\Models\Deposit;
use App\Modules\Wallet\Deposit\Models\DepositWebhookEvent;
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
 * Ne détecte volontairement pas les webhooks « répétés » : la seule notion
 * de répétition disponible aujourd'hui (rejeu idempotent d'un webhook déjà
 * traité, §4 point 4) est un comportement normal et attendu, pas une
 * anomalie — en détecter une exigerait de définir un seuil de suspicion
 * qu'aucune décision adoptée ne fixe (TD-0008-D reste ouvert pour cette
 * seule nuance).
 */
class AdminWalletDepositController extends Controller
{
    use ResolvesStaffVisibility;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
    ) {}

    public function index(Request $request): Response
    {
        $deniedProps = fn (string $reason): array => [
            'disputedDeposits' => ['access' => ['allowed' => false, 'reason' => $reason], 'items' => []],
            'invalidWebhooks' => ['access' => ['allowed' => false, 'reason' => $reason], 'items' => []],
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/wallet-deposits', $deniedProps);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        $canReview = $this->hasActiveStaffGrant($resolved['link'], 'wallet_deposit.review');

        return Inertia::render('admin/wallet-deposits', [
            'disputedDeposits' => [
                'access' => $this->staffAccessFor($canReview),
                'items' => $canReview ? $this->disputedDeposits() : [],
            ],
            'invalidWebhooks' => [
                'access' => $this->staffAccessFor($canReview),
                'items' => $canReview ? $this->invalidSignatureWebhooks() : [],
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function disputedDeposits(): array
    {
        return Deposit::query()
            ->where('state', DepositState::UnknownReconciliation->value)
            ->orderBy('created_at')
            ->get()
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
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function invalidSignatureWebhooks(): array
    {
        return DepositWebhookEvent::query()
            ->where('signature_valid', false)
            ->orderBy('received_at')
            ->get()
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
    }
}
