<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Infrastructure\Eloquent;

use RowBuddy\QueuePresence\Contracts\ConfidenceScoreRepository;
use RowBuddy\QueuePresence\ValueObjects\ConfidenceScoreRecord;
use RowBuddy\QueuePresence\ValueObjects\ConfidenceTier;

final class EloquentConfidenceScoreRepository implements ConfidenceScoreRepository
{
    public function record(ConfidenceScoreRecord $score): void
    {
        ConfidenceScoreModel::query()->create([
            'id' => $score->id,
            'presence_session_id' => $score->presenceSessionId,
            'points' => $score->points,
            'tier' => $score->tier->value,
            'computed_at' => $score->computedAt,
        ]);
    }

    public function latestFor(string $presenceSessionId): ?ConfidenceScoreRecord
    {
        /** @var ConfidenceScoreModel|null $model */
        $model = ConfidenceScoreModel::query()
            ->where('presence_session_id', $presenceSessionId)
            ->orderByDesc('computed_at')
            ->first();

        if (! $model instanceof ConfidenceScoreModel) {
            return null;
        }

        return new ConfidenceScoreRecord(
            $model->id,
            $model->presence_session_id,
            $model->points,
            ConfidenceTier::from($model->tier),
            $model->computed_at->toDateTimeImmutable(),
        );
    }
}
