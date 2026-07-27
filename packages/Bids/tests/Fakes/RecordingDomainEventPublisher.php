<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Tests\Fakes;

use RowBuddy\Bids\Contracts\DomainEventPublisher;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * Records published events and, given the matching {@see RecordingTransactionManager},
 * throws immediately if publish() is ever called while its transaction is
 * still open — a "trap" that directly proves ADR-012 §2's no-pre-commit-
 * publication requirement, rather than inferring it from timestamps.
 */
final class RecordingDomainEventPublisher implements DomainEventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function __construct(private readonly RecordingTransactionManager $transactions) {}

    public function publish(DomainEvent $event): void
    {
        if ($this->transactions->insideRun) {
            throw new RuntimeException('Event published while the transaction was still open.');
        }

        $this->published[] = $event;
    }
}
