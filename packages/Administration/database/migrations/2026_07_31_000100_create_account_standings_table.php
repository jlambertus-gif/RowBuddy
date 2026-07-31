<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The materialized current-standing state ADR-026 §4 requires — a fast,
 * single-row-per-user read path for the suspension check every
 * consuming module's own `AccountStandingLookup` port resolves through.
 * `admin_actions` (the next migration) is the durable transition log;
 * this table is a cache of "what is currently true," not the audit
 * trail itself. A user with no row here has never been suspended and
 * is `Active` by definition (see `AccountStandingRepository`'s own
 * docblock) — this table is therefore populated only on a user's first
 * suspension, never backfilled for every user up front.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_standings', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('state');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_standings');
    }
};
