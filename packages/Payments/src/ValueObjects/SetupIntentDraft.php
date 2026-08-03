<?php

declare(strict_types=1);

namespace RowBuddy\Payments\ValueObjects;

/**
 * The result of beginning a buyer payment-method setup —
 * `clientSecret` is the only Stripe identifier ever handed to the
 * frontend beyond the publishable key (ADR-027 Architecture Refinements
 * §4). `stripeCustomerId` stays server-side.
 */
final class SetupIntentDraft
{
    public function __construct(
        public readonly string $stripeCustomerId,
        public readonly string $setupIntentId,
        public readonly string $clientSecret,
    ) {}
}
