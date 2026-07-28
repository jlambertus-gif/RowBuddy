<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One linked Stripe Connect Express account per seller — enforced by a
 * unique constraint on seller_id, the same technique as
 * `auctions.presence_session_id` (ADR-009 §4). Deliberately carries no
 * charges_enabled/payouts_enabled columns: eligibility is always read
 * live from Stripe, never cached (see SellerPayoutAccount).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_payout_accounts', function (Blueprint $table) {
            $table->foreignId('seller_id')->primary();
            $table->string('stripe_account_id');
            $table->timestamp('linked_at');
            $table->timestamps();

            $table->unique('stripe_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payout_accounts');
    }
};
