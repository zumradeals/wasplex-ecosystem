<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Table `advertising.person_subscriptions` (instruction explicite du
 * fondateur, 2026-07-31) : abonnement courant d'une personne — une seule
 * ligne par personne, un nouvel achat écrase la précédente (pas
 * d'historique dans ce lot, même simplification que
 * `advertising.person_economic_type_assignments`, migration
 * `2026_07_31_200003`).
 *
 * `ends_at` gouverne l'expiration : jamais un sweep périodique qui
 * marquerait `expired` en arrière-plan — {@see EconomicTypeResolver} lit
 * `ends_at > now()` à chaque résolution (ADR-0003 §19 « aucun solde/état
 * n'est une donnée modifiable », même discipline que les projections
 * Ledger). L'expiration ne supprime jamais les WasPoints déjà gagnés
 * (docs/02 §7) — cette table ne touche jamais au Wallet.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.person_subscriptions (
                id uuid PRIMARY KEY,
                person_id uuid NOT NULL UNIQUE REFERENCES identity.people (id),
                subscription_plan_id uuid NOT NULL REFERENCES advertising.subscription_plans (id),
                starts_at timestamptz NOT NULL,
                ends_at timestamptz NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT person_subscriptions_period_check CHECK (ends_at > starts_at)
            )
        SQL);

        DB::statement('CREATE INDEX person_subscriptions_subscription_plan_id_index ON advertising.person_subscriptions (subscription_plan_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.person_subscriptions');
    }
};
