<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Tests\Fakes;

use RowBuddy\Administration\Contracts\DisputeCaseLookup;
use RowBuddy\Administration\ValueObjects\DisputeCaseSnapshot;

final class InMemoryDisputeCaseLookup implements DisputeCaseLookup
{
    /** @var array<string, DisputeCaseSnapshot> */
    public array $disputes = [];

    public function listAll(): array
    {
        return array_values($this->disputes);
    }

    public function findById(string $disputeId): ?DisputeCaseSnapshot
    {
        return $this->disputes[$disputeId] ?? null;
    }
}
