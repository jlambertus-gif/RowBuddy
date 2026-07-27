<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Tests\Fakes;

use RowBuddy\QueuePresence\Contracts\PresenceSessionRepository;
use RowBuddy\QueuePresence\Exceptions\DuplicateActivePresenceSession;
use RowBuddy\QueuePresence\PresenceSession;
use RowBuddy\QueuePresence\ValueObjects\PresenceSessionStatus;

/**
 * Also simulates the "one active session per seller per queue" partial
 * unique index from the real Eloquent adapter (Sprint 2), so that
 * invariant is exercised by fast, database-free application-service tests
 * too, not only the Docker-backed repository integration test.
 */
final class InMemoryPresenceSessionRepository implements PresenceSessionRepository
{
    /** @var array<string, PresenceSession> */
    public array $saved = [];

    public function save(PresenceSession $session): void
    {
        if ($session->status() === PresenceSessionStatus::Active && $this->hasAnotherActiveSession($session)) {
            throw DuplicateActivePresenceSession::forSellerAndQueue($session->sellerId, $session->queueId);
        }

        $this->saved[$session->id] = $session;
    }

    public function findById(string $id): ?PresenceSession
    {
        return $this->saved[$id] ?? null;
    }

    public function findLatestBySellerAndQueue(string $sellerId, string $queueId): ?PresenceSession
    {
        $matches = array_filter(
            $this->saved,
            static fn (PresenceSession $session): bool => $session->sellerId === $sellerId && $session->queueId === $queueId,
        );

        if ($matches === []) {
            return null;
        }

        usort($matches, static fn (PresenceSession $a, PresenceSession $b): int => $b->startedAt <=> $a->startedAt);

        return $matches[array_key_first($matches)];
    }

    private function hasAnotherActiveSession(PresenceSession $session): bool
    {
        foreach ($this->saved as $existing) {
            if (
                $existing->id !== $session->id
                && $existing->sellerId === $session->sellerId
                && $existing->queueId === $session->queueId
                && $existing->status() === PresenceSessionStatus::Active
            ) {
                return true;
            }
        }

        return false;
    }
}
