<?php

namespace App\Modules\Wallet\Deposit\Console\Commands;

use App\Modules\Wallet\Deposit\Models\DepositWebhookEvent;
use App\Modules\Wallet\Deposit\Services\DepositWebhookService;
use Illuminate\Console\Command;

/**
 * Commande de reprise idempotente (ADR-0007 §11 : « le traitement métier
 * est asynchrone et idempotent ») — même rôle que
 * `alerts:transmit-dispatches` (P008-A) pour les entrées d'inbox
 * (`ledger.wallet_deposit_webhook_events`) dont le traitement immédiat en
 * requête a échoué ou n'a pas encore eu lieu. Sûre à rejouer : un dépôt
 * déjà `completed`/`failed` court-circuite tout retraitement
 * ({@see DepositWebhookService::process()}).
 */
class ProcessDepositWebhooks extends Command
{
    protected $signature = 'wallet:process-deposit-webhooks';

    protected $description = "Retraite les entrées d'inbox de webhooks GeniusPay non encore traitées (AMD-0017)";

    public function handle(DepositWebhookService $service): int
    {
        $pending = DepositWebhookEvent::query()->whereNull('processed_at')->get();

        foreach ($pending as $event) {
            $service->process($event);
        }

        $this->info("Entrées traitées : {$pending->count()}");

        return self::SUCCESS;
    }
}
