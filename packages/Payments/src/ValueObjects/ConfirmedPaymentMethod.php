<?php

declare(strict_types=1);

namespace RowBuddy\Payments\ValueObjects;

/**
 * The result of verifying a SetupIntent actually succeeded and belongs to
 * the requesting buyer (ADR-027 Architecture Refinements §4) — carries
 * only Stripe references, resolved fresh from Stripe itself, never from a
 * client-supplied claim.
 */
final class ConfirmedPaymentMethod
{
    public function __construct(
        public readonly string $stripeCustomerId,
        public readonly string $stripePaymentMethodId,
    ) {}
}
