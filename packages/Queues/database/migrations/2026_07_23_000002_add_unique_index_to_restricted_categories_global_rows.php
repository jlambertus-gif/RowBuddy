<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The table-level `unique(['code', 'jurisdiction_country'])` constraint from
 * the previous migration does not stop duplicate global rules: both
 * PostgreSQL and SQLite treat every NULL as distinct from every other NULL,
 * so two rows with the same `code` and a NULL `jurisdiction_country` do not
 * violate that constraint. A partial unique index — supported by both
 * drivers with identical syntax — closes that gap for the NULL case, while
 * the existing composite constraint continues to cover jurisdiction-specific
 * rows (where `jurisdiction_country` is never NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX restricted_categories_global_code_unique '
            .'ON restricted_categories (code) '
            .'WHERE jurisdiction_country IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS restricted_categories_global_code_unique');
    }
};
