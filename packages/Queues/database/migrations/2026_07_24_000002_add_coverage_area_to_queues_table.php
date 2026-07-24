<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Coverage area for geospatial discovery (ADR-007), separate from the
 * point+radius `Geofence` already on this table (Sprint 2) used for
 * presence verification — coverage is a broader discoverability shape,
 * not a verification proximity check. Nullable: not every existing or
 * newly created queue has a defined coverage area yet (Sprint 6a adds no
 * caller that sets one outside tests; that wiring is Sprint 6b's job).
 *
 * geometry(MultiPolygon, 4326), not geography: coverage areas are
 * local/regional-scale polygons where planar point-in-polygon tests are
 * standard practice and GIST-indexable; geography's accurate long-
 * distance math isn't needed here.
 *
 * No-op on any driver other than pgsql — see
 * 2026_07_24_000001_enable_postgis_extension.php for why.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE queues ADD COLUMN coverage_area geometry(MultiPolygon, 4326) NULL');
        DB::statement('CREATE INDEX queues_coverage_area_gist ON queues USING GIST (coverage_area)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS queues_coverage_area_gist');
        DB::statement('ALTER TABLE queues DROP COLUMN IF EXISTS coverage_area');
    }
};
