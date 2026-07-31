<?php

declare(strict_types=1);

use RowBuddy\Ratings\Application\RatingSubmissionService;
use RowBuddy\Ratings\Events\RatingSubmitted;
use RowBuddy\Ratings\Exceptions\RaterAccountSuspended;
use RowBuddy\Ratings\Exceptions\RatingAlreadyExistsForTransferAndRater;
use RowBuddy\Ratings\Exceptions\RatingSubmissionNotAuthorized;
use RowBuddy\Ratings\Exceptions\TransferNotEligibleForRating;
use RowBuddy\Ratings\Tests\Fakes\FakeAccountStandingLookup;
use RowBuddy\Ratings\Tests\Fakes\FakeTransferParticipantLookup;
use RowBuddy\Ratings\Tests\Fakes\InMemoryRatingRepository;
use RowBuddy\Ratings\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Ratings\ValueObjects\TransferParticipantSnapshot;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('submits a rating for the buyer against a Confirmed transfer, deriving the seller as ratee', function () {
    $ratings = new InMemoryRatingRepository;
    $transferParticipants = new FakeTransferParticipantLookup;
    $transferParticipants->snapshots['transfer-1'] = new TransferParticipantSnapshot('transfer-1', '101', '102', true);
    $events = new RecordingDomainEventPublisher;
    $service = new RatingSubmissionService($ratings, $transferParticipants, $events, new FrozenClock, new FakeAccountStandingLookup);

    $rating = $service->submit('rating-1', 'transfer-1', '101', 5, 'Great handoff.');

    expect($rating->id)->toBe('rating-1')
        ->and($rating->raterId)->toBe('101')
        ->and($rating->rateeId)->toBe('102')
        ->and($ratings->findById('rating-1'))->not->toBeNull()
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(RatingSubmitted::class);
});

it('submits a rating for the seller against a Confirmed transfer, deriving the buyer as ratee', function () {
    $transferParticipants = new FakeTransferParticipantLookup;
    $transferParticipants->snapshots['transfer-1'] = new TransferParticipantSnapshot('transfer-1', '101', '102', true);
    $service = new RatingSubmissionService(new InMemoryRatingRepository, $transferParticipants, new RecordingDomainEventPublisher, new FrozenClock, new FakeAccountStandingLookup);

    $rating = $service->submit('rating-1', 'transfer-1', '102', 4, null);

    expect($rating->raterId)->toBe('102')
        ->and($rating->rateeId)->toBe('101');
});

it('allows both participants to independently rate the same transfer', function () {
    $ratings = new InMemoryRatingRepository;
    $transferParticipants = new FakeTransferParticipantLookup;
    $transferParticipants->snapshots['transfer-1'] = new TransferParticipantSnapshot('transfer-1', '101', '102', true);
    $service = new RatingSubmissionService($ratings, $transferParticipants, new RecordingDomainEventPublisher, new FrozenClock, new FakeAccountStandingLookup);

    $service->submit('rating-1', 'transfer-1', '101', 5, null);
    $service->submit('rating-2', 'transfer-1', '102', 3, null);

    expect($ratings->findByTransferId('transfer-1'))->toHaveCount(2);
});

it('rejects submission when the transfer does not exist', function () {
    $service = new RatingSubmissionService(
        new InMemoryRatingRepository,
        new FakeTransferParticipantLookup,
        new RecordingDomainEventPublisher,
        new FrozenClock,
        new FakeAccountStandingLookup,
    );

    expect(fn () => $service->submit('rating-1', 'transfer-missing', '101', 5, null))
        ->toThrow(TransferNotEligibleForRating::class);
});

it('rejects submission when the transfer is not Confirmed', function () {
    $transferParticipants = new FakeTransferParticipantLookup;
    $transferParticipants->snapshots['transfer-1'] = new TransferParticipantSnapshot('transfer-1', '101', '102', false);
    $service = new RatingSubmissionService(
        new InMemoryRatingRepository,
        $transferParticipants,
        new RecordingDomainEventPublisher,
        new FrozenClock,
        new FakeAccountStandingLookup,
    );

    expect(fn () => $service->submit('rating-1', 'transfer-1', '101', 5, null))
        ->toThrow(TransferNotEligibleForRating::class);
});

it('rejects submission by anyone other than the transfer\'s own buyer or seller', function () {
    $transferParticipants = new FakeTransferParticipantLookup;
    $transferParticipants->snapshots['transfer-1'] = new TransferParticipantSnapshot('transfer-1', '101', '102', true);
    $service = new RatingSubmissionService(
        new InMemoryRatingRepository,
        $transferParticipants,
        new RecordingDomainEventPublisher,
        new FrozenClock,
        new FakeAccountStandingLookup,
    );

    expect(fn () => $service->submit('rating-1', 'transfer-1', '999', 5, null))
        ->toThrow(RatingSubmissionNotAuthorized::class);
});

it('rejects a second submission from the same rater for the same transfer', function () {
    $ratings = new InMemoryRatingRepository;
    $transferParticipants = new FakeTransferParticipantLookup;
    $transferParticipants->snapshots['transfer-1'] = new TransferParticipantSnapshot('transfer-1', '101', '102', true);
    $service = new RatingSubmissionService($ratings, $transferParticipants, new RecordingDomainEventPublisher, new FrozenClock, new FakeAccountStandingLookup);

    $service->submit('rating-1', 'transfer-1', '101', 5, null);

    expect(fn () => $service->submit('rating-2', 'transfer-1', '101', 2, null))
        ->toThrow(RatingAlreadyExistsForTransferAndRater::class);
});

// --- Account standing (ADR-026 §4) ---

it('allows an active rater to submit a rating', function () {
    $transferParticipants = new FakeTransferParticipantLookup;
    $transferParticipants->snapshots['transfer-1'] = new TransferParticipantSnapshot('transfer-1', '101', '102', true);
    $accountStanding = new FakeAccountStandingLookup;
    $service = new RatingSubmissionService(new InMemoryRatingRepository, $transferParticipants, new RecordingDomainEventPublisher, new FrozenClock, $accountStanding);

    $rating = $service->submit('rating-1', 'transfer-1', '101', 5, null);

    expect($rating->id)->toBe('rating-1');
});

it('rejects submission from a suspended rater before any domain mutation or event publication', function () {
    $ratings = new InMemoryRatingRepository;
    $transferParticipants = new FakeTransferParticipantLookup;
    $transferParticipants->snapshots['transfer-1'] = new TransferParticipantSnapshot('transfer-1', '101', '102', true);
    $events = new RecordingDomainEventPublisher;
    $accountStanding = new FakeAccountStandingLookup;
    $accountStanding->suspended['101'] = true;
    $service = new RatingSubmissionService($ratings, $transferParticipants, $events, new FrozenClock, $accountStanding);

    expect(fn () => $service->submit('rating-1', 'transfer-1', '101', 5, null))
        ->toThrow(RaterAccountSuspended::class);

    expect($ratings->findById('rating-1'))->toBeNull()
        ->and($events->published)->toBe([]);
});
