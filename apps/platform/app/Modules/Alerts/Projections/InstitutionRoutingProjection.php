<?php

namespace App\Modules\Alerts\Projections;

use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Identity\Enums\OrganizationCategory;
use App\Modules\Identity\Enums\OrganizationState;
use App\Modules\Identity\Models\Organization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Résolution des organisations éligibles au routage d'un dossier
 * (ecosystem/alertes/03 §1.1). Une organisation est éligible si elle est
 * une institution active, dans le bon pays, et si au moins un grant actif
 * `alert_case.receive` la porte pour cette catégorie précise
 * (`scope_payload.organization_id` + `scope_payload.resource_type =
 * 'alerts.category'` + `scope_payload.resource_ids` contenant la
 * catégorie) — pure réutilisation du modèle de portée ADR-0004 existant,
 * aucun nouveau champ Governance/Authorization.
 *
 * `resource_ids` n'est jamais un tableau JSON réel une fois stocké :
 * `Grant::scopePayload()` encode avec `JSON_FORCE_OBJECT`, qui transforme
 * aussi les listes imbriquées en objet à clés numériques (`{"0":"fire"}`).
 * Une containment `@>` de tableau ne correspond donc jamais ; la
 * comparaison porte sur les *valeurs* de cet objet via `jsonb_each_text`,
 * vérifié empiriquement (pas deviné) en inspectant une ligne réelle.
 *
 * Read-only : ne modifie jamais `governance.grants`.
 */
class InstitutionRoutingProjection
{
    /**
     * @return Collection<int, Organization>
     */
    public function eligibleFor(CaseCategory $category, string $countryCode): Collection
    {
        $now = Carbon::now();

        return Organization::query()
            ->where('category', OrganizationCategory::Institution)
            ->where('state', OrganizationState::Active)
            ->where('country_code', $countryCode)
            ->whereExists(function ($query) use ($category, $now): void {
                $query->select(DB::raw(1))
                    ->from('governance.grants as g')
                    ->join('governance.capability_definitions as cd', 'cd.id', '=', 'g.capability_definition_id')
                    ->whereRaw("g.scope_payload ->> 'organization_id' = identity.organizations.id::text")
                    ->where('cd.stable_key', 'alert_case.receive')
                    ->where('g.state', GrantState::Active->value)
                    ->where('g.valid_from', '<=', $now)
                    ->where(function ($q) use ($now): void {
                        $q->whereNull('g.valid_until')->orWhere('g.valid_until', '>', $now);
                    })
                    ->whereRaw("g.scope_payload ->> 'resource_type' = 'alerts.category'")
                    ->whereRaw(
                        "EXISTS (SELECT 1 FROM jsonb_each_text(g.scope_payload -> 'resource_ids') AS kv(k, v) WHERE kv.v = ?)",
                        [$category->value],
                    );
            })
            ->get();
    }
}
