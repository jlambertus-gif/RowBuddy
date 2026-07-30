<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Ratings\Exceptions\RatingAlreadyExistsForTransferAndRater;
use RowBuddy\Ratings\Infrastructure\Eloquent\EloquentRatingRepository;
use RowBuddy\Ratings\Rating;
use RowBuddy\SharedKernel\Support\FrozenClock;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('ratings', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('transfer_id');
        $table->unsignedBigInteger('rater_id');
        $table->unsignedBigInteger('ratee_id');
        $table->unsignedTinyInteger('score');
        $table->text('comment')->nullable();
        $table->timestamp('submitted_at');
        $table->timestamps();

        $table->unique(['transfer_id', 'rater_id']);
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('ratings');
});

it('records and finds a rating by id, round-tripping every field', function () {
    $repository = new EloquentRatingRepository;
    $submittedAt = new DateTimeImmutable('2026-08-05 10:00:00');

    $rating = Rating::submit('rating-1', 'transfer-1', '101', '102', 5, 'Great handoff.', new FrozenClock($submittedAt));
    $repository->record($rating);

    $found = $repository->findById('rating-1');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe('rating-1')
        ->and($found->transferId)->toBe('transfer-1')
        ->and($found->raterId)->toBe('101')
        ->and($found->rateeId)->toBe('102')
        ->and($found->score->value)->toBe(5)
        ->and($found->comment)->toBe('Great handoff.')
        ->and($found->submittedAt)->toEqual($submittedAt);
});

it('round-trips a rating with no comment', function () {
    $repository = new EloquentRatingRepository;
    $repository->record(Rating::submit('rating-1', 'transfer-1', '101', '102', 3, null, new FrozenClock));

    $found = $repository->findById('rating-1');

    expect($found->comment)->toBeNull();
});

it('returns null when the rating does not exist', function () {
    expect((new EloquentRatingRepository)->findById('missing'))->toBeNull();
});

it('finds a rating by transfer and rater', function () {
    $repository = new EloquentRatingRepository;
    $repository->record(Rating::submit('rating-1', 'transfer-1', '101', '102', 4, null, new FrozenClock));

    $found = $repository->findByTransferAndRater('transfer-1', '101');

    expect($found)->not->toBeNull()->and($found->id)->toBe('rating-1');
});

it('returns null for findByTransferAndRater when no such rating exists', function () {
    expect((new EloquentRatingRepository)->findByTransferAndRater('transfer-1', '101'))->toBeNull();
});

it('rejects a second rating from the same rater for the same transfer', function () {
    $repository = new EloquentRatingRepository;
    $repository->record(Rating::submit('rating-1', 'transfer-1', '101', '102', 4, null, new FrozenClock));

    $second = Rating::submit('rating-2', 'transfer-1', '101', '102', 2, null, new FrozenClock);

    expect(fn () => $repository->record($second))->toThrow(RatingAlreadyExistsForTransferAndRater::class);
});

it('allows both participants to rate the same transfer independently', function () {
    $repository = new EloquentRatingRepository;
    $repository->record(Rating::submit('rating-1', 'transfer-1', '101', '102', 5, null, new FrozenClock));
    $repository->record(Rating::submit('rating-2', 'transfer-1', '102', '101', 4, null, new FrozenClock));

    $ratings = $repository->findByTransferId('transfer-1');

    expect($ratings)->toHaveCount(2);
});

it('returns both ratings for a transfer in submission order', function () {
    $repository = new EloquentRatingRepository;
    $repository->record(Rating::submit('rating-1', 'transfer-1', '101', '102', 5, null, new FrozenClock(new DateTimeImmutable('2026-08-05 10:00:00'))));
    $repository->record(Rating::submit('rating-2', 'transfer-1', '102', '101', 4, null, new FrozenClock(new DateTimeImmutable('2026-08-05 11:00:00'))));

    $ratings = $repository->findByTransferId('transfer-1');

    expect($ratings[0]->id)->toBe('rating-1')
        ->and($ratings[1]->id)->toBe('rating-2');
});

it('returns an empty list for findByTransferId when no ratings exist', function () {
    expect((new EloquentRatingRepository)->findByTransferId('transfer-missing'))->toBe([]);
});
