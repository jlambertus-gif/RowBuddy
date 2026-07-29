<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One transfer per auction, enforced by a unique constraint on
 * `auction_id` (ADR-017 §6), the same technique as
 * `auctions.presence_session_id` (ADR-009 §4) and
 * `seller_payout_accounts.seller_id` (Phase 4, Sprint 3). `seller_id`/
 * `buyer_id` mirror `auctions.seller_id`/`bids.bidder_id`'s convention:
 * an unsigned bigint matching `users.id`, no cross-package foreign key.
 * `qr_token_hash` stores only the hash — the plaintext QR value is never
 * persisted (ADR-017 §5). Latitude/longitude columns mirror
 * `presence_gps_pings`' `decimal(10, 7)` convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('auction_id')->unique();
            $table->uuid('winning_bid_id');
            $table->foreignId('seller_id');
            $table->foreignId('buyer_id');
            $table->string('qr_token_hash');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->string('status');
            $table->timestamp('seller_confirmed_at')->nullable();
            $table->decimal('seller_confirmed_latitude', 10, 7)->nullable();
            $table->decimal('seller_confirmed_longitude', 10, 7)->nullable();
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->decimal('buyer_confirmed_latitude', 10, 7)->nullable();
            $table->decimal('buyer_confirmed_longitude', 10, 7)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index('winning_bid_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
