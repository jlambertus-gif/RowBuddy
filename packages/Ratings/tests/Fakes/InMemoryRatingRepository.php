<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Tests\Fakes;

use RowBuddy\Ratings\Contracts\RatingRepository;
use RowBuddy\Ratings\Exceptions\RatingAlreadyExistsForTransferAndRater;
use RowBuddy\Ratings\Rating;

final class InMemoryRatingRepository implements RatingRepository
{
    /** @var array<string, Rating> */
    public array $recorded = [];

    public function record(Rating $rating): void
    {
        if ($this->findByTransferAndRater($rating->transferId, $rating->raterId) !== null) {
            throw RatingAlreadyExistsForTransferAndRater::forTransferAndRater($rating->transferId, $rating->raterId);
        }

        $this->recorded[$rating->id] = $rating;
    }

    public function findById(string $id): ?Rating
    {
        return $this->recorded[$id] ?? null;
    }

    public function findByTransferAndRater(string $transferId, string $raterId): ?Rating
    {
        foreach ($this->recorded as $rating) {
            if ($rating->transferId === $transferId && $rating->raterId === $raterId) {
                return $rating;
            }
        }

        return null;
    }

    public function findByTransferId(string $transferId): array
    {
        return array_values(array_filter(
            $this->recorded,
            fn (Rating $rating): bool => $rating->transferId === $transferId,
        ));
    }
}
