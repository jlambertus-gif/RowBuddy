<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Tests\Fakes;

use RowBuddy\Notifications\Contracts\DisputeParticipantLookup;
use RowBuddy\Notifications\ValueObjects\DisputeParticipantSnapshot;

final class FakeDisputeParticipantLookup implements DisputeParticipantLookup
{
    /** @var array<string, DisputeParticipantSnapshot> */
    public array $snapshots = [];

    public function findByDisputeId(string $disputeId): ?DisputeParticipantSnapshot
    {
        return $this->snapshots[$disputeId] ?? null;
    }
}
