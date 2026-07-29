<?php

declare(strict_types=1);

use RowBuddy\Disputes\Dispute;
use RowBuddy\Disputes\Events\DisputeEvidenceAttached;
use RowBuddy\Disputes\Events\DisputeOpened;
use RowBuddy\Disputes\Events\DisputeResolved;
use RowBuddy\Disputes\Exceptions\IllegalStateTransition;
use RowBuddy\Disputes\Exceptions\InvalidDisputeResolution;
use RowBuddy\Disputes\ValueObjects\DisputeEvidenceType;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\Disputes\ValueObjects\DisputeStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

it('opens a dispute and raises a DisputeOpened event', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'The position was not as described.', new FrozenClock);

    expect($dispute->status())->toBe(DisputeStatus::Opened)
        ->and($dispute->transferId)->toBe('transfer-1')
        ->and($dispute->auctionId)->toBe('auction-1')
        ->and($dispute->buyerId)->toBe('101')
        ->and($dispute->sellerId)->toBe('102')
        ->and($dispute->reason)->toBe('The position was not as described.')
        ->and($dispute->evidenceRecords())->toBe([]);

    $events = $dispute->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(DisputeOpened::class);
});

it('allows the buyer to attach evidence while Opened', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $dispute->releaseEvents();

    $dispute->attachEvidence(DisputeEvidenceType::Photo, 'disputes/dispute-1/photo.jpg', '101', new FrozenClock);

    expect($dispute->evidenceRecords())->toHaveCount(1)
        ->and($dispute->evidenceRecords()[0]->type)->toBe(DisputeEvidenceType::Photo)
        ->and($dispute->evidenceRecords()[0]->submittedBy)->toBe('101');

    $events = $dispute->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(DisputeEvidenceAttached::class);
});

it('allows the seller to attach counter-evidence while Opened', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $dispute->releaseEvents();

    $dispute->attachEvidence(DisputeEvidenceType::WrittenStatement, 'The buyer confirmed receipt in person.', '102', new FrozenClock);

    expect($dispute->evidenceRecords())->toHaveCount(1)
        ->and($dispute->evidenceRecords()[0]->submittedBy)->toBe('102');
});

it('supports attaching multiple evidence records from both parties', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $dispute->releaseEvents();

    $dispute->attachEvidence(DisputeEvidenceType::Photo, 'disputes/dispute-1/buyer.jpg', '101', new FrozenClock);
    $dispute->attachEvidence(DisputeEvidenceType::WrittenStatement, 'seller statement', '102', new FrozenClock);

    expect($dispute->evidenceRecords())->toHaveCount(2);
});

it('rejects attaching evidence once resolved', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $dispute->resolve(DisputeResolutionOutcome::ReleaseToSeller, null, 'admin-1', 'no issue found', false, new FrozenClock);

    expect(fn () => $dispute->attachEvidence(DisputeEvidenceType::Photo, 'ref', '101', new FrozenClock))
        ->toThrow(IllegalStateTransition::class);
});

it('resolves with ReleaseToSeller and no refund amount', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $dispute->releaseEvents();

    $dispute->resolve(DisputeResolutionOutcome::ReleaseToSeller, null, 'admin-1', 'evidence supports the seller', false, new FrozenClock);

    expect($dispute->status())->toBe(DisputeStatus::Resolved)
        ->and($dispute->resolutionOutcome())->toBe(DisputeResolutionOutcome::ReleaseToSeller)
        ->and($dispute->refundAmount())->toBeNull()
        ->and($dispute->resolvedBy())->toBe('admin-1')
        ->and($dispute->resolvedAt())->not->toBeNull();

    $events = $dispute->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(DisputeResolved::class);
});

it('resolves with RefundToBuyer and a positive refund amount', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $amount = new Money(11000, new Currency('USD'));

    $dispute->resolve(DisputeResolutionOutcome::RefundToBuyer, $amount, 'admin-1', 'buyer is correct', false, new FrozenClock);

    expect($dispute->resolutionOutcome())->toBe(DisputeResolutionOutcome::RefundToBuyer)
        ->and($dispute->refundAmount())->toBe($amount);
});

it('resolves with Split and a positive refund amount smaller than the full total', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $amount = new Money(5000, new Currency('USD'));

    $dispute->resolve(DisputeResolutionOutcome::Split, $amount, 'admin-1', 'partial fault on both sides', false, new FrozenClock);

    expect($dispute->resolutionOutcome())->toBe(DisputeResolutionOutcome::Split)
        ->and($dispute->refundAmount())->toBe($amount);
});

it('resolves with Cancelled and no refund amount', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);

    $dispute->resolve(DisputeResolutionOutcome::Cancelled, null, 'admin-1', 'buyer withdrew the claim', false, new FrozenClock);

    expect($dispute->resolutionOutcome())->toBe(DisputeResolutionOutcome::Cancelled)
        ->and($dispute->refundAmount())->toBeNull();
});

it('records a fraudulent-evidence finding as an inert observation with no automatic effect', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);

    $dispute->resolve(DisputeResolutionOutcome::ReleaseToSeller, null, 'admin-1', 'buyer submitted a fabricated photo', true, new FrozenClock);

    expect($dispute->evidenceFoundFraudulent())->toBeTrue()
        ->and($dispute->resolutionOutcome())->toBe(DisputeResolutionOutcome::ReleaseToSeller)
        ->and($dispute->refundAmount())->toBeNull();
});

it('rejects RefundToBuyer with no refund amount', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);

    expect(fn () => $dispute->resolve(DisputeResolutionOutcome::RefundToBuyer, null, 'admin-1', 'notes', false, new FrozenClock))
        ->toThrow(InvalidDisputeResolution::class);
});

it('rejects Split with a zero refund amount', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);

    expect(fn () => $dispute->resolve(DisputeResolutionOutcome::Split, new Money(0, new Currency('USD')), 'admin-1', 'notes', false, new FrozenClock))
        ->toThrow(InvalidDisputeResolution::class);
});

it('rejects ReleaseToSeller with a refund amount present', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);

    expect(fn () => $dispute->resolve(DisputeResolutionOutcome::ReleaseToSeller, new Money(1000, new Currency('USD')), 'admin-1', 'notes', false, new FrozenClock))
        ->toThrow(InvalidDisputeResolution::class);
});

it('rejects Cancelled with a refund amount present', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);

    expect(fn () => $dispute->resolve(DisputeResolutionOutcome::Cancelled, new Money(1000, new Currency('USD')), 'admin-1', 'notes', false, new FrozenClock))
        ->toThrow(InvalidDisputeResolution::class);
});

it('rejects resolving a dispute that is already resolved', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $dispute->resolve(DisputeResolutionOutcome::Cancelled, null, 'admin-1', 'notes', false, new FrozenClock);

    expect(fn () => $dispute->resolve(DisputeResolutionOutcome::ReleaseToSeller, null, 'admin-2', 'second attempt', false, new FrozenClock))
        ->toThrow(IllegalStateTransition::class);
});

it('releasing events clears them so they are not dispatched twice', function () {
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $dispute->releaseEvents();

    expect($dispute->releaseEvents())->toBe([]);
});

it('reconstitutes from persistence without raising any events', function () {
    $dispute = Dispute::fromPersistence(
        id: 'dispute-1',
        transferId: 'transfer-1',
        auctionId: 'auction-1',
        buyerId: '101',
        sellerId: '102',
        reason: 'reason',
        openedAt: new DateTimeImmutable('2026-08-01 10:00:00'),
        status: DisputeStatus::Resolved,
        resolutionOutcome: DisputeResolutionOutcome::RefundToBuyer,
        refundAmount: new Money(11000, new Currency('USD')),
        resolvedBy: 'admin-1',
        resolutionNotes: 'notes',
        evidenceFoundFraudulent: false,
        resolvedAt: new DateTimeImmutable('2026-08-03 10:00:00'),
    );

    expect($dispute->status())->toBe(DisputeStatus::Resolved)
        ->and($dispute->resolutionOutcome())->toBe(DisputeResolutionOutcome::RefundToBuyer)
        ->and($dispute->releaseEvents())->toBe([]);
});
