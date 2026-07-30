<?php

declare(strict_types=1);

use RowBuddy\Ratings\Application\FixedRatingRevealDeadlinePolicy;
use RowBuddy\Ratings\Application\RatingRevealEvaluator;
use RowBuddy\Ratings\Rating;
use RowBuddy\Ratings\Tests\Fakes\InMemoryRatingRepository;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('is not revealed when no counterpart exists and the deadline has not elapsed', function () {
    $ratings = new InMemoryRatingRepository;
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 5, null, new FrozenClock(new DateTimeImmutable('2026-08-01 10:00:00')));
    $ratings->record($rating);

    $evaluator = new RatingRevealEvaluator(
        $ratings,
        new FixedRatingRevealDeadlinePolicy(7 * 86400),
        new FrozenClock(new DateTimeImmutable('2026-08-03 10:00:00')),
    );

    expect($evaluator->isRevealed($rating))->toBeFalse();
});

it('is revealed immediately once the counterpart rating exists, regardless of the deadline', function () {
    $ratings = new InMemoryRatingRepository;
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 5, null, new FrozenClock(new DateTimeImmutable('2026-08-01 10:00:00')));
    $ratings->record($rating);
    $ratings->record(Rating::submit('rating-2', 'transfer-1', '102', '101', 4, null, new FrozenClock(new DateTimeImmutable('2026-08-01 10:05:00'))));

    $evaluator = new RatingRevealEvaluator(
        $ratings,
        new FixedRatingRevealDeadlinePolicy(7 * 86400),
        new FrozenClock(new DateTimeImmutable('2026-08-01 10:06:00')),
    );

    expect($evaluator->isRevealed($rating))->toBeTrue();
});

it('is revealed once its own deadline has elapsed, even with no counterpart', function () {
    $ratings = new InMemoryRatingRepository;
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 5, null, new FrozenClock(new DateTimeImmutable('2026-08-01 10:00:00')));
    $ratings->record($rating);

    $evaluator = new RatingRevealEvaluator(
        $ratings,
        new FixedRatingRevealDeadlinePolicy(7 * 86400),
        new FrozenClock(new DateTimeImmutable('2026-08-08 10:00:01')),
    );

    expect($evaluator->isRevealed($rating))->toBeTrue();
});

it('is revealed exactly at the deadline boundary', function () {
    $ratings = new InMemoryRatingRepository;
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 5, null, new FrozenClock(new DateTimeImmutable('2026-08-01 10:00:00')));
    $ratings->record($rating);

    $evaluator = new RatingRevealEvaluator(
        $ratings,
        new FixedRatingRevealDeadlinePolicy(7 * 86400),
        new FrozenClock(new DateTimeImmutable('2026-08-08 10:00:00')),
    );

    expect($evaluator->isRevealed($rating))->toBeTrue();
});

it('is not revealed one second before the deadline boundary', function () {
    $ratings = new InMemoryRatingRepository;
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 5, null, new FrozenClock(new DateTimeImmutable('2026-08-01 10:00:00')));
    $ratings->record($rating);

    $evaluator = new RatingRevealEvaluator(
        $ratings,
        new FixedRatingRevealDeadlinePolicy(7 * 86400),
        new FrozenClock(new DateTimeImmutable('2026-08-08 09:59:59')),
    );

    expect($evaluator->isRevealed($rating))->toBeFalse();
});

it('anchors each rating\'s own deadline to its own submittedAt, not to any shared transfer timestamp', function () {
    $ratings = new InMemoryRatingRepository;
    $early = Rating::submit('rating-1', 'transfer-1', '101', '102', 5, null, new FrozenClock(new DateTimeImmutable('2026-08-01 10:00:00')));
    $late = Rating::submit('rating-2', 'transfer-1', '102', '101', 4, null, new FrozenClock(new DateTimeImmutable('2026-08-20 10:00:00')));
    $ratings->record($early);
    $ratings->record($late);

    // Both are revealed the instant the second exists, regardless of
    // how far apart their own submission times were.
    $evaluator = new RatingRevealEvaluator(
        $ratings,
        new FixedRatingRevealDeadlinePolicy(7 * 86400),
        new FrozenClock(new DateTimeImmutable('2026-08-20 10:00:01')),
    );

    expect($evaluator->isRevealed($early))->toBeTrue()
        ->and($evaluator->isRevealed($late))->toBeTrue();
});

it('reveals a lone rating on its own schedule even when submitted long after confirmation', function () {
    $ratings = new InMemoryRatingRepository;
    // Submitted 60 days after some hypothetical confirmation — still
    // gets its own full 7-day blind window from its own submittedAt.
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 5, null, new FrozenClock(new DateTimeImmutable('2026-09-30 10:00:00')));
    $ratings->record($rating);

    $notYet = new RatingRevealEvaluator(
        $ratings,
        new FixedRatingRevealDeadlinePolicy(7 * 86400),
        new FrozenClock(new DateTimeImmutable('2026-10-05 10:00:00')),
    );
    $afterWindow = new RatingRevealEvaluator(
        $ratings,
        new FixedRatingRevealDeadlinePolicy(7 * 86400),
        new FrozenClock(new DateTimeImmutable('2026-10-07 10:00:01')),
    );

    expect($notYet->isRevealed($rating))->toBeFalse()
        ->and($afterWindow->isRevealed($rating))->toBeTrue();
});
