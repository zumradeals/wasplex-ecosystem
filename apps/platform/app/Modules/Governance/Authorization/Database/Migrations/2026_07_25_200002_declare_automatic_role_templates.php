<?php

use App\Modules\Governance\Authorization\Services\GrantAutoIssuer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare les rôles modèles utilisés par
 * {@see GrantAutoIssuer}
 * (P007) pour émettre automatiquement les grants de base d'un compte,
 * plutôt que de laisser chaque capacité `self` inutilisable par tout
 * compte réel tant qu'aucun grant n'a été proposé et activé à la main
 * (TD-0005-D, TD-0004-E) :
 *
 * - `user.base` : toute personne inscrite (`wallet.view`,
 *   `advertiser_profile.create`, `campaign.view`, `campaign.report` — la
 *   dernière n'est pas `self`, mais reste largement disponible à tout
 *   utilisateur authentifié, voir sa migration de déclaration).
 *   `advertiser_profile.create` y figure nécessairement (P007-W1) : sans
 *   elle, aucun compte réel ne pourrait jamais déclarer son propre dossier
 *   annonceur et donc jamais atteindre `advertiser.representative`
 *   ci-dessous. `campaign.view` y figure aussi, avant même de devenir
 *   représentant : même raisonnement que `wallet.view` (consultable dès
 *   l'inscription, quitte à n'y voir encore aucune campagne) — sans quoi
 *   `AdvertisingOverviewController` ne pourrait jamais afficher l'état
 *   d'onboarding (« aucun dossier annonceur ») lui-même.
 * - `advertiser.representative` : toute personne devenant représentante
 *   d'un dossier annonceur (`campaign.create`, `campaign.submit_for_review`,
 *   `campaign.view` — ce dernier redondant avec `user.base` de façon
 *   assumée : `GrantAutoIssuer` est idempotent, et un futur retrait de
 *   `campaign.view` de `user.base` ne doit pas silencieusement priver un
 *   représentant existant de cette capacité).
 *
 * Un rôle modèle seul n'autorise rien (ADR-0004 §6, testé par
 * `RoleTemplatesAndSeparationTest::test_a_role_template_alone_authorizes_nothing`) :
 * seul `GrantAutoIssuer`, en proposant puis activant un `Grant` par
 * capacité listée, produit un droit réel.
 *
 * `automatic_role_template_grants` est la politique partagée référencée par
 * chacun de ces grants automatiques — un document de gouvernance unique et
 * stable, jamais recréé par instance (contrairement aux politiques de test
 * générées avec un `checksum` aléatoire).
 */
return new class extends Migration
{
    public function up(): void
    {
        $policyId = (string) Str::uuid7();

        DB::table('governance.policy_versions')->insert([
            'id' => $policyId,
            'stable_key' => 'automatic_role_template_grants',
            'version' => 1,
            'state' => 'active',
            'checksum' => hash('sha256', 'automatic_role_template_grants.v1'),
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertRoleTemplate(
            stableKey: 'user.base',
            label: 'Utilisateur',
            description: 'Ensemble de capacités proposées à toute personne inscrite : consultation de son propre Wallet, déclaration de son propre dossier annonceur, consultation de ses propres campagnes, signalement de campagnes (ADR-0004 §6).',
            organizationCategory: null,
            capabilityKeys: ['wallet.view', 'advertiser_profile.create', 'campaign.view', 'campaign.report'],
        );

        $this->insertRoleTemplate(
            stableKey: 'advertiser.representative',
            label: 'Représentant annonceur',
            description: "Ensemble de capacités proposées à toute personne devenant représentante d'un dossier annonceur : création et soumission de campagnes, consultation de ses propres campagnes (ADR-0004 §6, ADR-0010 §3).",
            organizationCategory: 'advertiser',
            capabilityKeys: ['campaign.create', 'campaign.submit_for_review', 'campaign.view'],
        );
    }

    public function down(): void
    {
        DB::table('governance.role_template_capabilities')
            ->whereIn('role_template_id', function ($query): void {
                $query->select('id')->from('governance.role_templates')
                    ->whereIn('stable_key', ['user.base', 'advertiser.representative']);
            })
            ->delete();

        DB::table('governance.role_templates')->whereIn('stable_key', ['user.base', 'advertiser.representative'])->delete();
        DB::table('governance.policy_versions')->where('stable_key', 'automatic_role_template_grants')->delete();
    }

    /**
     * @param  list<string>  $capabilityKeys
     */
    private function insertRoleTemplate(
        string $stableKey,
        string $label,
        string $description,
        ?string $organizationCategory,
        array $capabilityKeys,
    ): string {
        $roleTemplateId = (string) Str::uuid7();

        // Insérée `draft` d'abord : `role_template_capabilities_prevent_active_mutation`
        // (P003-B1.1 §4) interdit toute écriture de capacité dès qu'un
        // role_templates est `active` — le catalogue doit être complet
        // avant l'activation, jamais construit après coup.
        DB::table('governance.role_templates')->insert([
            'id' => $roleTemplateId,
            'stable_key' => $stableKey,
            'version' => 1,
            'label' => $label,
            'description' => $description,
            'organization_category' => $organizationCategory,
            'state' => 'draft',
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($capabilityKeys as $capabilityKey) {
            $capabilityId = DB::table('governance.capability_definitions')
                ->where('stable_key', $capabilityKey)
                ->where('state', 'active')
                ->value('id');

            DB::table('governance.role_template_capabilities')->insert([
                'id' => (string) Str::uuid7(),
                'role_template_id' => $roleTemplateId,
                'capability_definition_id' => $capabilityId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('governance.role_templates')->where('id', $roleTemplateId)->update([
            'state' => 'active',
            'updated_at' => now(),
        ]);

        return $roleTemplateId;
    }
};
