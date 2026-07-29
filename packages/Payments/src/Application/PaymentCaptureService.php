<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Application;

use RowBuddy\Payments\Contracts\DomainEventPublisher;
use RowBuddy\Payments\Contracts\PaymentAuthorizationGateway;
use RowBuddy\Payments\Contracts\PaymentIntentRepository;
use RowBuddy\Payments\Contracts\TransactionManager;
use RowBuddy\Payments\PaymentIntent;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;

/**
 * Exposes the capture/cancel operations a future Transfers-owned
 * `PaymentCaptureGateway` adapter will call (ADR-019 §4) — this service
 * is Payments' side of that not-yet-built cross-package contract, kept
 * self-contained so it can be validated independently of it.
 *
 * Implements ADR-019 §6's exact locked sequence: lock the row, guard that
 * it is still `Authorized` (anything else is an idempotent no-op, not an
 * error — the caller may have already checked its own idempotency, or a
 * concurrent attempt may have already resolved this payment intent),
 * call Stripe, apply the resulting transition, save, and publish
 * collected events only after the transaction commits.
 */
final class PaymentCaptureService
{
    public function __construct(
        private readonly PaymentIntentRepository $paymentIntents,
        private readonly PaymentAuthorizationGateway $gateway,
        private readonly TransactionManager $transactions,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @throws NotFoundException if no PaymentIntent exists for this auction
     */
    public function capture(string $auctionId): void
    {
        $events = $this->transactions->run(function () use ($auctionId): array {
            $paymentIntent = $this->lockByAuctionId($auctionId);

            if ($paymentIntent->status() !== PaymentIntentStatus::Authorized) {
                return [];
            }

            $result = $this->gateway->capture($this->requireStripePaymentIntentId($paymentIntent));

            if ($result->succeeded) {
                $paymentIntent->capture($this->clock);
            } else {
                $paymentIntent->failCapture($result->failureReason ?? 'unknown', $this->clock);
            }

            $this->paymentIntents->save($paymentIntent);

            return $paymentIntent->releaseEvents();
        });

        foreach ($events as $event) {
            $this->events->publish($event);
        }
    }

    /**
     * @throws NotFoundException if no PaymentIntent exists for this auction
     */
    public function cancel(string $auctionId, string $reason): void
    {
        $events = $this->transactions->run(function () use ($auctionId, $reason): array {
            $paymentIntent = $this->lockByAuctionId($auctionId);

            if ($paymentIntent->status() !== PaymentIntentStatus::Authorized) {
                return [];
            }

            $this->gateway->cancel($this->requireStripePaymentIntentId($paymentIntent), $reason);
            $paymentIntent->cancelAuthorization($reason, $this->clock);
            $this->paymentIntents->save($paymentIntent);

            return $paymentIntent->releaseEvents();
        });

        foreach ($events as $event) {
            $this->events->publish($event);
        }
    }

    /**
     * @throws NotFoundException if no PaymentIntent exists for this auction
     */
    private function lockByAuctionId(string $auctionId): PaymentIntent
    {
        $existing = $this->paymentIntents->findByAuctionId($auctionId);

        if ($existing === null) {
            throw new NotFoundException("No PaymentIntent found for auction [{$auctionId}].");
        }

        $paymentIntent = $this->paymentIntents->findByIdForUpdate($existing->id);

        if ($paymentIntent === null) {
            throw new NotFoundException("No PaymentIntent found for auction [{$auctionId}].");
        }

        return $paymentIntent;
    }

    private function requireStripePaymentIntentId(PaymentIntent $paymentIntent): string
    {
        // Authorized always carries a Stripe id (declineAuthorization()
        // never does) — the status guard above already ensures we only
        // reach here for an Authorized PaymentIntent.
        return $paymentIntent->stripePaymentIntentId ?? throw new NotFoundException(
            "PaymentIntent [{$paymentIntent->id}] has no Stripe payment intent id."
        );
    }
}
