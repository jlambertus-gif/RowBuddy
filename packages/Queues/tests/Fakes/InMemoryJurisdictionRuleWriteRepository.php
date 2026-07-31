<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Tests\Fakes;

use RowBuddy\Queues\Contracts\JurisdictionRuleWriteRepository;

final class InMemoryJurisdictionRuleWriteRepository implements JurisdictionRuleWriteRepository
{
    /** @var array<string, bool> */
    public array $states = [];

    public function findActiveState(string $id): ?bool
    {
        return $this->states[$id] ?? null;
    }

    public function setActive(string $id, bool $active): void
    {
        $this->states[$id] = $active;
    }
}
