<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `status` is a plain string, not a native enum column — the same
 * provisional-by-design choice as `auctions.status` (see that table's
 * migration): only `authorized`/`failed` exist today (ADR-015 §4/§5), and
 * a plain string avoids a schema migration merely to add `captured` once
 * Phase 5 defines the Transfers contract. `seller_id`/`buyer_id` mirror
 * `auctions.seller_id`/`bids.bidder_id`'s convention: an unsigned bigint
 * matching `users.id`, no cross-package foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('auction_id');
            $table->uuid('winning_bid_id');
            $table->foreignId('seller_id');
            $table->foreignId('buyer_id');
            $table->unsignedBigInteger('amount_minor_units');
            $table->char('amount_currency', 3);
            $table->unsignedBigInteger('fee_amount_minor_units');
            $table->string('status');
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index('auction_id');
            $table->index('winning_bid_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};
