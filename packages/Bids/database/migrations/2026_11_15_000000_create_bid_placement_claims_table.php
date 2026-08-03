<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The HTTP-retry idempotency ledger for bid placement (Phase 9, ADR-027
 * Architecture Refinements §2) — mirrors `webhook_events`' own
 * unique-constraint-driven ledger shape. Composite primary key on
 * (bidder_id, idempotency_key): idempotency keys are scoped per bidder,
 * not global, so two different bidders coincidentally choosing the same
 * client-generated key never collide. `outcome`/`bid_id`/`rejection_reason`
 * stay null while a claim is still in flight — resolved exactly once, never
 * updated again afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_placement_claims', function (Blueprint $table) {
            $table->foreignId('bidder_id');
            $table->string('idempotency_key');
            $table->uuid('auction_id');
            $table->string('request_fingerprint');
            $table->string('outcome')->nullable();
            $table->uuid('bid_id')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->primary(['bidder_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_placement_claims');
    }
};
