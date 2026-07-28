<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

use RowBuddy\Payments\ValueObjects\PayoutEligibility;

/**
 * Payments-owned port to Stripe Connect Express (the seller-onboarding
 * provider decided for the MVP). `SellerOnboardingService` never touches
 * the Stripe SDK directly — only the Infrastructure adapter implementing
 * this interface does, mirroring every other external/cross-boundary
 * dependency in this codebase (e.g. EvidenceStorage, ClockInterface).
 */
interface ConnectAccountGateway
{
    /**
     * Creates a new Stripe Connect Express account for this seller and
     * returns its Stripe account id. Called at most once per seller —
     * `SellerOnboardingService` only calls this when no
     * `SellerPayoutAccount` is already linked.
     */
    public function createExpressAccount(string $sellerId): string;

    /**
     * A one-time-use, short-lived hosted onboarding URL for the given
     * Stripe account. `returnUrl`/`refreshUrl` are supplied by the
     * caller — this port has no concept of routes.
     */
    public function createOnboardingLink(string $stripeAccountId, string $returnUrl, string $refreshUrl): string;

    /**
     * A live read of the account's current charges_enabled/payouts_enabled
     * flags — never cached by this port or by `SellerPayoutAccount`
     * (product decision: eligibility is whatever Stripe currently
     * reports, checked fresh, not derived or stored by RowBuddy).
     */
    public function fetchEligibility(string $stripeAccountId): PayoutEligibility;
}
