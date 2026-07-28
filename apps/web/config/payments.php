<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Platform fee percentage
    |--------------------------------------------------------------------------
    |
    | Whole-number percentage of the winning bid charged to the buyer on
    | top of the bid (docs/decisions/006-fee-model-buyer-side-percentage.md).
    | Provisional MVP configuration, not a permanent domain invariant — see
    | RowBuddy\Payments\Contracts\PlatformFeePolicy.
    |
    */

    'fee_percentage' => (int) env('PAYMENTS_FEE_PERCENTAGE', 10),

    /*
    |--------------------------------------------------------------------------
    | Transaction value limit (USD)
    |--------------------------------------------------------------------------
    |
    | Maximum amount, in whole USD dollars, a single PaymentIntent may
    | authorize for the MVP — a temporary fraud/AML control, not a
    | permanent domain invariant. See
    | RowBuddy\Payments\Contracts\TransactionValueLimitPolicy.
    |
    */

    'transaction_value_limit_usd' => (int) env('PAYMENTS_TRANSACTION_VALUE_LIMIT_USD', 500),

    /*
    |--------------------------------------------------------------------------
    | Estimated payment-processing cost
    |--------------------------------------------------------------------------
    |
    | A whole-number percentage plus a fixed fee (in cents) approximating
    | Stripe's real card-processing rate, used only for Phase 4's
    | "expected settlement" estimate (ADR-015 §1) — not the real, final
    | cost, which is only known once a payout actually executes. See
    | RowBuddy\Payments\Contracts\PaymentProcessingCostPolicy.
    |
    */

    'processing_fee_percentage' => (int) env('PAYMENTS_PROCESSING_FEE_PERCENTAGE', 3),

    'processing_fee_fixed_cents' => (int) env('PAYMENTS_PROCESSING_FEE_FIXED_CENTS', 30),

];
