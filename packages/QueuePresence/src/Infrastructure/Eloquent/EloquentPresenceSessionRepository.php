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

        return PresenceSession::fromPersistence(
            id: $model->id,
            queueId: $model->queue_id,
            sellerId: (string) $model->seller_id,
            startedAt: $model->started_at->toDateTimeImmutable(),
            status: PresenceSessionStatus::from($model->status),
        );
    }
}
