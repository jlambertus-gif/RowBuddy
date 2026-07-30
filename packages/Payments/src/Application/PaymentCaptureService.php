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
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Exposes the capture/cancel operations the Transfers-owned
 * `PaymentCaptureGateway` adapter calls (ADR-019 §4), and, since Phase 6,
 * the refund operation the Disputes-owned refund contract calls
 * (ADR-022 §1) — one service for every operation that locks a
 * `PaymentIntent` row and calls Stripe against it, the same "grow the
 * existing service" precedent `PaymentAuthorizationGateway` itself
 * follows for its own methods, rather than one class per operation.
 *
 * Implements ADR-019 §6's exact locked sequence for capture/cancel, and
 * the equivalent sequence for refund: lock the row, guard the expected
 * prior status (anything else is an idempotent no-op, not an error — the
 * caller may have already checked its own idempotency, or a concurrent
 * attempt may have already resolved this payment intent), call Stripe,
 * apply the resulting transition, save, and publish collected events
 * only after the transaction commits.
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
     * ADR-022's exact locked refund sequence — financial-safety critical,
     * documented here precisely because getting the order wrong is what
     * would let money move without a corresponding local record, or let
     * a local record exist for money that was never actually moved:
     *
     * 1. Lock the PaymentIntent row (findByIdForUpdate).
     * 2. Guard: status must currently be Captured — anything else
     *    (already Refunded, still Authorized, etc.) is an idempotent
     *    no-op, not an error: a retried caller (the same DisputeResolved
     *    delivered twice, or a genuine retry after a prior failure) must
     *    never attempt, let alone repeat, a refund that already
     *    succeeded.
     * 3. Validate the request via `assertRefundable()` — read-only,
     *    non-mutating — *before* Stripe is ever called. An invalid
     *    request (wrong currency, exceeds the captured total) must never
     *    reach the external API in the first place.
     * 4. Call Stripe, with a deterministic idempotency key derived from
     *    `auctionId` and `disputeId` (stable domain data, per ADR-022) —
     *    never a fresh value per call. This is what makes a retry of this
     *    exact operation safe: Stripe recognizes the same key and returns
     *    the original, already-completed refund instead of creating a
     *    second one.
     * 5. Only *after* Stripe has genuinely accepted the refund does the
     *    aggregate transition — `PaymentIntent::refund()` — and get
     *    persisted. The PaymentIntent must never show `Refunded`,
     *    locally or durably, before step 4 has actually succeeded.
     * 6. Events are collected, not published, inside this closure;
     *    `DomainEventPublisher::publish()` is called only after
     *    `TransactionManager::run()` returns — i.e. strictly after the
     *    surrounding `DB::transaction()` has committed. A listener
     *    reacting to `PaymentRefunded` can never observe it before the
     *    row is durably `Refunded`.
     *
     * Recovery from external-success/local-failure: if Stripe accepts
     * the refund at step 4 but step 5's `save()` (or the transaction's
     * own COMMIT) then fails, the transaction rolls back — the local row
     * remains `Captured`, still disagreeing with Stripe's own, already-
     * completed state. This is deliberately not caught or reconciled
     * inline here (no "RefundFailed" state is introduced, per ADR-022).
     * The safe recovery path is a retry of this exact same call: step 2's
     * guard still sees `Captured` (nothing committed), step 4's gateway
     * call reuses the identical idempotency key (same `auctionId`/
     * `disputeId`), so Stripe returns the original refund rather than
     * creating a second one, and step 5 now succeeds — bringing the
     * local record into agreement with what Stripe already did. Plain
     * exception propagation is only an acceptable posture *because* the
     * idempotency key is deterministic; without it, this would be a real
     * gap. A Stripe-webhook-driven reconciliation (mirroring
     * `StripeCancellationReconciliationService`, ADR-019 §7) would add a
     * second, independent safety net, but is not built this sprint —
     * this recovery path does not depend on it.
     *
     * @throws NotFoundException if no PaymentIntent exists for this auction
     */
    public function refund(string $auctionId, string $disputeId, Money $amount, string $reason): void
    {
        $events = $this->transactions->run(function () use ($auctionId, $disputeId, $amount, $reason): array {
            $paymentIntent = $this->lockByAuctionId($auctionId);

            if ($paymentIntent->status() !== PaymentIntentStatus::Captured) {
                return [];
            }

            $paymentIntent->assertRefundable($amount);

            $idempotencyKey = "payments.dispute_refund.{$auctionId}.{$disputeId}";
            $this->gateway->refund($this->requireStripePaymentIntentId($paymentIntent), $amount, $idempotencyKey);

            $paymentIntent->refund($amount, $reason, $this->clock);
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
