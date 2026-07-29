<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Application;

use RowBuddy\Disputes\Contracts\DisputeFilingDeadlinePolicy;
use RowBuddy\Disputes\Contracts\DisputeRepository;
use RowBuddy\Disputes\Contracts\DomainEventPublisher;
use RowBuddy\Disputes\Contracts\TransferCaseLookup;
use RowBuddy\Disputes\Dispute;
use RowBuddy\Disputes\Exceptions\DisputeAlreadyExistsForTransfer;
use RowBuddy\Disputes\Exceptions\DisputeFilingNotAuthorized;
use RowBuddy\Disputes\Exceptions\DisputeFilingWindowElapsed;
use RowBuddy\Disputes\Exceptions\TransferNotEligibleForDispute;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Orchestrates the buyer's filing (ADR-021 §1-§3): only the `Transfer`'s
 * own buyer may file, only against a `Confirmed` transfer, only within
 * the filing deadline computed from `confirmedAt`. A rejected attempt
 * never creates a `Dispute`, mirroring `TransferConfirmationService`'s
 * "a rejected attempt leaves no trace" discipline.
 *
 * Unlike `TransferInitiationService`'s idempotent-replay-returns-existing
 * shape (appropriate for a system-triggered reactor that might see the
 * same event twice), a second filing attempt here is a hard rejection —
 * disputes are a deliberate, user-initiated action, not an idempotent
 * side effect of a domain event that might redeliver.
 *
 * No `TransactionManager`/row lock is used: this only ever inserts a new
 * `Dispute` row, and the database's own unique constraint on
 * `transfer_id` is the true concurrency-safety net, the same posture
 * `TransferInitiationService` takes for issuing a new `Transfer`.
 */
final class DisputeFilingService
{
    public function __construct(
        private readonly DisputeRepository $disputes,
        private readonly TransferCaseLookup $transferCases,
        private readonly DisputeFilingDeadlinePolicy $deadlinePolicy,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @throws DisputeAlreadyExistsForTransfer
     * @throws TransferNotEligibleForDispute
     * @throws DisputeFilingNotAuthorized
     * @throws DisputeFilingWindowElapsed
     */
    public function file(string $disputeId, string $transferId, string $filingBuyerId, string $reason): Dispute
    {
        if ($this->disputes->findByTransferId($transferId) !== null) {
            throw DisputeAlreadyExistsForTransfer::forTransferId($transferId);
        }

        $snapshot = $this->transferCases->findByTransferId($transferId);

        if ($snapshot === null || ! $snapshot->isConfirmed || $snapshot->confirmedAt === null) {
            throw TransferNotEligibleForDispute::forTransferId($transferId);
        }

        if ($snapshot->buyerId !== $filingBuyerId) {
            throw DisputeFilingNotAuthorized::forTransferId($transferId);
        }

        $deadlineSeconds = $this->deadlinePolicy->durationInSecondsFor($transferId);
        $deadline = $snapshot->confirmedAt->modify("+{$deadlineSeconds} seconds");

        if ($this->clock->now() > $deadline) {
            throw DisputeFilingWindowElapsed::forTransferId($transferId);
        }

        $dispute = Dispute::open($disputeId, $transferId, $snapshot->auctionId, $filingBuyerId, $snapshot->sellerId, $reason, $this->clock);
        $this->disputes->save($dispute);

        foreach ($dispute->releaseEvents() as $event) {
            $this->events->publish($event);
        }

        return $dispute;
    }
}
