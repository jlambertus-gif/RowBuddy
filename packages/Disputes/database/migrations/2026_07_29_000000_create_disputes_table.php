<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One dispute per transfer, enforced by a unique constraint on
 * `transfer_id` (ADR-021 Consequences: "no second filing against the
 * same Transfer once its dispute has resolved" — mirroring
 * `transfers.auction_id`'s identical technique, ADR-017 §6). `buyer_id`/
 * `seller_id`/`resolved_by` mirror `transfers.seller_id`/`buyer_id`'s
 * convention: an unsigned bigint matching `users.id`, no cross-package
 * foreign key. `refund_amount_*` is nullable — only populated for
 * `RefundToBuyer`/`Split` resolutions (ADR-022).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transfer_id')->unique();
            $table->uuid('auction_id');
            $table->foreignId('buyer_id');
            $table->foreignId('seller_id');
            $table->text('reason');
            $table->timestamp('opened_at');
            $table->string('status');
            $table->string('resolution_outcome')->nullable();
            $table->bigInteger('refund_amount_minor_units')->nullable();
            $table->string('refund_amount_currency', 3)->nullable();
            $table->foreignId('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->boolean('evidence_found_fraudulent')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('auction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
