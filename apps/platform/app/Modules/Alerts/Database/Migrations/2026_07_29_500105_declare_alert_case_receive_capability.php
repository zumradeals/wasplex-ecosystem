<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `alert_case.receive` (P008-A). Être une organisation éligible au routage d'un dossier pour une catégorie donnée (ecosystem/institutions/01 §4 ; InstitutionRoutingProjection).
 *
 * Clé adaptée en deux segments (`alert_case.{action}`) : la note P008-A suggère `alert.case.receive`, mais `governance.capability_definitions_stable_key_format_check` exige exactement un point (`^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$`) — vérifié au sens de la mission P008-A §10 (« vérifier les conventions du catalogue Governance avant de les créer »), pas une réinterprétation silencieuse.
 *
 * Portée réelle : `organization_id` + `resource_type = alerts.category` + `resource_ids` (catégories couvertes) — cette capacité n'exécute aucune action, elle rend seulement l'organisation visible du moteur de routage ; les actions réelles (`acknowledge`, `accept`...) restent des capacités séparées, jamais couvertes implicitement.
 *
 * Dimensions : `operation=read`, `risk_class=ordinary` ;
 * `purpose_required=false`, `approval_required=false` (même raisonnement
 * que les autres capacités déjà déclarées sur ce portail — aucune
 * consultation exploratoire, aucune formule de dual-control formalisée
 * dans ce lot) ; `minimum_session_assurance=weak` (aucun mouvement
 * financier ni action destructive dans ce lot — évite sciemment
 * `standard`, TD-0002-A n'a toujours pas de signal fiable).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'alert_case.receive',
            'version' => 1,
            'domain' => 'alerts',
            'action' => 'receive',
            'description' => "Être une organisation éligible au routage d'un dossier pour une catégorie donnée (ecosystem/institutions/01 §4 ; InstitutionRoutingProjection).",
            'operation' => 'read',
            'risk_class' => 'ordinary',
            'purpose_required' => false,
            'approval_required' => false,
            'minimum_session_assurance' => 'weak',
            'state' => 'active',
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('governance.capability_definitions')->where('stable_key', 'alert_case.receive')->delete();
    }
};
