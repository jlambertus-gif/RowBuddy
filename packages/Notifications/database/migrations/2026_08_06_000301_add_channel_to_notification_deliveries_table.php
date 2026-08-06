<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The channel dimension ADR-028 Decision 6 requires: email and push
 * delivery for the same (domain_event_id, recipient_id, notification_type)
 * must be tracked independently, so one channel's delivery never
 * incorrectly suppresses the other's. Additive to the Phase 7-accepted
 * schema (ADR-025 §7), not a breaking change to it — every row that
 * already exists is a real, already-sent email, so `default('email')`
 * backfills them correctly with no data migration needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->string('channel')->default('email')->after('notification_type');
        });

        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropUnique('notification_deliveries_logical_identity_unique');
            $table->unique(
                ['domain_event_id', 'recipient_id', 'notification_type', 'channel'],
                'notification_deliveries_logical_identity_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropUnique('notification_deliveries_logical_identity_unique');
            $table->unique(
                ['domain_event_id', 'recipient_id', 'notification_type'],
                'notification_deliveries_logical_identity_unique',
            );
            $table->dropColumn('channel');
        });
    }
};
