<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Exceptions;

use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Exceptions\DomainException;

final class IllegalStateTransition extends DomainException
{
    public static function forPaymentIntent(
        string $paymentIntentId,
        string $attemptedTransition,
        PaymentIntentStatus $currentStatus,
    ): self {
        return new self(
            "PaymentIntent [{$paymentIntentId}] cannot {$attemptedTransition} while in status [{$currentStatus->value}]."
        );
    }
}
