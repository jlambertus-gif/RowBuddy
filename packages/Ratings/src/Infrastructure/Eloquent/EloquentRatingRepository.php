<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Infrastructure\Eloquent;

use Illuminate\Database\UniqueConstraintViolationException;
use RowBuddy\Ratings\Contracts\RatingRepository;
use RowBuddy\Ratings\Exceptions\RatingAlreadyExistsForTransferAndRater;
use RowBuddy\Ratings\Rating;

/**
 * Translates between the {@see RatingModel} Eloquent record and the
 * {@see Rating} domain aggregate. Deliberately has no update/delete
 * method — only `record()` (insert) and reads, mirroring
 * {@see RatingRepository}'s own append-only contract.
 */
final class EloquentRatingRepository implements RatingRepository
{
    public function record(Rating $rating): void
    {
        try {
            RatingModel::query()->create([
                'id' => $rating->id,
                'transfer_id' => $rating->transferId,
                'rater_id' => $rating->raterId,
                'ratee_id' => $rating->rateeId,
                'score' => $rating->score->value,
                'comment' => $rating->comment,
                'submitted_at' => $rating->submittedAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw RatingAlreadyExistsForTransferAndRater::forTransferAndRater($rating->transferId, $rating->raterId);
        }
    }

    public function findById(string $id): ?Rating
    {
        /** @var RatingModel|null $model */
        $model = RatingModel::query()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByTransferAndRater(string $transferId, string $raterId): ?Rating
    {
        /** @var RatingModel|null $model */
        $model = RatingModel::query()
            ->where('transfer_id', $transferId)
            ->where('rater_id', $raterId)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByTransferId(string $transferId): array
    {
        /** @var iterable<RatingModel> $models */
        $models = RatingModel::query()
            ->where('transfer_id', $transferId)
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $ratings = [];

        foreach ($models as $model) {
            $ratings[] = $this->toDomain($model);
        }

        return $ratings;
    }

    private function toDomain(RatingModel $model): Rating
    {
        return Rating::fromPersistence(
            id: $model->id,
            transferId: $model->transfer_id,
            raterId: (string) $model->rater_id,
            rateeId: (string) $model->ratee_id,
            score: $model->score,
            comment: $model->comment,
            submittedAt: $model->submitted_at->toDateTimeImmutable(),
        );
    }
}
