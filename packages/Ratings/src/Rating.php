<?php

declare(strict_types=1);

namespace RowBuddy\Ratings;

use DateTimeImmutable;
use RowBuddy\Ratings\Events\RatingSubmitted;
use RowBuddy\Ratings\Exceptions\InvalidRatingScore;
use RowBuddy\Ratings\Exceptions\RatingCommentTooLong;
use RowBuddy\Ratings\ValueObjects\RatingScore;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * A single participant's rating of the other party on a `Transfer`
 * (Phase 7, ADR-024). Symmetric by design (ADR-024 §1): a buyer and a
 * seller each submit their own `Rating` of the other, so this aggregate
 * represents exactly one submission, keyed by `(transferId, raterId)`
 * (the uniqueness constraint ADR-024 §3/Consequences requires),
 * never a pair.
 *
 * Unlike `Dispute`, `Rating` has no state machine — it is immutable once
 * submitted; there is no `resolve()`, no status transition, nothing to
 * guard. Whether this rating is currently *revealed* to its counterparty
 * (ADR-024 §5) is deliberately not a field on this aggregate: reveal is a
 * computable read-time fact depending on whether the counterpart rating
 * exists and on `RatingRevealDeadlinePolicy`, both of which are
 * cross-aggregate/application-layer concerns introduced in a later
 * sprint, not something a single `Rating` can determine about itself.
 *
 * `transferId`, `raterId`, and `rateeId` arrive here as already-decided
 * facts the caller resolved (including the `Confirmed`-only eligibility
 * gate, ADR-024 §2, and the one-per-participant-per-transfer check,
 * ADR-024 §3) — the same discipline `Dispute` uses for facts sourced from
 * `Transfer`.
 */
final class Rating
{
    /**
     * The exact bound ADR-024 §6 requires be explicit and covered by
     * tests. Chosen for Sprint 1: long enough for genuine handoff
     * feedback, short enough to keep a notification/display render
     * bounded.
     */
    public const MAX_COMMENT_LENGTH = 1000;

    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly string $id,
        public readonly string $transferId,
        public readonly string $raterId,
        public readonly string $rateeId,
        public readonly RatingScore $score,
        public readonly ?string $comment,
        public readonly DateTimeImmutable $submittedAt,
    ) {}

    /**
     * @throws InvalidRatingScore
     * @throws RatingCommentTooLong
     */
    public static function submit(
        string $id,
        string $transferId,
        string $raterId,
        string $rateeId,
        int $score,
        ?string $comment,
        ClockInterface $clock,
    ): self {
        $ratingScore = new RatingScore($score);
        $normalizedComment = self::normalizeComment($comment);

        $rating = new self(
            id: $id,
            transferId: $transferId,
            raterId: $raterId,
            rateeId: $rateeId,
            score: $ratingScore,
            comment: $normalizedComment,
            submittedAt: $clock->now(),
        );

        $rating->recordedEvents[] = new RatingSubmitted(
            $clock,
            $id,
            $transferId,
            $raterId,
            $rateeId,
            $ratingScore->value,
            $normalizedComment,
        );

        return $rating;
    }

    /**
     * Reconstitutes a Rating from previously persisted state. Unlike
     * submit() above, this never raises domain events — loading a
     * Rating back out of storage is not a business event in itself.
     * Persisted values are assumed already valid; only the score's own
     * range invariant is re-checked, mirroring `RatingScore`'s
     * constructor guard.
     */
    public static function fromPersistence(
        string $id,
        string $transferId,
        string $raterId,
        string $rateeId,
        int $score,
        ?string $comment,
        DateTimeImmutable $submittedAt,
    ): self {
        return new self(
            id: $id,
            transferId: $transferId,
            raterId: $raterId,
            rateeId: $rateeId,
            score: new RatingScore($score),
            comment: $comment,
            submittedAt: $submittedAt,
        );
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /**
     * Blank or whitespace-only input normalizes to `null` (ADR-024 §6) —
     * there is no distinction in this system between "no comment" and
     * "an empty one." Non-blank input is stored exactly as submitted,
     * with no trimming, per this project's standing rule that
     * user-generated content is stored exactly as its author wrote it.
     *
     * @throws RatingCommentTooLong
     */
    private static function normalizeComment(?string $comment): ?string
    {
        if ($comment === null || trim($comment) === '') {
            return null;
        }

        $length = mb_strlen($comment);

        if ($length > self::MAX_COMMENT_LENGTH) {
            throw RatingCommentTooLong::forLength($length, self::MAX_COMMENT_LENGTH);
        }

        return $comment;
    }
}
