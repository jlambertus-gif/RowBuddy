<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only: no update/delete path exists anywhere in this codebase for
 * a `bids` row once inserted (CLAUDE.md: "all accepted bids are
 * immutable"), the same shape as `presence_confidence_scores` and
 * `audit_events`. `bidder_id` mirrors `auctions.seller_id`'s convention:
 * an unsigned bigint matching `users.id`, no cross-package foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('auction_id');
            $table->foreignId('bidder_id');
            $table->unsignedBigInteger('amount_minor_units');
            $table->char('amount_currency', 3);
            $table->timestamp('placed_at');
            $table->timestamps();

            $table->index('auction_id');
            $table->index('bidder_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
