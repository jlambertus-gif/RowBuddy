<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * At most one rating per participant per transfer (ADR-024 §3/
 * Consequences), enforced by a unique constraint on
 * `(transfer_id, rater_id)` — mirroring `disputes.transfer_id`'s
 * identical defense-in-depth technique, adapted to Ratings' two
 * independent rating slots per transfer. `rater_id`/`ratee_id` are
 * unsigned bigints matching `users.id`, no cross-package foreign key,
 * the same convention `disputes.buyer_id`/`seller_id` already uses.
 * `comment` is nullable text — blank/whitespace-only input is normalized
 * to `null` at the domain layer (ADR-024 §6) before it ever reaches this
 * table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transfer_id');
            $table->foreignId('rater_id');
            $table->foreignId('ratee_id');
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['transfer_id', 'rater_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
