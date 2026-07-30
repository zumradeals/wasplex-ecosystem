<?php

namespace App\Modules\Wallet\Deposit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Deposit\Models\DepositWebhookEvent;
use App\Modules\Wallet\Deposit\Services\DepositWebhookService;
use App\Modules\Wallet\Deposit\Services\GeniusPay\GeniusPayWebhookSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook entrant GeniusPay (ADR-0007 §11 ; ecosystem/wallet/05 §4).
 * Authentifié par signature HMAC, jamais par une capacité ADR-0004 : cet
 * appel est serveur à serveur, sans session utilisateur — hors du groupe
 * `auth`, exempté de CSRF (`bootstrap/app.php`).
 *
 * Enregistre durablement toute réception, signature valide ou non, avant
 * toute tentative d'effet. Le traitement métier reste idempotent : un
 * échec ici n'empêche pas la commande de reprise
 * (`wallet:process-deposit-webhooks`) de le retraiter plus tard.
 */
class DepositWebhookController extends Controller
{
    public function __construct(
        private readonly GeniusPayWebhookSignatureVerifier $signatureVerifier,
        private readonly DepositWebhookService $webhookService,
    ) {}

    public function store(Request $request): Response
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-GeniusPay-Signature');
        $signatureValid = $this->signatureVerifier->verify($rawPayload, $signature);

        $event = DepositWebhookEvent::create([
            'provider' => 'geniuspay',
            'signature_valid' => $signatureValid,
            'raw_payload' => $rawPayload,
        ]);

        if (! $signatureValid) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'signature_invalid'])->save();

            return new Response('', 401);
        }

        try {
            $this->webhookService->process($event);
        } catch (Throwable $exception) {
            // L'entrée d'inbox reste enregistrée et non traitée
            // (`processed_at` nul) : la commande de reprise la retraitera.
            // On répond tout de même rapidement (ADR-0007 §11), la
            // durabilité de l'entrée est ce qui protège contre toute perte.
            Log::error('wallet_deposit_webhook_processing_failed', [
                'webhook_event_id' => $event->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return new Response('', 200);
    }
}
