<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

use RowBuddy\Payments\Exceptions\InvalidWebhookSignature;
use RowBuddy\Payments\ValueObjects\VerifiedWebhookEvent;

/**
 * Payments-owned port verifying an inbound Stripe webhook's authenticity.
 * apps/web's controller never touches the Stripe SDK directly — only the
 * Infrastructure adapter implementing this interface does, the same
 * "only Infrastructure knows Stripe's shape" rule as every other Stripe
 * integration point in this package.
 */
interface WebhookSignatureVerifier
{
    /**
     * @throws InvalidWebhookSignature if the payload's signature cannot
     *                                 be verified against the configured
     *                                 webhook secret
     */
    public function verify(string $payload, string $signatureHeader): VerifiedWebhookEvent;
}
