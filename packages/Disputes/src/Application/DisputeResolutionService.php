<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Application;

use RowBuddy\Disputes\Contracts\DisputeRepository;
use RowBuddy\Disputes\Contracts\DomainEventPublisher;
use RowBuddy\Disputes\Contracts\TransactionManager;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Orchestrates an administrator's explicit resolution (ADR-021 §6):
 * locks the `Dispute` row, applies the chosen outcome, persists, and
 * publishes the resulting `DisputeResolved` event. Deliberately limited
 * to validation, domain mutation, persistence, and event publication —
 * it has no knowledge of Payments or refunds.
 *
 * Reacting to the resulting `DisputeResolved` event (and triggering the
 * real Stripe refund when the outcome requires one, ADR-022) is
 * `DisputeRefundTriggerService`'s job, invoked only after this service's
 * transaction has committed and the event has actually been published —
 * never as an inline call here, which would couple an outbound side
 * effect to this service's own aggregate mutation rather than to the
 * committed event. This mirrors the exact correction Transfers'
 * `TransferConfirmationService`/`TransferCaptureTriggerService` split
 * already established in Phase 5.
 */
final class DisputeResolutionService
{
    public function __construct(
        private readonly DisputeRepository $disputes,
        private readonly TransactionManager $transactions,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @throws NotFoundException
     */
    public function resolve(
        string $disputeId,
        DisputeResolutionOutcome $outcome,
        ?Money $refundAmount,
        string $resolvedBy,
        string $resolutionNotes,
        bool $evidenceFoundFraudulent,
    ): void {
        $events = $this->transactions->run(function () use ($disputeId, $outcome, $refundAmount, $resolvedBy, $resolutionNotes, $evidenceFoundFraudulent) {
            $dispute = $this->disputes->findByIdForUpdate($disputeId);

            if ($dispute === null) {
                throw new NotFoundException("Dispute [{$disputeId}] not found.");
            }

            $dispute->resolve($outcome, $refundAmount, $resolvedBy, $resolutionNotes, $evidenceFoundFraudulent, $this->clock);
            $this->disputes->save($dispute);

            return $dispute->releaseEvents();
        });

        foreach ($events as $event) {
            $this->events->publish($event);
        }
    }
}
