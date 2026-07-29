<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RowBuddy\Payments\Application\StripeWebhookProcessor;
use RowBuddy\Payments\Contracts\WebhookSignatureVerifier;
use RowBuddy\Payments\Exceptions\InvalidWebhookSignature;

/**
 * Receives Stripe webhook deliveries (ADR-015 §1). Not user-facing — this
 * endpoint is called only by Stripe's own systems, authenticated solely
 * by the signature header, so its responses carry no translation keys
 * (CLAUDE.md's localization rules govern interface text shown to users,
 * not machine-to-machine API responses). CSRF-exempt (see
 * bootstrap/app.php) since Stripe cannot supply a Laravel CSRF token.
 */
final class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookSignatureVerifier $verifier, StripeWebhookProcessor $processor): JsonResponse
    {
        try {
            $event = $verifier->verify($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (InvalidWebhookSignature) {
            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $processor->process($event->stripeEventId, $event->eventType, $event->objectId);

        return response()->json(['received' => true]);
    }
}
