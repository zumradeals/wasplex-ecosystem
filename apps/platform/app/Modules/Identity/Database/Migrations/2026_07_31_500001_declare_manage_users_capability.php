<?php

use App\Modules\Identity\Services\RegistersUserIdentity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `identity.manage_users` (instruction explicite du fondateur,
 * 2026-07-31 : « à ce jour l'administrateur n'a pas de fonctions
 * essentielles pour créer, modifier ou supprimer un utilisateur ») —
 * première capacité de gestion administrative des comptes.
 *
 * `risk_class = sensitive`, PAS `ordinary` : contrairement à une
 * configuration de référence (types économiques, plans d'abonnement),
 * cette capacité agit directement sur l'accès réel d'une personne
 * (suspension, clôture) — même niveau que `campaign.moderate`
 * (décision conséquente sur la ressource d'un tiers). PAS `critical` non
 * plus : aucune écriture Ledger, contrairement à `campaign.fund`/
 * `event.accept`.
 *
 * `minimum_session_assurance = weak` : même plancher que
 * `campaign.moderate`, laissé au futur relèvement documenté par
 * ADR-0004 §12 si le personnel l'exige plus tard, jamais inventé ici.
 *
 * La création d'un compte via cette capacité ne remplace jamais
 * l'inscription en libre-service ({@see RegistersUserIdentity})
 * — mêmes garanties transactionnelles, origine `support_review`
 * distincte pour ne jamais confondre un compte auto-inscrit d'un compte
 * créé par le personnel dans un audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'identity.manage_users',
            'version' => 1,
            'domain' => 'identity',
            'action' => 'manage_users',
            'description' => 'Créer un compte utilisateur pour une personne ou une entreprise, et suspendre, réactiver ou clôturer un compte existant.',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'identity.manage_users')->delete();
    }
};
