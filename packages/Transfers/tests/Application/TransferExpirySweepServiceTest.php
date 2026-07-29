<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\Transfers\Application\TransferExpiryEvaluator;
use RowBuddy\Transfers\Application\TransferExpirySweepService;
use RowBuddy\Transfers\Events\TransferExpired;
use RowBuddy\Transfers\Tests\Fakes\InMemoryTransferRepository;
use RowBuddy\Transfers\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Transfers\Tests\Fakes\RecordingTransactionManager;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

it('expires every still-Issued transfer past its deadline and leaves the rest untouched', function () {
    $transfers = new InMemoryTransferRepository;

    $due = Transfer::issue('transfer-due', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('2026-10-28 10:00:00'), new FrozenClock(new DateTimeImmutable('2026-10-27 10:00:00')));
    $due->releaseEvents();
    $transfers->save($due);

    $notDue = Transfer::issue('transfer-not-due', 'auction-2', 'bid-2', '103', '104', 'hash', new DateTimeImmutable('2026-10-29 10:00:00'), new FrozenClock(new DateTimeImmutable('2026-10-27 10:00:00')));
    $notDue->releaseEvents();
    $transfers->save($notDue);

    $confirmed = Transfer::issue('transfer-confirmed', 'auction-3', 'bid-3', '105', '106', 'hash', new DateTimeImmutable('2026-10-28 10:00:00'), new FrozenClock(new DateTimeImmutable('2026-10-27 10:00:00')));
    $confirmed->confirmBySeller(geoPoint(), new FrozenClock(new DateTimeImmutable('2026-10-27 11:00:00')));
    $confirmed->confirmByBuyer(geoPoint(), new FrozenClock(new DateTimeImmutable('2026-10-27 11:05:00')));
    $confirmed->releaseEvents();
    $transfers->save($confirmed);

    $events = new RecordingDomainEventPublisher;
    $evaluator = new TransferExpiryEvaluator(new FrozenClock(new DateTimeImmutable('2026-10-28 10:00:01')));
    $sweep = new TransferExpirySweepService($transfers, $evaluator, new RecordingTransactionManager, $events);

    $evaluated = $sweep->sweep();

    expect($evaluated)->toBe(2)
        ->and($transfers->findById('transfer-due')->status())->toBe(TransferStatus::Expired)
        ->and($transfers->findById('transfer-not-due')->status())->toBe(TransferStatus::Issued)
        ->and($transfers->findById('transfer-confirmed')->status())->toBe(TransferStatus::Confirmed);

    $expiredEvents = array_filter($events->published, static fn ($event): bool => $event instanceof TransferExpired);
    expect($expiredEvents)->toHaveCount(1);
});

it('evaluates nothing when there are no Issued transfers', function () {
    $transfers = new InMemoryTransferRepository;
    $events = new RecordingDomainEventPublisher;
    $sweep = new TransferExpirySweepService($transfers, new TransferExpiryEvaluator(new FrozenClock), new RecordingTransactionManager, $events);

    expect($sweep->sweep())->toBe(0)
        ->and($events->published)->toBe([]);
});
