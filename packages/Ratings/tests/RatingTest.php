<?php

declare(strict_types=1);

use RowBuddy\Ratings\Events\RatingSubmitted;
use RowBuddy\Ratings\Exceptions\InvalidRatingScore;
use RowBuddy\Ratings\Exceptions\RatingCommentTooLong;
use RowBuddy\Ratings\Rating;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('submits a rating and raises a RatingSubmitted event', function () {
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 5, 'Great, professional handoff.', new FrozenClock);

    expect($rating->id)->toBe('rating-1')
        ->and($rating->transferId)->toBe('transfer-1')
        ->and($rating->raterId)->toBe('101')
        ->and($rating->rateeId)->toBe('102')
        ->and($rating->score->value)->toBe(5)
        ->and($rating->comment)->toBe('Great, professional handoff.');

    $events = $rating->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(RatingSubmitted::class);
});

it('allows either the buyer or the seller to be the rater', function () {
    $buyerRating = Rating::submit('rating-1', 'transfer-1', 'buyer-1', 'seller-1', 4, null, new FrozenClock);
    $sellerRating = Rating::submit('rating-2', 'transfer-1', 'seller-1', 'buyer-1', 5, null, new FrozenClock);

    expect($buyerRating->raterId)->toBe('buyer-1')
        ->and($buyerRating->rateeId)->toBe('seller-1')
        ->and($sellerRating->raterId)->toBe('seller-1')
        ->and($sellerRating->rateeId)->toBe('buyer-1');
});

it('accepts a comment-less rating', function () {
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 3, null, new FrozenClock);

    expect($rating->comment)->toBeNull();
});

it('normalizes a blank comment to null', function () {
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 3, '', new FrozenClock);

    expect($rating->comment)->toBeNull();
});

it('normalizes a whitespace-only comment to null', function () {
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 3, "  \t\n  ", new FrozenClock);

    expect($rating->comment)->toBeNull();
});

it('stores a non-blank comment exactly as submitted, without trimming', function () {
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 3, '  Good handoff.  ', new FrozenClock);

    expect($rating->comment)->toBe('  Good handoff.  ');
});

it('accepts a comment exactly at the maximum length', function () {
    $comment = str_repeat('a', Rating::MAX_COMMENT_LENGTH);

    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 3, $comment, new FrozenClock);

    expect($rating->comment)->toBe($comment);
});

it('rejects a comment one character over the maximum length', function () {
    $comment = str_repeat('a', Rating::MAX_COMMENT_LENGTH + 1);

    expect(fn () => Rating::submit('rating-1', 'transfer-1', '101', '102', 3, $comment, new FrozenClock))
        ->toThrow(RatingCommentTooLong::class);
});

it('accepts every score in the valid range', function (int $score) {
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', $score, null, new FrozenClock);

    expect($rating->score->value)->toBe($score);
})->with([1, 2, 3, 4, 5]);

it('rejects a score below the minimum', function () {
    expect(fn () => Rating::submit('rating-1', 'transfer-1', '101', '102', 0, null, new FrozenClock))
        ->toThrow(InvalidRatingScore::class);
});

it('rejects a score above the maximum', function () {
    expect(fn () => Rating::submit('rating-1', 'transfer-1', '101', '102', 6, null, new FrozenClock))
        ->toThrow(InvalidRatingScore::class);
});

it('releasing events clears them so they are not dispatched twice', function () {
    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 4, null, new FrozenClock);
    $rating->releaseEvents();

    expect($rating->releaseEvents())->toBe([]);
});

it('reconstitutes from persistence without raising any events', function () {
    $rating = Rating::fromPersistence(
        id: 'rating-1',
        transferId: 'transfer-1',
        raterId: '101',
        rateeId: '102',
        score: 5,
        comment: 'Great handoff.',
        submittedAt: new DateTimeImmutable('2026-08-01 10:00:00'),
    );

    expect($rating->score->value)->toBe(5)
        ->and($rating->comment)->toBe('Great handoff.')
        ->and($rating->releaseEvents())->toBe([]);
});
