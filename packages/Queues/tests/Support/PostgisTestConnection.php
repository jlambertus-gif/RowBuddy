<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Tests\Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Shared real-PostgreSQL/PostGIS test bootstrap (ADR-007 §7), used by
 * every test in this package that needs a genuine spatial connection —
 * extracted so the connection/schema setup exists in exactly one place
 * rather than duplicated per test file.
 *
 * Connects to a dedicated `rowbuddy_test` database (created here if
 * missing), never the real dev `rowbuddy` database's `queues` table.
 * Connection details come from env vars with defaults matching
 * docker-compose (so this runs correctly both from inside the app
 * container, where DB_HOST=postgres is already a real container env
 * var, and from the host via the published 5432 port). Skips the
 * calling test if no PostgreSQL/PostGIS connection is reachable.
 */
function postgisTestConnectionConfig(): array
{
    return [
        'driver' => 'pgsql',
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '5432',
        'username' => getenv('DB_USERNAME') ?: 'rowbuddy',
        'password' => getenv('DB_PASSWORD') ?: 'rowbuddy',
        'charset' => 'utf8',
    ];
}

function bootPostgisTestConnection(TestCase $test): void
{
    $config = postgisTestConnectionConfig();

    try {
        $bootstrap = new Capsule;
        $bootstrap->addConnection([...$config, 'database' => getenv('DB_DATABASE') ?: 'rowbuddy'], 'bootstrap');
        $pdo = $bootstrap->getConnection('bootstrap')->getPdo();

        $exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = 'rowbuddy_test'")->fetchColumn();

        if (! $exists) {
            $pdo->exec('CREATE DATABASE rowbuddy_test');
        }
    } catch (Throwable $e) {
        $test->markTestSkipped('No reachable PostgreSQL/PostGIS connection: '.$e->getMessage());

        return;
    }

    $capsule = new Capsule;
    $capsule->addConnection([...$config, 'database' => 'rowbuddy_test']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $connection = Capsule::connection();
    $connection->statement('CREATE EXTENSION IF NOT EXISTS postgis');
    $connection->statement('DROP TABLE IF EXISTS queues');
    $connection->statement(<<<'SQL'
        CREATE TABLE queues (
            id uuid PRIMARY KEY,
            category varchar(255) NOT NULL,
            jurisdiction_country varchar(2) NOT NULL,
            center_latitude decimal(10,7) NOT NULL,
            center_longitude decimal(10,7) NOT NULL,
            radius_meters double precision NOT NULL,
            authorship varchar(255) NOT NULL,
            organizer_reference varchar(255) NULL,
            status varchar(255) NOT NULL,
            coverage_area geometry(MultiPolygon, 4326) NULL,
            created_at timestamp NULL,
            updated_at timestamp NULL
        )
        SQL);
    $connection->statement('CREATE INDEX queues_coverage_area_gist ON queues USING GIST (coverage_area)');

    $connection->beginTransaction();
}

function rollbackPostgisTestConnection(): void
{
    // Best-effort teardown: if bootPostgisTestConnection() skipped the
    // test before ever calling setAsGlobal() (no reachable connection),
    // there is nothing to roll back, and Capsule::connection() itself
    // would throw on a null manager instance.
    try {
        if (Capsule::connection()->transactionLevel() > 0) {
            Capsule::connection()->rollBack();
        }
    } catch (Throwable) {
        // Nothing was ever booted — nothing to roll back.
    }
}
