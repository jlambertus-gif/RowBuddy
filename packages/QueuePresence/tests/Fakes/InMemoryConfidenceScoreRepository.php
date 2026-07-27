<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Tests\Fakes;

use RowBuddy\QueuePresence\Contracts\ConfidenceScoreRepository;
use RowBuddy\QueuePresence\ValueObjects\ConfidenceScoreRecord;

final class InMemoryConfidenceScoreRepository implements ConfidenceScoreRepository
{
    /** @var list<ConfidenceScoreRecord> */
    public array $recorded = [];

    public function record(ConfidenceScoreRecord $score): void
    {
        $this->recorded[] = $score;
    }

    public function latestFor(string $presenceSessionId): ?ConfidenceScoreRecord
    {
        $forSession = array_values(array_filter(
            $this->recorded,
            static fn (ConfidenceScoreRecord $score): bool => $score->presenceSessionId === $presenceSessionId,
        ));

        return $forSession === [] ? null : $forSession[count($forSession) - 1];
    }
}
