<?php

namespace App\Http\Controllers;

use App\Modules\Advertising\Models\CampaignFundingWebhookEvent;
use App\Modules\Advertising\Services\CampaignFundingWebhookService;
use App\Modules\Wallet\Deposit\Models\DepositWebhookEvent;
use App\Modules\Wallet\Deposit\Services\DepositWebhookService;
use App\Modules\Wallet\Deposit\Services\GeniusPay\GeniusPayWebhookSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook entrant GeniusPay unique (véto du dirigeant, 2026-07-30 ;
 * ADR-0007 §11). Un seul point de terminaison est configuré côté
 * prestataire en production ; ce contrôleur neutre (hors de tout module,
 * même précédent que {@see HealthController}/{@see AccountOverviewController})
 * répartit chaque réception entre les deux domaines qui peuvent la
 * concerner, sans qu'aucun des deux ne possède la table de l'autre :
 *
 * 1. Tente d'abord le dépôt Wallet ({@see DepositWebhookService}, code
 *    inchangé depuis AMD-0017) ;
 * 2. Si `processing_result === 'deposit_not_found'` (la référence ne
 *    correspond à aucun dépôt Wallet), tente ensuite le financement de
 *    campagne ({@see CampaignFundingWebhookService}).
 *
 * Remplace l'ancien `DepositWebhookController` (supprimé, sa logique de
 * réception/journalisation est reprise ici à l'identique pour le dépôt
 * Wallet). Authentifié par signature HMAC, jamais par une capacité ADR-0004
 * — hors du groupe `auth`, exempté de CSRF (`bootstrap/app.php`).
 */
class GeniusPayWebhookController extends Controller
{
    public function __construct(
        private readonly GeniusPayWebhookSignatureVerifier $signatureVerifier,
        private readonly DepositWebhookService $depositWebhookService,
        private readonly CampaignFundingWebhookService $campaignFundingWebhookService,
    ) {}

    public function store(Request $request): Response
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-GeniusPay-Signature');
        $signatureValid = $this->signatureVerifier->verify($rawPayload, $signature);

        $depositEvent = DepositWebhookEvent::create([
            'provider' => 'geniuspay',
            'signature_valid' => $signatureValid,
            'raw_payload' => $rawPayload,
        ]);

        if (! $signatureValid) {
            $depositEvent->forceFill(['processed_at' => now(), 'processing_result' => 'signature_invalid'])->save();

            return new Response('', 401);
        }

        try {
            $this->depositWebhookService->process($depositEvent);
        } catch (Throwable $exception) {
            Log::error('wallet_deposit_webhook_processing_failed', [
                'webhook_event_id' => $depositEvent->id,
                'exception' => $exception->getMessage(),
            ]);

            return new Response('', 200);
        }

        if ($depositEvent->processing_result === 'deposit_not_found') {
            $campaignFundingEvent = CampaignFundingWebhookEvent::create([
                'provider' => 'geniuspay',
                'signature_valid' => true,
                'raw_payload' => $rawPayload,
            ]);

            try {
                $this->campaignFundingWebhookService->process($campaignFundingEvent);
            } catch (Throwable $exception) {
                // L'entrée d'inbox reste enregistrée et non traitée
                // (`processed_at` nul) : la durabilité de l'entrée protège
                // contre toute perte, un retraitement pourra être ajouté
                // plus tard sans perdre l'événement.
                Log::error('campaign_funding_webhook_processing_failed', [
                    'webhook_event_id' => $campaignFundingEvent->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return new Response('', 200);
    }
}
