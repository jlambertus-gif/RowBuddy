<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Tests\Fakes;

use RowBuddy\Disputes\Contracts\TransferCaseLookup;
use RowBuddy\Disputes\ValueObjects\TransferCaseSnapshot;

final class FakeTransferCaseLookup implements TransferCaseLookup
{
    /** @var array<string, TransferCaseSnapshot> */
    public array $snapshots = [];

    public function findByTransferId(string $transferId): ?TransferCaseSnapshot
    {
        return $this->snapshots[$transferId] ?? null;
    }
}
