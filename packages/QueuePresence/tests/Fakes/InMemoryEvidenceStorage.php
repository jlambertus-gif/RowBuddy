<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Tests\Fakes;

use DateTimeImmutable;
use RowBuddy\QueuePresence\Contracts\EvidenceStorage;

final class InMemoryEvidenceStorage implements EvidenceStorage
{
    /** @var array<string, string> */
    public array $stored = [];

    private int $nextReference = 1;

    public function store(string $presenceSessionId, string $contents): string
    {
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
