<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-011 (Sprint 4): tracks the moment a live-proximity check first
 * detected this auction as stale — null while not at risk. `status`
 * itself needs no schema change: it was already a plain string column
 * with no CHECK constraint, so the new `cancelled` value requires no
 * migration of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->timestamp('proximity_at_risk_since')->nullable()->after('winning_amount_currency');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('proximity_at_risk_since');
        });
    }
};
