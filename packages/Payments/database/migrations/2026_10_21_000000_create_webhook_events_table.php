<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The idempotency ledger (ADR-015 §1). `stripe_event_id` is the primary
 * key — the simplest possible enforcement of "process each Stripe event
 * at most once," with no separate surrogate id needed. Append-only, the
 * same shape as `bids` and `seller_payout_accounts`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->string('stripe_event_id')->primary();
            $table->string('event_type');
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
