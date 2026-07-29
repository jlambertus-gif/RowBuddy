<?php

declare(strict_types=1);

use RowBuddy\Disputes\Application\DisputeFilingService;
use RowBuddy\Disputes\Application\FixedDisputeFilingDeadlinePolicy;
use RowBuddy\Disputes\Events\DisputeOpened;
use RowBuddy\Disputes\Exceptions\DisputeAlreadyExistsForTransfer;
use RowBuddy\Disputes\Exceptions\DisputeFilingNotAuthorized;
use RowBuddy\Disputes\Exceptions\DisputeFilingWindowElapsed;
use RowBuddy\Disputes\Exceptions\TransferNotEligibleForDispute;
use RowBuddy\Disputes\Tests\Fakes\FakeTransferCaseLookup;
use RowBuddy\Disputes\Tests\Fakes\InMemoryDisputeRepository;
use RowBuddy\Disputes\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Disputes\ValueObjects\TransferCaseSnapshot;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('files a dispute for the buyer against a Confirmed transfer within the deadline', function () {
    $disputes = new InMemoryDisputeRepository;
    $transferCases = new FakeTransferCaseLookup;
    $transferCases->snapshots['transfer-1'] = new TransferCaseSnapshot(
        transferId: 'transfer-1',
        auctionId: 'auction-1',
        buyerId: '101',
        sellerId: '102',
        isConfirmed: true,
        confirmedAt: new DateTimeImmutable('2026-08-01 10:00:00'),
        evidenceRecords: [],
    );
    $events = new RecordingDomainEventPublisher;
    $now = new FrozenClock(new DateTimeImmutable('2026-08-03 10:00:00'));
    $service = new DisputeFilingService($disputes, $transferCases, new FixedDisputeFilingDeadlinePolicy(7 * 86400), $events, $now);

    $dispute = $service->file('dispute-1', 'transfer-1', '101', 'Not as described');

    expect($dispute->id)->toBe('dispute-1')
        ->and($dispute->transferId)->toBe('transfer-1')
        ->and($dispute->auctionId)->toBe('auction-1')
        ->and($dispute->buyerId)->toBe('101')
        ->and($dispute->sellerId)->toBe('102')
        ->and($disputes->findByTransferId('transfer-1'))->not->toBeNull()
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(DisputeOpened::class);
});

it('rejects filing when the transfer does not exist', function () {
    $service = new DisputeFilingService(
        new InMemoryDisputeRepository,
        new FakeTransferCaseLookup,
        new FixedDisputeFilingDeadlinePolicy(7 * 86400),
        new RecordingDomainEventPublisher,
        new FrozenClock,
    );

    expect(fn () => $service->file('dispute-1', 'transfer-missing', '101', 'reason'))
        ->toThrow(TransferNotEligibleForDispute::class);
});

it('rejects filing when the transfer is not Confirmed', function () {
    $transferCases = new FakeTransferCaseLookup;
    $transferCases->snapshots['transfer-1'] = new TransferCaseSnapshot(
        transferId: 'transfer-1',
        auctionId: 'auction-1',
        buyerId: '101',
        sellerId: '102',
        isConfirmed: false,
        confirmedAt: null,
        evidenceRecords: [],
    );
    $service = new DisputeFilingService(
        new InMemoryDisputeRepository,
        $transferCases,
        new FixedDisputeFilingDeadlinePolicy(7 * 86400),
        new RecordingDomainEventPublisher,
        new FrozenClock,
    );

    expect(fn () => $service->file('dispute-1', 'transfer-1', '101', 'reason'))
        ->toThrow(TransferNotEligibleForDispute::class);
});

it('rejects filing by anyone other than the transfer\'s own buyer', function () {
    $transferCases = new FakeTransferCaseLookup;
    $transferCases->snapshots['transfer-1'] = new TransferCaseSnapshot(
        transferId: 'transfer-1',
        auctionId: 'auction-1',
        buyerId: '101',
        sellerId: '102',
        isConfirmed: true,
        confirmedAt: new DateTimeImmutable('2026-08-01 10:00:00'),
        evidenceRecords: [],
    );
    $service = new DisputeFilingService(
        new InMemoryDisputeRepository,
        $transferCases,
        new FixedDisputeFilingDeadlinePolicy(7 * 86400),
        new RecordingDomainEventPublisher,
        new FrozenClock(new DateTimeImmutable('2026-08-02 10:00:00')),
    );

    expect(fn () => $service->file('dispute-1', 'transfer-1', '102', 'seller trying to file'))
        ->toThrow(DisputeFilingNotAuthorized::class);
});

it('rejects filing after the filing deadline has elapsed', function () {
    $transferCases = new FakeTransferCaseLookup;
    $transferCases->snapshots['transfer-1'] = new TransferCaseSnapshot(
        transferId: 'transfer-1',
        auctionId: 'auction-1',
        buyerId: '101',
        sellerId: '102',
        isConfirmed: true,
        confirmedAt: new DateTimeImmutable('2026-08-01 10:00:00'),
        evidenceRecords: [],
    );
    $service = new DisputeFilingService(
        new InMemoryDisputeRepository,
        $transferCases,
        new FixedDisputeFilingDeadlinePolicy(7 * 86400),
        new RecordingDomainEventPublisher,
        new FrozenClock(new DateTimeImmutable('2026-08-08 10:00:01')),
    );

    expect(fn () => $service->file('dispute-1', 'transfer-1', '101', 'too late'))
        ->toThrow(DisputeFilingWindowElapsed::class);
});

it('allows filing exactly at the filing deadline boundary', function () {
    $transferCases = new FakeTransferCaseLookup;
    $transferCases->snapshots['transfer-1'] = new TransferCaseSnapshot(
        transferId: 'transfer-1',
        auctionId: 'auction-1',
        buyerId: '101',
        sellerId: '102',
        isConfirmed: true,
        confirmedAt: new DateTimeImmutable('2026-08-01 10:00:00'),
        evidenceRecords: [],
    );
    $service = new DisputeFilingService(
        new InMemoryDisputeRepository,
        $transferCases,
        new FixedDisputeFilingDeadlinePolicy(7 * 86400),
        new RecordingDomainEventPublisher,
        new FrozenClock(new DateTimeImmutable('2026-08-08 10:00:00')),
    );

    $dispute = $service->file('dispute-1', 'transfer-1', '101', 'right at the boundary');

    expect($dispute->id)->toBe('dispute-1');
});

it('rejects filing a second dispute for a transfer that already has one', function () {
    $disputes = new InMemoryDisputeRepository;
    $transferCases = new FakeTransferCaseLookup;
    $transferCases->snapshots['transfer-1'] = new TransferCaseSnapshot(
        transferId: 'transfer-1',
        auctionId: 'auction-1',
        buyerId: '101',
        sellerId: '102',
        isConfirmed: true,
        confirmedAt: new DateTimeImmutable('2026-08-01 10:00:00'),
        evidenceRecords: [],
    );
    $service = new DisputeFilingService(
        $disputes,
        $transferCases,
        new FixedDisputeFilingDeadlinePolicy(7 * 86400),
        new RecordingDomainEventPublisher,
        new FrozenClock(new DateTimeImmutable('2026-08-02 10:00:00')),
    );

    $service->file('dispute-1', 'transfer-1', '101', 'first filing');

    expect(fn () => $service->file('dispute-2', 'transfer-1', '101', 'second filing'))
        ->toThrow(DisputeAlreadyExistsForTransfer::class);
});
