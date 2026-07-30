<?php

use App\Modules\Wallet\Deposit\Models\ProviderCredential;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Table `ledger.wallet_deposit_provider_credentials` (véto du dirigeant,
 * 2026-07-30 : ouvrir une configuration admin des clés GeniusPay là où
 * TD-0008-A ne prévoyait jusqu'ici que l'environnement de production comme
 * porte de reprise). Un seul enregistrement par prestataire — `provider`
 * unique, contrainte pilote identique à `ledger.wallet_deposits`
 * (`provider = 'geniuspay'`).
 *
 * `api_key`/`api_secret`/`webhook_secret` sont chiffrées par le cast Eloquent
 * `encrypted` (voir {@see ProviderCredential}) :
 * la colonne ne porte donc jamais le secret en clair, et sa lisibilité
 * dépend de `APP_KEY` (jamais versionné, AMD-0017 §6 — « aucun secret
 * codé en dur ni versé au dépôt Git » : ceci reste vrai ici, seule la
 * valeur chiffrée touche le dépôt Git via les migrations, jamais un secret).
 * `base_url` n'est pas un secret et reste en clair.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE ledger.wallet_deposit_provider_credentials (
                id uuid PRIMARY KEY,
                provider text NOT NULL,
                base_url text NULL,
                api_key text NULL,
                api_secret text NULL,
                webhook_secret text NULL,
                updated_by_person_account_link_id uuid NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT wallet_deposit_provider_credentials_provider_unique UNIQUE (provider),
                CONSTRAINT wallet_deposit_provider_credentials_provider_pilot_check CHECK (provider = 'geniuspay')
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS ledger.wallet_deposit_provider_credentials');
    }
};
