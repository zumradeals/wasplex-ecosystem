<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `alert_case.review` (P008-A). Faire entrer un dossier communautaire en revue, le restreindre, le refuser ou le retirer (CaseModerationService).
 *
 * Clé adaptée en deux segments (`alert_case.{action}`) : la note P008-A suggère `alert.case.review`, mais `governance.capability_definitions_stable_key_format_check` exige exactement un point (`^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$`) — vérifié au sens de la mission P008-A §10 (« vérifier les conventions du catalogue Governance avant de les créer »), pas une réinterprétation silencieuse.
 *
 * Portée réelle : `resource_type = alerts.case_category` sans `resource_ids` (personnel de modération, jamais scopée à un dossier annonceur — même forme que `campaign.moderate`). `risk_class=sensitive` : une revue conditionne la publication ou le rejet, effet réel sur la visibilité du dossier.
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
            'stable_key' => 'alert_case.review',
            'version' => 1,
            'domain' => 'alerts',
            'action' => 'review',
            'description' => 'Faire entrer un dossier communautaire en revue, le restreindre, le refuser ou le retirer (CaseModerationService).',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'alert_case.review')->delete();
    }
};
