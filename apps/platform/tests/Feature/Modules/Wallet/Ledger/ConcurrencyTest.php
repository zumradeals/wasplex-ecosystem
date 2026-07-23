<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PgSql\Connection;
use Tests\TestCase;

/**
 * ADR-0003 §10 : deux tentatives concurrentes de consommer la même clé
 * d'idempotence n'aboutissent jamais à un double effet comptable — test de
 * concurrence réelle, pas seulement séquentielle (P004-A §4.10).
 *
 * Deux connexions PostgreSQL réellement distinctes (extension `pgsql`, déjà
 * présente dans le pipeline CI : `platform-tests.yml` installe
 * `pdo_pgsql, pgsql`), pas deux appels dans le même processus PHP : sous
 * `RefreshDatabase`, une transaction imbriquée reste une savepoint jamais
 * réellement validée, donc invisible à une autre connexion tant qu'elle ne
 * l'est pas — ce qui rendrait toute concurrence inobservable. Cette classe
 * n'utilise donc délibérément pas `RefreshDatabase` : les lignes créées ici
 * sont réellement validées, puis purgées explicitement en fin de test
 * (déclencheurs d'immutabilité désactivés le temps de cette seule purge
 * technique, jamais en production — voir `tearDown()`).
 *
 * La branche applicative correspondante (comment {@see LedgerPoster}
 * récupère après avoir perdu une course à l'idempotence) est démontrée de
 * façon déterministe par
 * `IdempotentReplayTest::test_the_service_recovers_transparently_when_it_loses_the_idempotency_race`.
 */
class ConcurrencyTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $createdTransactionIds = [];

    protected function tearDown(): void
    {
        $this->purgeCreatedRows();

        parent::tearDown();
    }

    public function test_two_concurrent_sessions_consuming_the_same_idempotency_key_never_produce_a_double_effect(): void
    {
        $scope = 'concurrency_test';
        $key = 'concurrent-key-'.Str::uuid();
        $fingerprint = hash('sha256', 'identical-content-for-both-sessions');

        $conn1 = $this->rawConnection();
        $conn2 = $this->rawConnection();

        try {
            pg_query($conn1, 'BEGIN');
            pg_query($conn2, 'BEGIN');

            $transactionId1 = (string) Str::uuid7();
            $transactionId2 = (string) Str::uuid7();

            // Session 1 comptabilise et ne valide pas encore : la ligne
            // existe mais n'est pas encore visible ailleurs.
            $inserted1 = pg_query_params($conn1, $this->insertTransactionSql(), [
                $transactionId1, $scope, $key, $fingerprint,
            ]);
            $this->assertNotFalse($inserted1, pg_last_error($conn1));

            // Session 2 tente la même clé, de façon asynchrone : l'appel ne
            // bloque pas PHP ; la requête reste "busy" côté serveur tant
            // que session 1 n'a pas relâché son verrou d'unicité.
            $sent = pg_send_query_params($conn2, $this->insertTransactionSql(), [
                $transactionId2, $scope, $key, $fingerprint,
            ]);
            $this->assertTrue($sent);

            usleep(200_000);

            // Preuve de la concurrence réelle : la requête de session 2 est
            // encore en attente, pas déjà résolue.
            $this->assertTrue(
                pg_connection_busy($conn2),
                'session 2 devrait être bloquée par le verrou de session 1, encore non validée à cet instant.'
            );

            $committed1 = pg_query($conn1, 'COMMIT');
            $this->assertNotFalse($committed1);
            $this->createdTransactionIds[] = $transactionId1;

            $result2 = pg_get_result($conn2);
            $sqlState2 = pg_result_error_field($result2, PGSQL_DIAG_SQLSTATE);

            $this->assertSame(
                '23505',
                $sqlState2,
                "session 2 doit échouer sur l'unicité de la clé d'idempotence, jamais créer un second effet comptable."
            );

            pg_query($conn2, 'ROLLBACK');

            $count = DB::table('ledger.ledger_transactions')
                ->where('idempotency_scope', $scope)
                ->where('idempotency_key', $key)
                ->count();

            $this->assertSame(1, $count, 'une seule transaction ne doit jamais exister pour cette clé, malgré la tentative concurrente.');
        } finally {
            pg_close($conn1);
            pg_close($conn2);
        }
    }

    /**
     * @return resource|Connection
     */
    private function rawConnection()
    {
        $config = config('database.connections.pgsql');

        // PGSQL_CONNECT_FORCE_NEW est indispensable ici : sans lui,
        // pg_connect() renvoie la même connexion mise en cache pour une
        // chaîne de connexion identique, ce qui romprait la démonstration de
        // deux sessions réellement distinctes.
        $connection = pg_connect(sprintf(
            'host=%s port=%s dbname=%s user=%s password=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['username'],
            $config['password'],
        ), PGSQL_CONNECT_FORCE_NEW);

        $this->assertNotFalse($connection, 'connexion PostgreSQL brute impossible pour le test de concurrence.');

        return $connection;
    }

    private function insertTransactionSql(): string
    {
        return <<<'SQL'
            INSERT INTO ledger.ledger_transactions (
                id, type, state, business_date, accounting_date,
                source_module, source_reference,
                idempotency_scope, idempotency_key, idempotency_fingerprint,
                correlation_id, authored_by, created_at, updated_at
            ) VALUES (
                $1, 'concurrency_test_movement', 'posted', CURRENT_DATE, CURRENT_DATE,
                'wallet_test', 'concurrency-test-reference',
                $2, $3, $4,
                gen_random_uuid(), 'wallet.ledger.test_suite', now(), now()
            )
        SQL;
    }

    /**
     * Les déclencheurs d'immutabilité (ADR-0003 §11) refusent toute
     * suppression, y compris pour un nettoyage de test : on les désactive
     * ici le temps strict de la purge, sur cette seule connexion, jamais en
     * production ni dans une migration.
     */
    private function purgeCreatedRows(): void
    {
        if ($this->createdTransactionIds === []) {
            return;
        }

        DB::statement('ALTER TABLE ledger.ledger_transactions DISABLE TRIGGER ledger_transactions_prevent_delete');

        try {
            DB::table('ledger.ledger_transactions')->whereIn('id', $this->createdTransactionIds)->delete();
        } finally {
            DB::statement('ALTER TABLE ledger.ledger_transactions ENABLE TRIGGER ledger_transactions_prevent_delete');
        }

        $this->createdTransactionIds = [];
    }
}
