<?php

namespace App\Console\Commands;

use App\Modules\Advertising\Models\InterestTaxonomyEntry;
use Illuminate\Console\Command;

/**
 * Bootstrap manuel de la référence des centres d'intérêt du profil
 * publicitaire (véto du dirigeant, 2026-07-30) — même esprit que
 * `governance:grant-staff-capability` : aucun écran admin dédié dans ce
 * lot (même état que `advertising.sector_classifications` aujourd'hui),
 * seul moyen de peupler `advertising.interest_taxonomy_entries`.
 *
 * N'invente aucune valeur : les libellés réels sont choisis par le
 * fondateur après déploiement, jamais semés par une migration
 * (`ecosystem/publicite/01-cycle-creation-valeur.md` §5 ne cite « centres
 * d'intérêt » que comme exemple non normatif).
 */
class ManageInterestTaxonomy extends Command
{
    protected $signature = 'advertising:manage-interest-taxonomy
        {action : add ou retire}
        {code : Code stable du centre d\'intérêt (ex. "sport")}
        {label? : Libellé affiché (requis pour "add")}';

    protected $description = 'Ajoute ou retire une entrée de la référence des centres d\'intérêt du profil publicitaire.';

    public function handle(): int
    {
        $action = $this->argument('action');
        $code = $this->argument('code');

        return match ($action) {
            'add' => $this->add($code, $this->argument('label')),
            'retire' => $this->retire($code),
            default => $this->reportUnknownAction($action),
        };
    }

    private function add(string $code, ?string $label): int
    {
        if ($label === null || $label === '') {
            $this->components->error('Le libellé est requis pour "add".');

            return self::FAILURE;
        }

        $entry = InterestTaxonomyEntry::query()->where('code', $code)->first();

        if ($entry !== null) {
            $entry->forceFill(['label' => $label, 'state' => 'active'])->save();
            $this->components->info("Entrée «{$code}» déjà existante : libellé mis à jour, état réactivé.");

            return self::SUCCESS;
        }

        InterestTaxonomyEntry::create(['code' => $code, 'label' => $label, 'state' => 'active']);
        $this->components->info("Entrée «{$code}» ({$label}) ajoutée, active.");

        return self::SUCCESS;
    }

    private function retire(string $code): int
    {
        $entry = InterestTaxonomyEntry::query()->where('code', $code)->first();

        if ($entry === null) {
            $this->components->error("Aucune entrée «{$code}» trouvée.");

            return self::FAILURE;
        }

        $entry->forceFill(['state' => 'retired'])->save();
        $this->components->info("Entrée «{$code}» retirée (retired) — reste consultable sur les profils qui l'avaient déjà choisie.");

        return self::SUCCESS;
    }

    private function reportUnknownAction(string $action): int
    {
        $this->components->error("Action inconnue : {$action}. Attendu : add ou retire.");

        return self::FAILURE;
    }
}
