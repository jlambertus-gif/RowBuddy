<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each recorded GPS ping, including whether it fell inside the queue's
 * geofence at the moment it was recorded (computed once, at capture time,
 * by the application service — never recomputed later). A dedicated table
 * rather than a polymorphic "presence_signals" table: v1 has exactly two
 * signal shapes (this one and evidence photos, Sprint 5), and their columns
 * don't overlap enough to justify a shared/generic schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_gps_pings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('presence_session_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->double('accuracy_meters');
            $table->boolean('within_geofence');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index('presence_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_gps_pings');
    }
};
