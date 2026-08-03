<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

use RowBuddy\Payments\Exceptions\SetupIntentBuyerMismatch;
use RowBuddy\Payments\Exceptions\SetupIntentNotConfirmed;
use RowBuddy\Payments\ValueObjects\ConfirmedPaymentMethod;
use RowBuddy\Payments\ValueObjects\SetupIntentDraft;

/**
 * Payments-owned port to Stripe's Customer/SetupIntent API (ADR-027
 * Architecture Refinements §4) — raw card data never reaches this port or
 * any caller of it; Stripe.js/Elements collects it directly on the
 * frontend and confirms the SetupIntent client-side. This port only ever
 * sees Stripe references.
 */
interface BuyerPaymentMethodGateway
{
    /**
     * Creates a new Stripe Customer for this buyer, stamped with
     * `metadata.rowbuddy_buyer_id` so it can be traced back to this buyer
     * from Stripe alone — the same traceability convention
     * {@see StripeConnectAccountGateway} already establishes for sellers.
     */
    public function createCustomer(string $buyerId): string;

    /**
     * Creates a SetupIntent for reuse (`usage: off_session`) scoped to
     * this Stripe Customer. `clientSecret` is the only value the frontend
     * ever receives.
     */
    public function createSetupIntent(string $stripeCustomerId): SetupIntentDraft;

    /**
     * Retrieves the SetupIntent fresh from Stripe (never trusting a
     * client-supplied "it succeeded" claim), verifies it actually
     * succeeded and that its own Customer's `metadata.rowbuddy_buyer_id`
     * matches `$buyerId`, and returns the resulting Customer/PaymentMethod
     * references.
     *
     * @throws SetupIntentNotConfirmed if the SetupIntent has not succeeded yet
     * @throws SetupIntentBuyerMismatch if the SetupIntent's Customer does
     *                                  not belong to this buyer
     */
    public function retrieveConfirmedPaymentMethod(string $setupIntentId, string $buyerId): ConfirmedPaymentMethod;
}
