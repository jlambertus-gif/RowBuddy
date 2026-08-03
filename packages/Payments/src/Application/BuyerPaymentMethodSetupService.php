<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Application;

use RowBuddy\Payments\BuyerPaymentMethod;
use RowBuddy\Payments\Contracts\BuyerPaymentMethodGateway;
use RowBuddy\Payments\Contracts\BuyerPaymentMethodRepository;
use RowBuddy\Payments\Contracts\DomainEventPublisher;
use RowBuddy\Payments\Exceptions\SetupIntentBuyerMismatch;
use RowBuddy\Payments\Exceptions\SetupIntentNotConfirmed;
use RowBuddy\Payments\ValueObjects\SetupIntentDraft;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Orchestrates buyer payment-method setup via Stripe SetupIntent (ADR-027
 * Architecture Refinements §4). Reuses an existing Stripe Customer if this
 * buyer already has one on record — a buyer replacing their saved payment
 * method never gets a second Stripe Customer.
 *
 * Mirrors {@see SellerOnboardingService}'s exact shape (find-or-create,
 * then delegate to the gateway) in the opposite direction: a buyer
 * concept instead of a seller one.
 */
final class BuyerPaymentMethodSetupService
{
    public function __construct(
        private readonly BuyerPaymentMethodRepository $methods,
        private readonly BuyerPaymentMethodGateway $gateway,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    public function beginSetup(string $buyerId): SetupIntentDraft
    {
        $existing = $this->methods->findByBuyerId($buyerId);
        $stripeCustomerId = $existing?->stripeCustomerId ?? $this->gateway->createCustomer($buyerId);

        return $this->gateway->createSetupIntent($stripeCustomerId);
    }

    /**
     * @throws SetupIntentNotConfirmed
     * @throws SetupIntentBuyerMismatch
     */
    public function completeSetup(string $buyerId, string $setupIntentId): BuyerPaymentMethod
    {
        $confirmed = $this->gateway->retrieveConfirmedPaymentMethod($setupIntentId, $buyerId);

        $method = BuyerPaymentMethod::save(
            $buyerId,
            $confirmed->stripeCustomerId,
            $confirmed->stripePaymentMethodId,
            $this->clock,
        );

        $this->methods->save($method);

        foreach ($method->releaseEvents() as $event) {
            $this->events->publish($event);
        }

        return $method;
    }
}
