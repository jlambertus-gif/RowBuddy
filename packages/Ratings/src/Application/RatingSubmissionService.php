<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Application;

use RowBuddy\Ratings\Contracts\AccountStandingLookup;
use RowBuddy\Ratings\Contracts\DomainEventPublisher;
use RowBuddy\Ratings\Contracts\RatingRepository;
use RowBuddy\Ratings\Contracts\TransferParticipantLookup;
use RowBuddy\Ratings\Exceptions\InvalidRatingScore;
use RowBuddy\Ratings\Exceptions\RaterAccountSuspended;
use RowBuddy\Ratings\Exceptions\RatingAlreadyExistsForTransferAndRater;
use RowBuddy\Ratings\Exceptions\RatingCommentTooLong;
use RowBuddy\Ratings\Exceptions\RatingSubmissionNotAuthorized;
use RowBuddy\Ratings\Exceptions\TransferNotEligibleForRating;
use RowBuddy\Ratings\Rating;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Orchestrates a participant's rating submission (ADR-024 §1-§3): only
 * against a `Confirmed` transfer, only by that transfer's own buyer or
 * seller, always of the *other* party — this service derives `rateeId`
 * itself rather than trusting a caller-supplied value, so a rater can
 * never name an arbitrary third party as the ratee. A rejected attempt
 * never creates a `Rating`, mirroring `DisputeFilingService`'s "a
 * rejected attempt leaves no trace" discipline.
 *
 * The application-layer existence check below and the database's own
 * unique constraint on `(transfer_id, rater_id)` (Sprint 2) are a
 * belt-and-suspenders pair, the same posture `DisputeFilingService`
 * takes for `disputes.transfer_id`: the check rejects the common case
 * cheaply, the constraint is the true concurrency-safety net for a race
 * between two concurrent submission attempts.
 *
 * No `TransactionManager`/row lock is used, for the same reason
 * `DisputeFilingService` needs none: this only ever inserts a new
 * `Rating` row.
 */
final class RatingSubmissionService
{
    public function __construct(
        private readonly RatingRepository $ratings,
        private readonly TransferParticipantLookup $transferParticipants,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
        private readonly AccountStandingLookup $accountStanding,
    ) {}

    /**
     * @throws RaterAccountSuspended
     * @throws TransferNotEligibleForRating
     * @throws RatingSubmissionNotAuthorized
     * @throws RatingAlreadyExistsForTransferAndRater
     * @throws InvalidRatingScore
     * @throws RatingCommentTooLong
     */
    public function submit(string $ratingId, string $transferId, string $raterId, int $score, ?string $comment): Rating
    {
        if ($this->accountStanding->isSuspended($raterId)) {
            throw RaterAccountSuspended::forRater($raterId);
        }

        $snapshot = $this->transferParticipants->findByTransferId($transferId);

        if ($snapshot === null || ! $snapshot->isConfirmed) {
            throw TransferNotEligibleForRating::forTransferId($transferId);
        }

        if ($raterId !== $snapshot->buyerId && $raterId !== $snapshot->sellerId) {
            throw RatingSubmissionNotAuthorized::forTransferId($transferId);
        }

        if ($this->ratings->findByTransferAndRater($transferId, $raterId) !== null) {
            throw RatingAlreadyExistsForTransferAndRater::forTransferAndRater($transferId, $raterId);
        }

        $rateeId = $raterId === $snapshot->buyerId ? $snapshot->sellerId : $snapshot->buyerId;

        $rating = Rating::submit($ratingId, $transferId, $raterId, $rateeId, $score, $comment, $this->clock);
        $this->ratings->record($rating);

        foreach ($rating->releaseEvents() as $event) {
            $this->events->publish($event);
        }

        return $rating;
    }
}
