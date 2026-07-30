<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal, nullable per-user locale preference storage (Phase 7,
 * Notifications sprint plan) — language, country, currency, and
 * timezone are independent settings (ADR-002; docs/architecture/
 * localization.md), so each gets its own column rather than a single
 * combined field. Deliberately generic: these columns belong to the
 * Identity-owned `users` table and are not Notifications-specific —
 * any future bounded context reads them through its own read port, the
 * same "consumer owns the port" discipline `TransferParticipantLookup`/
 * `TransferCaseLookup` already use for cross-module reads elsewhere.
 * This is foundational persistence only: no preference-management UI or
 * settings feature is introduced here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('language', 10)->nullable()->after('is_admin');
            $table->string('country_code', 2)->nullable()->after('language');
            $table->string('currency', 3)->nullable()->after('country_code');
            $table->string('timezone', 64)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['language', 'country_code', 'currency', 'timezone']);
        });
    }
};
