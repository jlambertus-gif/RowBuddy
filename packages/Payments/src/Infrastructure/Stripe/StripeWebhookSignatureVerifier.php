<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Stripe;

use RowBuddy\Payments\Contracts\WebhookSignatureVerifier;
use RowBuddy\Payments\Exceptions\InvalidWebhookSignature;
use RowBuddy\Payments\ValueObjects\VerifiedWebhookEvent;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;

/**
 * The only class in this package allowed to know the Stripe SDK's shape
 * for webhook verification. Signature verification is a pure, local
 * cryptographic check (HMAC-SHA256 against the configured secret) — it
 * makes no network call to Stripe.
 */
final class StripeWebhookSignatureVerifier implements WebhookSignatureVerifier
{
    public function __construct(private readonly string $webhookSecret) {}

    public function verify(string $payload, string $signatureHeader): VerifiedWebhookEvent
    {
        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);
        } catch (SignatureVerificationException|UnexpectedValueException $exception) {
            throw InvalidWebhookSignature::because($exception->getMessage());
        }

        return new VerifiedWebhookEvent($event->id, $event->type);
    }
}
