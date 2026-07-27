<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Infrastructure\Eloquent;

use Illuminate\Database\UniqueConstraintViolationException;
use RowBuddy\QueuePresence\Contracts\PresenceSessionRepository;
use RowBuddy\QueuePresence\Exceptions\DuplicateActivePresenceSession;
use RowBuddy\QueuePresence\PresenceSession;
use RowBuddy\QueuePresence\ValueObjects\PresenceSessionStatus;

/**
 * Translates between the {@see PresenceSessionModel} Eloquent record and
 * the {@see PresenceSession} domain aggregate. This is the only place in
 * the QueuePresence module allowed to know both shapes at once.
 */
final class EloquentPresenceSessionRepository implements PresenceSessionRepository
{
    public function save(PresenceSession $session): void
    {
        try {
            PresenceSessionModel::query()->updateOrCreate(
                ['id' => $session->id],
                [
                    'queue_id' => $session->queueId,
                    'seller_id' => $session->sellerId,
                    'started_at' => $session->startedAt,
                    'ended_at' => $session->endedAt(),
                    'status' => $session->status()->value,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw DuplicateActivePresenceSession::forSellerAndQueue($session->sellerId, $session->queueId);
        }
    }

    public function findById(string $id): ?PresenceSession
    {
        $model = PresenceSessionModel::query()->find($id);

        if (! $model instanceof PresenceSessionModel) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findLatestBySellerAndQueue(string $sellerId, string $queueId): ?PresenceSession
    {
        /** @var PresenceSessionModel|null $model */
        $model = PresenceSessionModel::query()
            ->where('seller_id', $sellerId)
            ->where('queue_id', $queueId)
            ->orderByDesc('started_at')
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    private function toDomain(PresenceSessionModel $model): PresenceSession
    {
        return PresenceSession::fromPersistence(
            id: $model->id,
            queueId: $model->queue_id,
            sellerId: (string) $model->seller_id,
            startedAt: $model->started_at->toDateTimeImmutable(),
            status: PresenceSessionStatus::from($model->status),
            endedAt: $model->ended_at?->toDateTimeImmutable(),
        );
    }
}
