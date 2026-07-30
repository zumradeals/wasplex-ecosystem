<?php

use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ScopeMatcher;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `governance.system_administrator` — amendement ADR-0004
 * 2026-07-30 (« Rôle Administrateur Système », décision du fondateur).
 *
 * Une capacité-marqueur, pas une capacité d'action sur une ressource : sa
 * seule présence, active, sur un compte fait de ce compte l'auteur exempté
 * de la matrice de séparation des tâches de {@see GrantManager::activate()}
 * pour les octrois qu'il émet (voir l'amendement pour le mécanisme exact et
 * ce qu'il NE change PAS : invariants C0, écritures Ledger, journalisation).
 *
 * `resource_type = governance.system` : une valeur nominale, exigée par
 * {@see ScopePayload} (« au
 * moins une restriction explicite »), jamais évaluée par
 * {@see ScopeMatcher} — cette
 * capacité n'est jamais vérifiée via `AuthorizationGate::authorize()`,
 * seulement par recherche directe d'un grant actif dans `GrantManager`
 * (même style que `ResolvesStaffVisibility::hasActiveStaffGrant()`).
 *
 * `risk_class = critical` : la plus haute classe existante — son
 * attribution initiale exige donc déjà un approbateur distinct (§12,
 * inchangé), et `GrantManager::activate()` refuse tout second grant actif
 * pendant qu'un premier existe (« un seul compte à la fois », amendement).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'governance.system_administrator',
            'version' => 1,
            'domain' => 'governance',
            'action' => 'system_administrator',
            'description' => "Marque le compte porteur du rôle Administrateur Système (amendement ADR-0004 2026-07-30) : exempté, comme auteur, de la matrice de séparation des tâches pour les octrois qu'il émet. Un seul compte actif à la fois.",
            'operation' => 'write',
            'risk_class' => 'critical',
            'purpose_required' => false,
            'approval_required' => false,
            'minimum_session_assurance' => 'strong',
            'state' => 'active',
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('governance.capability_definitions')->where('stable_key', 'governance.system_administrator')->delete();
    }
};
