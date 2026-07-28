<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when an inbound webhook payload's signature cannot be verified
 * against the configured Stripe webhook secret — the request is not
 * trusted and must not be processed (CLAUDE.md: "Stripe webhook forgery
 * if signature validation is missed on any endpoint").
 */
final class InvalidWebhookSignature extends DomainException
{
    public static function because(string $reason): self
    {
        return new self("Stripe webhook signature verification failed: {$reason}");
    }
}
