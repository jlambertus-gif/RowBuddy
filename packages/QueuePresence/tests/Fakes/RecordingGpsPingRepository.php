<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Tests\Fakes;

use RowBuddy\QueuePresence\Contracts\GpsPingRepository;
use RowBuddy\QueuePresence\ValueObjects\GpsPingRecord;

final class RecordingGpsPingRepository implements GpsPingRepository
{
    /** @var list<GpsPingRecord> */
    public array $recorded = [];

    public function record(GpsPingRecord $ping): void
    {
        $this->recorded[] = $ping;
    }
}
