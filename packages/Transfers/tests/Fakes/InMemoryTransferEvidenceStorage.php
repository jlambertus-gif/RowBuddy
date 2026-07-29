<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Tests\Fakes;

use DateTimeImmutable;
use RowBuddy\Transfers\Contracts\TransferEvidenceStorage;
use RowBuddy\Transfers\Exceptions\EvidenceStorageFailed;

final class InMemoryTransferEvidenceStorage implements TransferEvidenceStorage
{
    /** @var array<string, string> */
    public array $stored = [];

    private int $nextReference = 1;

    public bool $shouldFail = false;

    public function store(string $transferId, string $contents): string
    {
        if ($this->shouldFail) {
            throw EvidenceStorageFailed::forPath("transfer-evidence/{$transferId}/simulated-failure");
        }

        $reference = "fake-storage-ref-{$this->nextReference}";
        $this->nextReference++;

        $this->stored[$reference] = $contents;

        return $reference;
    }

    public function temporaryUrl(string $reference, DateTimeImmutable $expiresAt): string
    {
        return "https://example.test/{$reference}?expires=".$expiresAt->getTimestamp();
    }
}
