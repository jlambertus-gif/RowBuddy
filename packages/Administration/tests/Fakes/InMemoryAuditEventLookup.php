<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Tests\Fakes;

use RowBuddy\Administration\Contracts\AuditEventLookup;
use RowBuddy\Administration\ValueObjects\AuditEventSnapshot;

final class InMemoryAuditEventLookup implements AuditEventLookup
{
    /** @var array<string, AuditEventSnapshot> */
    public array $events = [];

    public function listRecent(int $limit): array
    {
        $events = array_values($this->events);
        usort($events, static fn (AuditEventSnapshot $a, AuditEventSnapshot $b): int => $b->occurredAt <=> $a->occurredAt);

        return array_slice($events, 0, $limit);
    }

    public function findById(string $id): ?AuditEventSnapshot
    {
        return $this->events[$id] ?? null;
    }
}
