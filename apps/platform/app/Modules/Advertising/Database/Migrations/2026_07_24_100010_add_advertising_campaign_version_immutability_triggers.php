<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Immuabilité sémantique d'une CampaignVersion approuvée (ADR-0010 §2, §3 ;
 * P005-A §3.B) : sur le modèle exact des déclencheurs de
 * Governance/Authorization (P003-B1.1 §4, `grants_prevent_semantic_mutation`).
 *
 * Dès qu'une version atteint l'état `approved` (et pour toujours ensuite,
 * y compris `suspended`/`retired`), aucun champ substantiel n'est plus
 * modifiable. Seuls le cycle de vie (`state`, `approver_person_account_link_id`,
 * `approved_at`, `retired_at`, `updated_at`) restent mutables — une
 * campagne `draft`/`in_review` reste librement composable jusqu'à son
 * approbation.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION advertising.prevent_campaign_versions_semantic_mutation()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.state NOT IN ('approved', 'suspended', 'retired') THEN
                    RETURN NEW;
                END IF;

                IF NEW.campaign_id IS DISTINCT FROM OLD.campaign_id OR
                   NEW.version IS DISTINCT FROM OLD.version OR
                   NEW.sector_classification_id IS DISTINCT FROM OLD.sector_classification_id OR
                   NEW.creations IS DISTINCT FROM OLD.creations OR
                   NEW.expected_event IS DISTINCT FROM OLD.expected_event OR
                   NEW.destination IS DISTINCT FROM OLD.destination OR
                   NEW.territory IS DISTINCT FROM OLD.territory OR
                   NEW.pricing_configuration_key IS DISTINCT FROM OLD.pricing_configuration_key OR
                   NEW.pricing_configuration_version IS DISTINCT FROM OLD.pricing_configuration_version OR
                   NEW.reward_configuration_key IS DISTINCT FROM OLD.reward_configuration_key OR
                   NEW.reward_configuration_version IS DISTINCT FROM OLD.reward_configuration_version OR
                   NEW.valid_from IS DISTINCT FROM OLD.valid_from OR
                   NEW.valid_until IS DISTINCT FROM OLD.valid_until OR
                   NEW.author_person_account_link_id IS DISTINCT FROM OLD.author_person_account_link_id
                THEN
                    RAISE EXCEPTION 'advertising: une campaign_version approuvée ne peut voir aucun de ses champs sémantiques modifiés ; créez une nouvelle version (ADR-0010 §2, §3)';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER campaign_versions_prevent_semantic_mutation BEFORE UPDATE ON advertising.campaign_versions '
            .'FOR EACH ROW EXECUTE FUNCTION advertising.prevent_campaign_versions_semantic_mutation()'
        );

        // Aucune suppression physique d'une version ayant existé, sur le
        // modèle exact de `prevent_grants_deletion`.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION advertising.prevent_campaign_versions_deletion()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'advertising: une campaign_version ne peut jamais être supprimée physiquement (ADR-0010 §2, §3)';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER campaign_versions_prevent_deletion BEFORE DELETE ON advertising.campaign_versions '
            .'FOR EACH ROW EXECUTE FUNCTION advertising.prevent_campaign_versions_deletion()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS campaign_versions_prevent_deletion ON advertising.campaign_versions');
        DB::statement('DROP FUNCTION IF EXISTS advertising.prevent_campaign_versions_deletion()');
        DB::statement('DROP TRIGGER IF EXISTS campaign_versions_prevent_semantic_mutation ON advertising.campaign_versions');
        DB::statement('DROP FUNCTION IF EXISTS advertising.prevent_campaign_versions_semantic_mutation()');
    }
};
