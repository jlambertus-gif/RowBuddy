<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-026 Architecture Refinements §6: `jurisdiction_rules` had no
 * activation state independent of its legal content (`permitted`) or
 * legal-effectiveness window (`effective_from`/`effective_to`) — this
 * adds one, mirroring `restricted_categories.active` exactly. The
 * column default backfills every existing row to active, preserving
 * today's gating behavior for rules that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurisdiction_rules', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('effective_to');
        });
    }

    public function down(): void
    {
        Schema::table('jurisdiction_rules', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
