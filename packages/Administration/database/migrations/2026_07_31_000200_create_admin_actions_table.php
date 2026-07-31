<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The typed, fail-closed operational record of deliberate administrative
 * decisions (ADR-026 Architecture Refinements §3) — a separate, narrower
 * concern from the platform-wide `audit_events` sink, which remains the
 * one immutable, complete record of every domain event. `admin_id` and
 * `reason` are never nullable: every row here is a real administrator's
 * explicit, reasoned decision, never an automated or system-triggered
 * one. Append-only — no `updated_at`, mirroring `bids.placed_at`'s
 * identical single-timestamp, immutable-record shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('admin_id');
            $table->string('action_type');
            $table->string('target_type');
            $table->string('target_id');
            $table->text('reason');
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->timestamp('created_at');

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
