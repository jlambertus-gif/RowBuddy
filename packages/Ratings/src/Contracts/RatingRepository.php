<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Contracts;

use RowBuddy\Ratings\Rating;

/**
 * Domain-facing persistence port. Deliberately expresses no ORM/storage
 * concept — implementations translate between whatever storage
 * technology backs them and the {@see Rating} aggregate, never the
 * reverse.
 *
 * Deliberately exposes no update or delete path — a `Rating` is
 * append-only once submitted, mirroring `BidRepository`'s identical
 * append-only contract (CLAUDE.md: "all accepted bids are immutable";
 * ADR-024 explains why a rating carries the same posture).
 */
interface RatingRepository
{
    public function record(Rating $rating): void;

    public function findById(string $id): ?Rating;

    public function findByTransferAndRater(string $transferId, string $raterId): ?Rating;

    /**
     * Every rating submitted so far for a transfer — at most two, one per
     * participant (ADR-024 §3). A later sprint's reveal evaluator needs
     * both sides at once to determine whether a rating is currently
     * revealed (ADR-024 §5).
     *
     * @return list<Rating>
     */
    public function findByTransferId(string $transferId): array;
}
