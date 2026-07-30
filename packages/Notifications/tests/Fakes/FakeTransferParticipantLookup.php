<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Tests\Fakes;

use RowBuddy\Notifications\Contracts\TransferParticipantLookup;
use RowBuddy\Notifications\ValueObjects\TransferParticipantSnapshot;

final class FakeTransferParticipantLookup implements TransferParticipantLookup
{
    /** @var array<string, TransferParticipantSnapshot> */
    public array $snapshots = [];

    public function findByTransferId(string $transferId): ?TransferParticipantSnapshot
    {
        return $this->snapshots[$transferId] ?? null;
    }
}
