<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Tests\Fakes;

use RowBuddy\Ratings\Contracts\DomainEventPublisher;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

final class RecordingDomainEventPublisher implements DomainEventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(DomainEvent $event): void
    {
        $this->published[] = $event;
    }
}
