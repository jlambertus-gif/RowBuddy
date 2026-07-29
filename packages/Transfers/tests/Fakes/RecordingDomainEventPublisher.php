<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Tests\Fakes;

use RowBuddy\SharedKernel\Contracts\DomainEvent;
use RowBuddy\Transfers\Contracts\DomainEventPublisher;

final class RecordingDomainEventPublisher implements DomainEventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(DomainEvent $event): void
    {
        $this->published[] = $event;
    }
}
