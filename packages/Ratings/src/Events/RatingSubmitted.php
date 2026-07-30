<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

/**
 * A single participant's rating of the other party on a transfer
 * (ADR-024 §1/§2/§3) — always against a `Transfer` already `Confirmed` by
 * the time this fires; the eligibility check itself is an
 * application-layer concern (a later sprint), not this event's or this
 * aggregate's job. Whether this rating is currently revealed is a
 * computed read-time fact (ADR-024 §5), never carried on this event.
 */
final class RatingSubmitted extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $ratingId,
        private readonly string $transferId,
        private readonly string $raterId,
        private readonly string $rateeId,
        private readonly int $score,
        private readonly ?string $comment,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'ratings.rating_submitted';
    }

    public function payload(): array
    {
        return [
            'rating_id' => $this->ratingId,
            'transfer_id' => $this->transferId,
            'rater_id' => $this->raterId,
            'ratee_id' => $this->rateeId,
            'score' => $this->score,
            'comment' => $this->comment,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'rating';
    }

    public function auditSubjectId(): string|int
    {
        return $this->ratingId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
