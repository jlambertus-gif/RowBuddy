<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The real Stripe PaymentIntent id this authorization created — needed so
 * a later capture()/cancelAuthorization() (ADR-019) knows which Stripe
 * object to act on. Nullable: a declined authorization never creates one
 * (PaymentIntent::declineAuthorization()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->after('fee_amount_minor_units');
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->dropColumn('stripe_payment_intent_id');
        });
    }
};
