<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `alert_case.publish` (P008-A). Publier une projection publique minimisée d'un dossier communautaire (ecosystem/alertes/03 §2).
 *
 * Clé adaptée en deux segments (`alert_case.{action}`) : la note P008-A suggère `alert.case.publish`, mais `governance.capability_definitions_stable_key_format_check` exige exactement un point (`^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$`) — vérifié au sens de la mission P008-A §10 (« vérifier les conventions du catalogue Governance avant de les créer »), pas une réinterprétation silencieuse.
 *
 * Portée réelle : `resource_type = alerts.case_category`, personnel de modération. `risk_class=sensitive` : rend un contenu public, effet réel et visible.
 *
 * Dimensions : `operation=write`, `risk_class=sensitive` ;
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
            'stable_key' => 'alert_case.publish',
            'version' => 1,
            'domain' => 'alerts',
            'action' => 'publish',
            'description' => "Publier une projection publique minimisée d'un dossier communautaire (ecosystem/alertes/03 §2).",
            'operation' => 'write',
            'risk_class' => 'sensitive',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'alert_case.publish')->delete();
    }
};
