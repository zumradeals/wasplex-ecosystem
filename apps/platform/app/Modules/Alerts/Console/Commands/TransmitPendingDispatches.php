<?php

namespace App\Modules\Alerts\Console\Commands;

use App\Modules\Alerts\Services\CaseDispatchService;
use Illuminate\Console\Command;

/**
 * Worker outbox idempotent (architecture/10) : fait passer chaque
 * transmission institutionnelle `created` à `transmitted`. Nécessite une
 * exécution périodique (cron) — non configurée par ce lot, voir la dette
 * technique P008-A associée. Le rejeu est sans effet : une ligne déjà
 * `transmitted` n'est jamais reprise (requête filtrée sur `created` seul).
 */
class TransmitPendingDispatches extends Command
{
    protected $signature = 'alerts:transmit-dispatches {--limit=100}';

    protected $description = 'Transmet les dossiers Alertes routés (created) aux institutions destinataires (transmitted)';

    public function handle(CaseDispatchService $service): int
    {
        $count = $service->transmitPending((int) $this->option('limit'));

        $this->info("{$count} dispatch(es) transmis.");

        return self::SUCCESS;
    }
}
