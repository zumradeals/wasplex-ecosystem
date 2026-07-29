<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `alert_case.submit` (P008-A). Proposer puis soumettre un dossier communautaire (AMD-0007 ; ecosystem/alertes/02). Un SOS (`reportSos()`) n'exige délibérément aucune capacité — il doit fonctionner sans authentification (AMD-0007 §2) ; seules la validation serveur et une limite de fréquence le protègent.
 *
 * Clé adaptée en deux segments (`alert_case.{action}`) : la note P008-A suggère `alert.case.submit`, mais `governance.capability_definitions_stable_key_format_check` exige exactement un point (`^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$`) — vérifié au sens de la mission P008-A §10 (« vérifier les conventions du catalogue Governance avant de les créer »), pas une réinterprétation silencieuse.
 *
 * Portée réelle : `self` — chacun ne soumet que ses propres dossiers, même raisonnement que `campaign.create`.
 *
 * Dimensions : `operation=write`, `risk_class=ordinary` ;
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
            'stable_key' => 'alert_case.submit',
            'version' => 1,
            'domain' => 'alerts',
            'action' => 'submit',
            'description' => "Proposer puis soumettre un dossier communautaire (AMD-0007 ; ecosystem/alertes/02). Un SOS (`reportSos()`) n'exige délibérément aucune capacité — il doit fonctionner sans authentification (AMD-0007 §2) ; seules la validation serveur et une limite de fréquence le protègent.",
            'operation' => 'write',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'alert_case.submit')->delete();
    }
};
