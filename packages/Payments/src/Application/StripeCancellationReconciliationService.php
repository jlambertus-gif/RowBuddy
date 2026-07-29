<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Application;

use RowBuddy\Payments\Contracts\DomainEventPublisher;
use RowBuddy\Payments\Contracts\PaymentIntentRepository;
use RowBuddy\Payments\Contracts\TransactionManager;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * ADR-019 §7's secondary, defensive reconciliation — not the primary
 * mechanism for handling approaching expiry (ADR-018's scheduled sweep
 * is). Reacts only to `payment_intent.canceled`, confirming Stripe
 * already canceled an authorization our own scheduler failed to catch in
 * time (e.g. after scheduler downtime), by applying the exact same
 * `PaymentIntent::cancelAuthorization()` transition §2-§3 already define —
 * no new state or transition is introduced solely for webhook
 * reconciliation.
 *
 * Idempotent twice over: an unlocked `findByStripePaymentIntentId()` no-ops
 * if the webhook's `data.object.id` doesn't correspond to any known
 * `PaymentIntent` at all, and the locked guard below no-ops if it does but
 * is no longer `Authorized` (already `Captured`/`CaptureFailed`/
 * `Cancelled` locally) — a redelivered or late webhook is never an error.
 */
final class StripeCancellationReconciliationService
{
    public function __construct(
        private readonly PaymentIntentRepository $paymentIntents,
        private readonly TransactionManager $transactions,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    public function reconcileCancellation(string $stripePaymentIntentId): void
    {
        $existing = $this->paymentIntents->findByStripePaymentIntentId($stripePaymentIntentId);

        if ($existing === null) {
            return;
        }

        $events = $this->transactions->run(function () use ($existing) {
            $paymentIntent = $this->paymentIntents->findByIdForUpdate($existing->id);

            if ($paymentIntent === null || $paymentIntent->status() !== PaymentIntentStatus::Authorized) {
                return [];
            }

            $paymentIntent->cancelAuthorization('Stripe reported the authorization as canceled.', $this->clock);
            $this->paymentIntents->save($paymentIntent);

            return $paymentIntent->releaseEvents();
        });

        foreach ($events as $event) {
            $this->events->publish($event);
        }
    }
}
