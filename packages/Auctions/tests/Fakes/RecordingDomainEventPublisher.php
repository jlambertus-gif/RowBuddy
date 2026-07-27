<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Tests\Fakes;

use RowBuddy\Auctions\Contracts\DomainEventPublisher;
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
