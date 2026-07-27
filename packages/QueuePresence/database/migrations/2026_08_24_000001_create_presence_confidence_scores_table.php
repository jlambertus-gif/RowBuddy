<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only, per docs/product/claude-mvp-analysis.md §6: "computed
 * level + numeric score, computed_at — recomputed, not mutated in place."
 * Every recomputation inserts a new row; nothing ever updates or deletes
 * one, preserving the full history of how a session's confidence evolved.
 *
 * `computed_at` is stored with microsecond precision (unlike this
 * package's other timestamp columns, which default to whole seconds) so
 * that "the latest score for a session" has a genuinely reliable
 * ordering — two recomputations occurring within the same real-world
 * second are common (e.g. a ping immediately followed by an evidence
 * upload).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_confidence_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('presence_session_id');
            $table->unsignedInteger('points');
            $table->string('tier');
            $table->timestamp('computed_at', 6);
            $table->timestamps();

            $table->index('presence_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_confidence_scores');
    }
};
