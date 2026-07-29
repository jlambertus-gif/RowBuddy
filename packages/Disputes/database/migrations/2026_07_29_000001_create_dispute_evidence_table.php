<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dedicated one-to-many table, not a JSON column on `disputes` —
 * mirrors `transfer_evidence`'s exact shape and rationale (ADR-021 §7:
 * evidence is append-only, effectively immutable once referenced by a
 * `Dispute`; any future correction is modeled by appending, never
 * modifying or deleting). A plain auto-incrementing id, not a uuid —
 * `DisputeEvidenceRecord` (the domain value object) has no
 * externally-supplied id of its own to persist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_evidence', function (Blueprint $table) {
            $table->id();
            $table->uuid('dispute_id');
            $table->string('type');
            $table->text('storage_reference');
            $table->foreignId('submitted_by');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index('dispute_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_evidence');
    }
};
