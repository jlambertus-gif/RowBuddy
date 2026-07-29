<?php

declare(strict_types=1);

namespace RowBuddy\Payments\ValueObjects;

use RowBuddy\Payments\Contracts\WebhookSignatureVerifier;

/**
 * The minimal, Stripe-SDK-free shape a verified webhook reduces to —
 * callers of {@see WebhookSignatureVerifier} never touch a Stripe SDK
 * type directly, keeping the "only Infrastructure knows Stripe's shape"
 * rule intact all the way out to apps/web.
 *
 * `objectId` is `data.object.id` from the Stripe event payload — for a
 * `payment_intent.*` event, the Stripe PaymentIntent id, needed by
 * ADR-019 §7's reconciliation to look up the corresponding local
 * `PaymentIntent`.
 */
final class VerifiedWebhookEvent
{
    public function __construct(
        public readonly string $stripeEventId,
        public readonly string $eventType,
        public readonly string $objectId,
    ) {}
}
