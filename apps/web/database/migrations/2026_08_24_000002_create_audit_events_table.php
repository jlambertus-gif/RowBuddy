<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The minimal, append-only audit sink (Sprint 6): a single, shared table
 * every module writes to via one generic listener
 * (App\Listeners\RecordAuditEvent) subscribed to the
 * RowBuddy\SharedKernel\Contracts\AuditableAction interface — not to any
 * one module's concrete event classes — so any bounded context's
 * AuditableAction events land here automatically, with no per-module
 * wiring. This is deliberately not the future Administration/Fraud-Risk
 * module (no case management, no admin UI, no risk scoring) — just the
 * durable record CLAUDE.md's "every verification action is audited" rule
 * requires right now.
 *
 * No separate `actor_id` column: whichever actor identifier is relevant
 * (seller_id, approved_by_user_id, etc.) already varies per event type
 * and is captured in `payload` — inventing a generic column for a concept
 * that isn't uniform across every event would be speculative, not
 * minimal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_name');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
