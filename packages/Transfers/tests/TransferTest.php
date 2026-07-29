<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\Transfers\Events\TransferBuyerConfirmed;
use RowBuddy\Transfers\Events\TransferCancelled;
use RowBuddy\Transfers\Events\TransferConfirmed;
use RowBuddy\Transfers\Events\TransferEvidenceAttached;
use RowBuddy\Transfers\Events\TransferExpired;
use RowBuddy\Transfers\Events\TransferIssued;
use RowBuddy\Transfers\Events\TransferSellerConfirmed;
use RowBuddy\Transfers\Exceptions\IllegalStateTransition;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferEvidenceType;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

it('issues a transfer and raises a TransferIssued event', function () {
    $issuedAt = new DateTimeImmutable('2026-10-24 10:00:00');
    $expiresAt = $issuedAt->modify('+24 hours');

    $transfer = Transfer::issue(
        'transfer-1',
        'auction-1',
        'bid-1',
        '101',
        '102',
        'hashed-qr-token',
        $expiresAt,
        new FrozenClock($issuedAt),
    );

    expect($transfer->id)->toBe('transfer-1')
        ->and($transfer->auctionId)->toBe('auction-1')
        ->and($transfer->winningBidId)->toBe('bid-1')
        ->and($transfer->sellerId)->toBe('101')
        ->and($transfer->buyerId)->toBe('102')
        ->and($transfer->qrTokenHash)->toBe('hashed-qr-token')
        ->and($transfer->issuedAt)->toEqual($issuedAt)
        ->and($transfer->expiresAt)->toEqual($expiresAt)
        ->and($transfer->status())->toBe(TransferStatus::Issued)
        ->and($transfer->sellerConfirmedAt())->toBeNull()
        ->and($transfer->buyerConfirmedAt())->toBeNull()
        ->and($transfer->confirmedAt())->toBeNull();

    $events = $transfer->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(TransferIssued::class)
        ->and($events[0]->payload())->toBe([
            'transfer_id' => 'transfer-1',
            'auction_id' => 'auction-1',
            'winning_bid_id' => 'bid-1',
            'seller_id' => '101',
            'buyer_id' => '102',
        ]);
});

it('records a seller-only confirmation without reaching Confirmed', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();

    $confirmedAt = new DateTimeImmutable('2026-10-24 11:00:00');
    $geo = geoPoint();
    $transfer->confirmBySeller($geo, new FrozenClock($confirmedAt));

    expect($transfer->status())->toBe(TransferStatus::Issued)
        ->and($transfer->sellerConfirmedAt())->toEqual($confirmedAt)
        ->and($transfer->sellerConfirmedGeo())->toBe($geo)
        ->and($transfer->buyerConfirmedAt())->toBeNull()
        ->and($transfer->confirmedAt())->toBeNull();

    $events = $transfer->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(TransferSellerConfirmed::class);
});

it('records a buyer-only confirmation without reaching Confirmed', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();

    $transfer->confirmByBuyer(geoPoint(), new FrozenClock);

    expect($transfer->status())->toBe(TransferStatus::Issued)
        ->and($transfer->buyerConfirmedAt())->not->toBeNull()
        ->and($transfer->sellerConfirmedAt())->toBeNull();

    $events = $transfer->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(TransferBuyerConfirmed::class);
});

it('reaches Confirmed only once both parties have confirmed, seller then buyer', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();

    $transfer->confirmBySeller(geoPoint(), new FrozenClock);
    $transfer->releaseEvents();

    $confirmedAt = new DateTimeImmutable('2026-10-24 12:00:00');
    $transfer->confirmByBuyer(geoPoint(), new FrozenClock($confirmedAt));

    expect($transfer->status())->toBe(TransferStatus::Confirmed)
        ->and($transfer->confirmedAt())->toEqual($confirmedAt);

    $events = $transfer->releaseEvents();
    expect($events)->toHaveCount(2)
        ->and($events[0])->toBeInstanceOf(TransferBuyerConfirmed::class)
        ->and($events[1])->toBeInstanceOf(TransferConfirmed::class)
        ->and($events[1]->payload())->toBe([
            'transfer_id' => 'transfer-1',
            'auction_id' => 'auction-1',
            'winning_bid_id' => 'bid-1',
        ]);
});

it('reaches Confirmed only once both parties have confirmed, buyer then seller', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();

    $transfer->confirmByBuyer(geoPoint(), new FrozenClock);
    $transfer->releaseEvents();

    $transfer->confirmBySeller(geoPoint(), new FrozenClock);

    expect($transfer->status())->toBe(TransferStatus::Confirmed);

    $events = $transfer->releaseEvents();
    expect($events)->toHaveCount(2)
        ->and($events[0])->toBeInstanceOf(TransferSellerConfirmed::class)
        ->and($events[1])->toBeInstanceOf(TransferConfirmed::class);
});

it('rejects a second seller confirmation', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->confirmBySeller(geoPoint(), new FrozenClock);

    expect(fn () => $transfer->confirmBySeller(geoPoint(), new FrozenClock))
        ->toThrow(IllegalStateTransition::class);
});

it('rejects a second buyer confirmation', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->confirmByBuyer(geoPoint(), new FrozenClock);

    expect(fn () => $transfer->confirmByBuyer(geoPoint(), new FrozenClock))
        ->toThrow(IllegalStateTransition::class);
});

it('rejects any confirmation attempt once already Confirmed', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->confirmBySeller(geoPoint(), new FrozenClock);
    $transfer->confirmByBuyer(geoPoint(), new FrozenClock);

    expect(fn () => $transfer->confirmBySeller(geoPoint(), new FrozenClock))
        ->toThrow(IllegalStateTransition::class);
});

it('expires when the window closes with no confirmation', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();

    $transfer->expire(new FrozenClock);

    expect($transfer->status())->toBe(TransferStatus::Expired);

    $events = $transfer->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(TransferExpired::class);
});

it('rejects expiring a transfer that is not Issued', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->expire(new FrozenClock);

    expect(fn () => $transfer->expire(new FrozenClock))
        ->toThrow(IllegalStateTransition::class);
});

it('cancels with a reason on an explicit default or failed re-authorization', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();

    $transfer->cancel('reauthorization_failed', new FrozenClock);

    expect($transfer->status())->toBe(TransferStatus::Cancelled);

    $events = $transfer->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(TransferCancelled::class)
        ->and($events[0]->payload())->toBe([
            'transfer_id' => 'transfer-1',
            'auction_id' => 'auction-1',
            'reason' => 'reauthorization_failed',
        ]);
});

it('rejects cancelling a transfer that is not Issued', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->cancel('reason', new FrozenClock);

    expect(fn () => $transfer->cancel('reason', new FrozenClock))
        ->toThrow(IllegalStateTransition::class);
});

it('rejects confirming an expired transfer', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->expire(new FrozenClock);

    expect(fn () => $transfer->confirmBySeller(geoPoint(), new FrozenClock))
        ->toThrow(IllegalStateTransition::class);
});

it('attaches evidence regardless of status, including after a terminal transition', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->cancel('reason', new FrozenClock);
    $transfer->releaseEvents();

    $submittedAt = new DateTimeImmutable('2026-10-25 09:00:00');
    $transfer->attachEvidence(TransferEvidenceType::Photo, 'evidence/photo-1.jpg', '102', new FrozenClock($submittedAt));

    expect($transfer->evidenceRecords())->toHaveCount(1);
    $record = $transfer->evidenceRecords()[0];
    expect($record->type)->toBe(TransferEvidenceType::Photo)
        ->and($record->storageReference)->toBe('evidence/photo-1.jpg')
        ->and($record->submittedBy)->toBe('102')
        ->and($record->submittedAt)->toEqual($submittedAt);

    $events = $transfer->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(TransferEvidenceAttached::class)
        ->and($events[0]->payload())->toBe([
            'transfer_id' => 'transfer-1',
            'type' => 'photo',
            'storage_reference' => 'evidence/photo-1.jpg',
            'submitted_by' => '102',
        ]);
});

it('supports attaching multiple evidence records', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);

    $transfer->attachEvidence(TransferEvidenceType::Photo, 'evidence/photo-1.jpg', '101', new FrozenClock);
    $transfer->attachEvidence(TransferEvidenceType::Photo, 'evidence/photo-2.jpg', '102', new FrozenClock);

    expect($transfer->evidenceRecords())->toHaveCount(2);
});

it('releasing events clears them so they are not dispatched twice', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);

    $transfer->releaseEvents();

    expect($transfer->releaseEvents())->toBe([]);
});

it('reconstitutes from persistence without raising any events', function () {
    $issuedAt = new DateTimeImmutable('2026-10-24 10:00:00');
    $expiresAt = $issuedAt->modify('+24 hours');
    $confirmedAt = $issuedAt->modify('+1 hour');

    $transfer = Transfer::fromPersistence(
        'transfer-1',
        'auction-1',
        'bid-1',
        '101',
        '102',
        'hashed-qr-token',
        $issuedAt,
        $expiresAt,
        TransferStatus::Confirmed,
        $confirmedAt,
        geoPoint(),
        $confirmedAt,
        geoPoint(),
        $confirmedAt,
    );

    expect($transfer->status())->toBe(TransferStatus::Confirmed)
        ->and($transfer->sellerConfirmedAt())->toEqual($confirmedAt)
        ->and($transfer->evidenceRecords())->toBe([])
        ->and($transfer->releaseEvents())->toBe([]);
});
