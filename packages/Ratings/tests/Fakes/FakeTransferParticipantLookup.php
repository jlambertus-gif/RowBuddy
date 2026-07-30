<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Tests\Fakes;

use RowBuddy\Ratings\Contracts\TransferParticipantLookup;
use RowBuddy\Ratings\ValueObjects\TransferParticipantSnapshot;

final class FakeTransferParticipantLookup implements TransferParticipantLookup
{
    /** @var array<string, TransferParticipantSnapshot> */
    public array $snapshots = [];

    public function findByTransferId(string $transferId): ?TransferParticipantSnapshot
    {
        return $this->snapshots[$transferId] ?? null;
    }
}
