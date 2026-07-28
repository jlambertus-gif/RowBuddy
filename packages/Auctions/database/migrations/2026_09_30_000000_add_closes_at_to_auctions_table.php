<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-013 §1: every auction now has an explicit closing deadline, set at
 * creation via AuctionDurationPolicy and possibly extended by
 * SoftCloseExtender. Not nullable — the aggregate treats closesAt as a
 * required value, not an optional one like proximity_at_risk_since.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->timestamp('closes_at')->after('opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('closes_at');
        });
    }
};
