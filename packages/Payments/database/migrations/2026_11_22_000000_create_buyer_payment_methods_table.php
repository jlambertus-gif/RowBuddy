<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One saved payment method per buyer (Phase 9, ADR-027 Architecture
 * Refinements §4) — `buyer_id` is the primary key, always overwritten in
 * place when a buyer replaces their saved method, unlike
 * `seller_payout_accounts`' link-once posture. Carries only the minimum
 * Stripe references needed for safe reuse (a Stripe Customer reference,
 * required by Stripe's own SetupIntent/PaymentMethod-reuse model, plus
 * the resulting PaymentMethod reference) — never card number, CVC,
 * expiry, or any other card detail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_payment_methods', function (Blueprint $table) {
            $table->foreignId('buyer_id')->primary();
            $table->string('stripe_customer_id');
            $table->string('stripe_payment_method_id');
            $table->timestamp('saved_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_payment_methods');
    }
};
