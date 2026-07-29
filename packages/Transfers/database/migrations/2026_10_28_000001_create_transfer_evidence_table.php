<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dedicated one-to-many table, not a JSON column on `transfers` —
 * evidence is zero-or-more per transfer by design (ADR-020 §4), and
 * Phase 6 will want to query/join it directly, the same reason
 * `presence_confidence_scores` is its own append-only table rather than
 * a column on `presence_sessions` (Phase 2 precedent). Append-only: no
 * update/delete path exists anywhere in this codebase for a row once
 * inserted. A plain auto-incrementing id, not a uuid — unlike every
 * other aggregate root in this codebase, `TransferEvidenceRecord` (the
 * domain value object, ADR-020 §4) has no externally-supplied id of its
 * own to persist; nothing yet needs to reference one individually.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_evidence', function (Blueprint $table) {
            $table->id();
            $table->uuid('transfer_id');
            $table->string('type');
            $table->string('storage_reference');
            $table->foreignId('submitted_by');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index('transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_evidence');
    }
};
