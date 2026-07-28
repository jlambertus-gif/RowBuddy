<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The maximum amount a single `PaymentIntent` may authorize for the MVP,
 * as a temporary fraud/AML control (not a permanent domain invariant).
 * Exposed as a {@see Money} value, not a numeric amount plus a separate
 * currency, so every comparison against it stays inside the `Money`
 * value-object model — `PaymentIntent::authorize()` never handles a raw
 * amount/currency pair itself. `PaymentIntent` never knows or derives
 * this value; it only receives and validates against whatever this
 * policy currently resolves to.
 */
interface TransactionValueLimitPolicy
{
    public function maximum(): Money;
}
