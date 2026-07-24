<?php

namespace Tests\Feature\Modules\Advertising;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PgSql\Connection;
use Tests\TestCase;

/**
 * ADR-0010 §7 : une même preuve ne produit jamais deux facturations ni
 * deux rémunérations, y compris sous course concurrente réelle — même
 * démonstration que `Tests\Feature\Modules\Wallet\Ledger\ConcurrencyTest`
 * (P004-A), appliquée ici à une clé d'idempotence dérivée d'un
 * QualifiedEvent (`advertising.reservation`) plutôt qu'à une clé
 * générique : la garantie sous-jacente est exactement la même contrainte
 * unique PostgreSQL sur `ledger.ledger_transactions`, que Publicité
 * n'écrit jamais directement mais dont elle hérite via `LedgerPoster`.
 *
 * Deux connexions PostgreSQL réellement distinctes (extension `pgsql`),
 * pas deux appels dans le même processus PHP : sous `RefreshDatabase`, une
 * transaction imbriquée reste une savepoint jamais réellement validée,
 * invisible à une autre connexion — voir la classe P004-A pour le détail
 * du raisonnement. Cette classe n'utilise donc pas `RefreshDatabase` ; les
 * lignes créées sont réellement validées puis purgées en fin de test.
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

    public function test_two_concurrent_sessions_reserving_the_same_qualified_event_never_produce_a_double_effect(): void
    {
        $scope = 'advertising.reservation';
        $key = 'concurrent-event-'.Str::uuid();
        $fingerprint = hash('sha256', 'identical-content-for-both-sessions');

        $conn1 = $this->rawConnection();
        $conn2 = $this->rawConnection();

        try {
            pg_query($conn1, 'BEGIN');
            pg_query($conn2, 'BEGIN');

            $transactionId1 = (string) Str::uuid7();
            $transactionId2 = (string) Str::uuid7();

            $inserted1 = pg_query_params($conn1, $this->insertTransactionSql(), [
                $transactionId1, $scope, $key, $fingerprint,
            ]);
            $this->assertNotFalse($inserted1, pg_last_error($conn1));

            $sent = pg_send_query_params($conn2, $this->insertTransactionSql(), [
                $transactionId2, $scope, $key, $fingerprint,
            ]);
            $this->assertTrue($sent);

            usleep(200_000);

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
                "session 2 doit échouer sur l'unicité de la clé d'idempotence, jamais créer un second effet."
            );

            pg_query($conn2, 'ROLLBACK');

            $count = DB::table('ledger.ledger_transactions')
                ->where('idempotency_scope', $scope)
                ->where('idempotency_key', $key)
                ->count();

            $this->assertSame(1, $count, 'une seule réservation ne doit jamais exister pour cette clé, malgré la tentative concurrente.');
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
                $1, 'advertising_campaign_reservation', 'posted', CURRENT_DATE, CURRENT_DATE,
                'advertising', 'concurrency-test-reference',
                $2, $3, $4,
                gen_random_uuid(), 'advertising.campaign_budget_service', now(), now()
            )
        SQL;
    }

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
