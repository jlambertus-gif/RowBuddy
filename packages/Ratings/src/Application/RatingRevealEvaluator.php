<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Application;

use RowBuddy\Ratings\Contracts\RatingRepository;
use RowBuddy\Ratings\Contracts\RatingRevealDeadlinePolicy;
use RowBuddy\Ratings\Rating;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Computes whether a `Rating` is currently revealed (ADR-024 §5) —
 * deliberately not a field on `Rating` itself (see that aggregate's own
 * docblock): reveal depends on a second aggregate's existence plus a
 * policy, both cross-aggregate/application-layer concerns.
 *
 * ADR-024 §5 describes two paths — "revealed immediately once the
 * counterpart is submitted" and "revealed lazily once the deadline
 * elapses" — but both collapse into this single read-time check by
 * construction: once a counterpart rating is recorded, every subsequent
 * call simply finds it and returns true immediately, with no separate
 * "eager reveal" code path or stored flag needed. There is deliberately
 * no persisted "revealed" column and no scheduler; every call
 * re-evaluates both conditions against the current state and the
 * current clock (ADR-024 §5's explicit "no scheduler" requirement).
 */
final class RatingRevealEvaluator
{
    public function __construct(
        private readonly RatingRepository $ratings,
        private readonly RatingRevealDeadlinePolicy $deadlinePolicy,
        private readonly ClockInterface $clock,
    ) {}

    public function isRevealed(Rating $rating): bool
    {
        if ($this->counterpartFor($rating) !== null) {
            return true;
        }

        $deadlineSeconds = $this->deadlinePolicy->durationInSecondsFor($rating->transferId);
        $deadline = $rating->submittedAt->modify("+{$deadlineSeconds} seconds");

        return $this->clock->now() >= $deadline;
    }

    private function counterpartFor(Rating $rating): ?Rating
    {
        foreach ($this->ratings->findByTransferId($rating->transferId) as $candidate) {
            if ($candidate->raterId !== $rating->raterId) {
                return $candidate;
            }
        }

        return null;
    }
}
