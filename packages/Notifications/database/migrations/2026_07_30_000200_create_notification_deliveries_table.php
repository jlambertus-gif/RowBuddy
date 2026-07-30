<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The idempotency ledger ADR-025 §7 requires — an operational
 * correctness mechanism, not a business audit trail. At most one row per
 * (domain_event_id, recipient_id, notification_type), enforced by a
 * unique constraint mirroring `disputes.transfer_id`'s/`ratings.
 * (transfer_id, rater_id)`'s identical defense-in-depth technique.
 * `domain_event_id` is deliberately a plain string, not a foreign key —
 * its shape varies by notification type (an auctionId, transferId, or
 * disputeId, whichever stably identifies that event type's at-most-once
 * occurrence).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('domain_event_id');
            $table->foreignId('recipient_id');
            $table->string('notification_type');
            $table->timestamp('delivered_at');
            $table->timestamps();

            $table->unique(['domain_event_id', 'recipient_id', 'notification_type'], 'notification_deliveries_logical_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
