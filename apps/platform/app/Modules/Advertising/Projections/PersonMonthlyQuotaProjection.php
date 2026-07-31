<?php

namespace App\Modules\Advertising\Projections;

use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Models\QualifiedEvent;
use Illuminate\Support\Carbon;

/**
 * Nombre d'événements publicitaires déjà rémunérés par une personne sur le
 * mois civil courant (instruction explicite du fondateur, 2026-07-31 ;
 * docs/02-abonnements-et-types-utilisateurs.md §4, décision confirmée : le
 * quota se consomme uniquement quand l'événement est validé et paie
 * réellement — un rejet antifraude ne consomme jamais une unité).
 *
 * Jamais un compteur stocké : reconstruit depuis `advertising.qualified_events`
 * à chaque lecture, même discipline que
 * {@see \App\Modules\Advertising\Projections\CampaignBudgetProjection}
 * (ADR-0003 §19). Mois civil UTC — décision technique par défaut,
 * documentée comme telle (docs/02 §4 laisse ce point ouvert ; « civil » et
 * « UTC » sont les valeurs les plus simples à auditer, changeables sans
 * migration de données puisque rien n'est stocké).
 */
class PersonMonthlyQuotaProjection
{
    public function consumedThisMonth(string $beneficiaryPersonAccountLinkId, ?Carbon $reference = null): int
    {
        $reference ??= now('UTC');

        return QualifiedEvent::query()
            ->where('beneficiary_person_account_link_id', $beneficiaryPersonAccountLinkId)
            ->where('billing_status', BillingStatus::Accepted)
            ->whereBetween('occurred_at', [
                $reference->copy()->startOfMonth(),
                $reference->copy()->endOfMonth(),
            ])
            ->count();
    }
}
