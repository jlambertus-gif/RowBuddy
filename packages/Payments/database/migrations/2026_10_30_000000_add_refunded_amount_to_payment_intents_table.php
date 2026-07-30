<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-022: the refunded amount must be persisted and reconstructible on
 * `PaymentIntent` itself, not only in the `PaymentRefunded` audit event —
 * `remainingCapturedAmount()` is always derived from `amount` minus this
 * value, so no separate "remaining balance" column is needed. Nullable:
 * unset for every `PaymentIntent` that has never been refunded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->bigInteger('refunded_amount_minor_units')->nullable()->after('status');
            $table->string('refunded_amount_currency', 3)->nullable()->after('refunded_amount_minor_units');
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->dropColumn(['refunded_amount_minor_units', 'refunded_amount_currency']);
        });
    }
};
