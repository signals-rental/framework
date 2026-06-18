<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Bootstraps the dedicated `pgsql_testing` connection for the `@group pgsql`
 * test lane.
 *
 * The default Signals test suite runs on SQLite `:memory:`, where PostgreSQL
 * range types (`tstzrange`), GiST indexes, and the serialised-asset exclusion
 * constraint cannot be exercised. Tests that need to prove those guarantees
 * `use()` this trait, which:
 *
 *  1. Probes the `pgsql_testing` connection. If Postgres is unreachable, the
 *     test is skipped (so the default suite stays green locally and in CI with
 *     no Postgres available — the lane is opt-in on machines that have it).
 *  2. Creates the dedicated test database if it does not yet exist (connecting
 *     to the `postgres` maintenance database to issue `CREATE DATABASE`).
 *  3. Points the application's default connection at `pgsql_testing` and runs a
 *     fresh `migrate:fresh` so the schema (incl. the `btree_gist` extension and
 *     the `demands` exclusion constraint) is present.
 *  4. Wraps each test in a transaction that is rolled back in tear-down, so the
 *     schema is migrated once per process but row state is isolated per test.
 *
 * Running the lane:
 *
 *   # Default (SQLite) suite — pgsql tests skip:
 *   php artisan test --compact
 *
 *   # Run ONLY the pgsql lane against local Postgres:
 *   php artisan test --compact --group=pgsql
 *
 *   # Override connection target via env if your Postgres differs:
 *   PGSQL_TESTING_HOST=127.0.0.1 PGSQL_TESTING_PORT=5432 \
 *   PGSQL_TESTING_USERNAME=postgres PGSQL_TESTING_PASSWORD=secret \
 *   PGSQL_TESTING_DATABASE=signals_pgsql_testing \
 *   php artisan test --compact --group=pgsql
 */
trait UsesPostgres
{
    /** Whether `migrate:fresh` has run for this connection in this process. */
    protected static bool $pgsqlMigrated = false;

    protected function setUpUsesPostgres(): void
    {
        $this->skipUnlessPostgresReachable();

        config(['database.default' => 'pgsql_testing']);
        DB::setDefaultConnection('pgsql_testing');

        if (! static::$pgsqlMigrated) {
            $this->artisan('migrate:fresh', [
                '--database' => 'pgsql_testing',
                '--force' => true,
            ])->run();

            static::$pgsqlMigrated = true;
        }

        DB::connection('pgsql_testing')->beginTransaction();
    }

    protected function tearDownUsesPostgres(): void
    {
        if (DB::connection('pgsql_testing')->transactionLevel() > 0) {
            DB::connection('pgsql_testing')->rollBack();
        }
    }

    /**
     * Skip the test unless the `pgsql_testing` Postgres server is reachable,
     * ensuring the dedicated test database exists first.
     */
    protected function skipUnlessPostgresReachable(): void
    {
        /** @var array<string, mixed> $config */
        $config = config('database.connections.pgsql_testing');

        try {
            $this->ensureTestDatabaseExists($config);

            // Verify the target database itself is reachable.
            DB::connection('pgsql_testing')->getPdo();
        } catch (Throwable $e) {
            $this->markTestSkipped(
                'PostgreSQL is not reachable for the pgsql test lane: '.$e->getMessage()
            );
        }
    }

    /**
     * Create the dedicated test database if it is missing, by connecting to the
     * `postgres` maintenance database on the same server.
     *
     * @param  array<string, mixed>  $config
     */
    protected function ensureTestDatabaseExists(array $config): void
    {
        $database = (string) $config['database'];

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=postgres',
            (string) $config['host'],
            (string) $config['port'],
        );

        $pdo = new PDO(
            $dsn,
            (string) $config['username'],
            (string) $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2],
        );

        $exists = $pdo->query(
            sprintf('SELECT 1 FROM pg_database WHERE datname = %s', $pdo->quote($database))
        )->fetchColumn();

        if ($exists === false) {
            // Identifier cannot be bound; it is sourced from config, not user input.
            $pdo->exec(sprintf('CREATE DATABASE "%s"', str_replace('"', '', $database)));
        }
    }
}
