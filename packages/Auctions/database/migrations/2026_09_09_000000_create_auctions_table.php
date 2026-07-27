<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `seller_id` is an unsigned bigint (matching apps/web's `users.id`), stored
 * as a plain column with no cross-package foreign-key constraint, the same
 * approach as `presence_sessions.seller_id`.
 *
 * `presence_session_id` carries a full (not partial) unique constraint —
 * unlike `presence_sessions`' "one *active* session per seller per queue"
 * partial index, ADR-009 §4's MVP restriction is unconditional: a
 * PresenceSession backs at most one auction regardless of that auction's
 * status. This is documented there as an explicitly provisional
 * restriction, not a permanent domain invariant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('queue_id');
            $table->foreignId('seller_id');
            $table->uuid('presence_session_id')->unique();
            $table->unsignedBigInteger('starting_price_minor_units');
            $table->char('starting_price_currency', 3);
            $table->timestamp('opened_at');
            $table->string('status');
            $table->string('winning_bid_id')->nullable();
            $table->unsignedBigInteger('winning_amount_minor_units')->nullable();
            $table->char('winning_amount_currency', 3)->nullable();
            $table->timestamps();

            $table->index('queue_id');
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
