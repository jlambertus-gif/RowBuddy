<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;
use RowBuddy\Transfers\Application\TransferConfirmationService;
use RowBuddy\Transfers\Application\TransferExpiryEvaluator;
use RowBuddy\Transfers\Events\TransferBuyerConfirmed;
use RowBuddy\Transfers\Events\TransferConfirmed;
use RowBuddy\Transfers\Events\TransferExpired;
use RowBuddy\Transfers\Events\TransferSellerConfirmed;
use RowBuddy\Transfers\Exceptions\ConfirmationOutsideGeofence;
use RowBuddy\Transfers\Exceptions\IllegalStateTransition;
use RowBuddy\Transfers\Exceptions\InvalidQrToken;
use RowBuddy\Transfers\Exceptions\TransferAccessDenied;
use RowBuddy\Transfers\Tests\Fakes\FakeTransferGeofenceLookup;
use RowBuddy\Transfers\Tests\Fakes\InMemoryTransferRepository;
use RowBuddy\Transfers\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Transfers\Tests\Fakes\RecordingTransactionManager;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

// Every test issues a transfer with the plaintext QR token
// 'plaintext-token' (hashed at rest, per ADR-017 §2), seller id '101',
// buyer id '102', before exercising confirmation — inlined per test,
// matching this codebase's established style, rather than via a shared
// helper.

it('records a seller-only confirmation', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $events = new RecordingDomainEventPublisher;
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator(new FrozenClock), new RecordingTransactionManager, $events, new FrozenClock);

    $service->confirmBySeller('transfer-1', '101', 'plaintext-token', $geofence->geofence->center);

    $found = $transfers->findById('transfer-1');
    expect($found->status())->toBe(TransferStatus::Issued)
        ->and($found->sellerConfirmedAt())->not->toBeNull()
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(TransferSellerConfirmed::class);
});

it('records a buyer-only confirmation', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $events = new RecordingDomainEventPublisher;
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator(new FrozenClock), new RecordingTransactionManager, $events, new FrozenClock);

    $service->confirmByBuyer('transfer-1', '102', $geofence->geofence->center);

    $found = $transfers->findById('transfer-1');
    expect($found->status())->toBe(TransferStatus::Issued)
        ->and($found->buyerConfirmedAt())->not->toBeNull()
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(TransferBuyerConfirmed::class);
});

it('reaches Confirmed and publishes TransferConfirmed once both parties confirm, seller then buyer', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $events = new RecordingDomainEventPublisher;
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator(new FrozenClock), new RecordingTransactionManager, $events, new FrozenClock);

    $service->confirmBySeller('transfer-1', '101', 'plaintext-token', $geofence->geofence->center);
    $service->confirmByBuyer('transfer-1', '102', $geofence->geofence->center);

    $found = $transfers->findById('transfer-1');
    expect($found->status())->toBe(TransferStatus::Confirmed);

    $confirmedEvents = array_filter($events->published, static fn ($event): bool => $event instanceof TransferConfirmed);
    expect($confirmedEvents)->toHaveCount(1);
});

it('reaches Confirmed and publishes TransferConfirmed once both parties confirm, buyer then seller', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $events = new RecordingDomainEventPublisher;
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator(new FrozenClock), new RecordingTransactionManager, $events, new FrozenClock);

    $service->confirmByBuyer('transfer-1', '102', $geofence->geofence->center);
    $service->confirmBySeller('transfer-1', '101', 'plaintext-token', $geofence->geofence->center);

    expect($transfers->findById('transfer-1')->status())->toBe(TransferStatus::Confirmed);

    $confirmedEvents = array_filter($events->published, static fn ($event): bool => $event instanceof TransferConfirmed);
    expect($confirmedEvents)->toHaveCount(1);
});

it('rejects a seller confirmation with the wrong QR token and records nothing', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $events = new RecordingDomainEventPublisher;
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator(new FrozenClock), new RecordingTransactionManager, $events, new FrozenClock);

    expect(fn () => $service->confirmBySeller('transfer-1', '101', 'wrong-token', $geofence->geofence->center))
        ->toThrow(InvalidQrToken::class);

    expect($transfers->findById('transfer-1')->sellerConfirmedAt())->toBeNull()
        ->and($events->published)->toBe([]);
});

it('rejects a seller confirmation outside the geofence and records nothing', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator(new FrozenClock), new RecordingTransactionManager, new RecordingDomainEventPublisher, new FrozenClock);

    $farAway = new GeoPoint(40.7128, -74.0060);

    expect(fn () => $service->confirmBySeller('transfer-1', '101', 'plaintext-token', $farAway))
        ->toThrow(ConfirmationOutsideGeofence::class);

    expect($transfers->findById('transfer-1')->sellerConfirmedAt())->toBeNull();
});

it('rejects a buyer confirmation outside the geofence and records nothing', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator(new FrozenClock), new RecordingTransactionManager, new RecordingDomainEventPublisher, new FrozenClock);

    $farAway = new GeoPoint(40.7128, -74.0060);

    expect(fn () => $service->confirmByBuyer('transfer-1', '102', $farAway))
        ->toThrow(ConfirmationOutsideGeofence::class);

    expect($transfers->findById('transfer-1')->buyerConfirmedAt())->toBeNull();
});

it('throws NotFoundException when confirming a transfer that does not exist', function () {
    $service = new TransferConfirmationService(
        new InMemoryTransferRepository,
        new FakeTransferGeofenceLookup,
        new TransferExpiryEvaluator(new FrozenClock),
        new RecordingTransactionManager,
        new RecordingDomainEventPublisher,
        new FrozenClock,
    );

    expect(fn () => $service->confirmByBuyer('missing', '102', new GeoPoint(32.7157, -117.1611)))
        ->toThrow(NotFoundException::class);
});

it('expires an already-due transfer lazily on a confirmation attempt, committing the expiry and rejecting the attempt', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('2026-10-28 10:00:00'), new FrozenClock(new DateTimeImmutable('2026-10-27 10:00:00')));
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $events = new RecordingDomainEventPublisher;
    $pastDeadline = new FrozenClock(new DateTimeImmutable('2026-10-28 10:00:01'));
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator($pastDeadline), new RecordingTransactionManager, $events, $pastDeadline);

    expect(fn () => $service->confirmBySeller('transfer-1', '101', 'plaintext-token', $geofence->geofence->center))
        ->toThrow(IllegalStateTransition::class);

    $found = $transfers->findById('transfer-1');
    expect($found->status())->toBe(TransferStatus::Expired)
        ->and($found->sellerConfirmedAt())->toBeNull()
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(TransferExpired::class);
});

it('does not expire a transfer whose deadline has not yet passed and confirms it normally', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('2026-10-28 10:00:00'), new FrozenClock(new DateTimeImmutable('2026-10-27 10:00:00')));
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $events = new RecordingDomainEventPublisher;
    $beforeDeadline = new FrozenClock(new DateTimeImmutable('2026-10-28 09:59:59'));
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator($beforeDeadline), new RecordingTransactionManager, $events, $beforeDeadline);

    $service->confirmBySeller('transfer-1', '101', 'plaintext-token', $geofence->geofence->center);

    $found = $transfers->findById('transfer-1');
    expect($found->status())->toBe(TransferStatus::Issued)
        ->and($found->sellerConfirmedAt())->not->toBeNull()
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(TransferSellerConfirmed::class);
});

it('rejects a seller confirmation attempted by the buyer and records nothing', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $events = new RecordingDomainEventPublisher;
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator(new FrozenClock), new RecordingTransactionManager, $events, new FrozenClock);

    expect(fn () => $service->confirmBySeller('transfer-1', '102', 'plaintext-token', $geofence->geofence->center))
        ->toThrow(TransferAccessDenied::class);

    expect($transfers->findById('transfer-1')->sellerConfirmedAt())->toBeNull()
        ->and($events->published)->toBe([]);
});

it('rejects a buyer confirmation attempted by the seller and records nothing', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $events = new RecordingDomainEventPublisher;
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator(new FrozenClock), new RecordingTransactionManager, $events, new FrozenClock);

    expect(fn () => $service->confirmByBuyer('transfer-1', '101', $geofence->geofence->center))
        ->toThrow(TransferAccessDenied::class);

    expect($transfers->findById('transfer-1')->buyerConfirmedAt())->toBeNull()
        ->and($events->published)->toBe([]);
});

it('rejects a confirmation attempted by a completely unrelated user and records nothing', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $events = new RecordingDomainEventPublisher;
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator(new FrozenClock), new RecordingTransactionManager, $events, new FrozenClock);

    expect(fn () => $service->confirmByBuyer('transfer-1', '999', $geofence->geofence->center))
        ->toThrow(TransferAccessDenied::class);

    expect(fn () => $service->confirmBySeller('transfer-1', '999', 'plaintext-token', $geofence->geofence->center))
        ->toThrow(TransferAccessDenied::class);

    expect($transfers->findById('transfer-1')->sellerConfirmedAt())->toBeNull()
        ->and($transfers->findById('transfer-1')->buyerConfirmedAt())->toBeNull()
        ->and($events->published)->toBe([]);
});

it('rejects access-denied confirmation attempts before the expiry evaluator ever runs', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', hash('sha256', 'plaintext-token'), new DateTimeImmutable('2026-10-28 10:00:00'), new FrozenClock(new DateTimeImmutable('2026-10-27 10:00:00')));
    $transfer->releaseEvents();
    $transfers->save($transfer);
    $geofence = new FakeTransferGeofenceLookup;
    $pastDeadline = new FrozenClock(new DateTimeImmutable('2026-10-28 10:00:01'));
    $service = new TransferConfirmationService($transfers, $geofence, new TransferExpiryEvaluator($pastDeadline), new RecordingTransactionManager, new RecordingDomainEventPublisher, $pastDeadline);

    expect(fn () => $service->confirmByBuyer('transfer-1', '999', $geofence->geofence->center))
        ->toThrow(TransferAccessDenied::class);

    // The transfer's own deadline has already passed, but since the
    // wrong-participant check runs first, the expiry evaluator never even
    // ran — the transfer is still Issued, not Expired.
    expect($transfers->findById('transfer-1')->status())->toBe(TransferStatus::Issued);
});
