<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The postgis/postgis Docker image (ADR-007) auto-creates this extension
 * via its init scripts, but only against a freshly initialized data
 * directory — the existing dev volume predates the image switch, and any
 * environment that ever restores from a plain-postgres snapshot needs
 * this too. Idempotent either way.
 *
 * No-op on any driver other than pgsql: apps/web's own test suite runs
 * migrations against in-memory SQLite (phpunit.xml), which has no
 * concept of extensions and no PostGIS-specific SQL support at all — the
 * real PostGIS integration tests for this module never go through this
 * connection anyway (see PostGISQueueDiscoveryRepositoryTest).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP EXTENSION IF EXISTS postgis');
    }
};
