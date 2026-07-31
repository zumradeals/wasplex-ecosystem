<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Table `advertising.subscription_plans` (instruction explicite du
 * fondateur, 2026-07-31 ; docs/02 §2). Chaque plan rattache exactement un
 * type économique (`economic_type_id`) — l'achat d'un plan est le chemin
 * normal vers un type autre que le défaut (voir `EconomicTypeResolver`).
 *
 * `duration_days` remplace la « période de renouvellement » textuelle de
 * docs/02 §2 par un entier simple : aucun renouvellement automatique n'est
 * codé dans ce lot (docs/02 §6 laisse la proratisation et l'immédiateté
 * d'un changement de plan comme décisions ouvertes) — un abonnement expire
 * simplement à `ends_at`, rachetable manuellement.
 *
 * Cycle de vie et unicité (stable_key, version) : même gabarit que
 * `advertising.economic_types` (migration `2026_07_31_200001`).
 *
 * `price_amount > 0` : aucun plan gratuit dans ce lot — un abonnement
 * gratuit court-circuiterait tout le flux GeniusPay
 * (`SubscriptionPurchaseInitiationService`), hors périmètre ici. À revoir
 * explicitement le jour où un plan gratuit est réellement décidé.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.subscription_plans (
                id uuid PRIMARY KEY,
                stable_key text NOT NULL,
                name text NOT NULL,
                version integer NOT NULL,
                price_amount integer NOT NULL CHECK (price_amount > 0),
                currency text NOT NULL,
                duration_days integer NOT NULL CHECK (duration_days > 0),
                economic_type_id uuid NOT NULL REFERENCES advertising.economic_types (id),
                state text NOT NULL,
                effective_from timestamptz NOT NULL DEFAULT now(),
                effective_to timestamptz NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT subscription_plans_state_check CHECK (state IN ('draft', 'active', 'retired')),
                CONSTRAINT subscription_plans_currency_format_check CHECK (currency ~ '^[A-Z]{3}$'),
                CONSTRAINT subscription_plans_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX subscription_plans_stable_key_version_unique ON advertising.subscription_plans (stable_key, version)');
        DB::statement("CREATE UNIQUE INDEX subscription_plans_one_active_per_key ON advertising.subscription_plans (stable_key) WHERE state = 'active'");
        DB::statement('CREATE INDEX subscription_plans_economic_type_id_index ON advertising.subscription_plans (economic_type_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.subscription_plans');
    }
};
