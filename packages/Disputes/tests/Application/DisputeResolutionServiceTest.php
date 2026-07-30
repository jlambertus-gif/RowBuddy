<?php

declare(strict_types=1);

use RowBuddy\Disputes\Application\DisputeResolutionService;
use RowBuddy\Disputes\Dispute;
use RowBuddy\Disputes\Events\DisputeResolved;
use RowBuddy\Disputes\Exceptions\InvalidDisputeResolution;
use RowBuddy\Disputes\Tests\Fakes\InMemoryDisputeRepository;
use RowBuddy\Disputes\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Disputes\Tests\Fakes\RecordingTransactionManager;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\Disputes\ValueObjects\DisputeStatus;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

it('resolves a dispute with ReleaseToSeller and publishes DisputeResolved', function () {
    $disputes = new InMemoryDisputeRepository;
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $dispute->releaseEvents();
    $disputes->save($dispute);
    $events = new RecordingDomainEventPublisher;
    $service = new DisputeResolutionService($disputes, new RecordingTransactionManager, $events, new FrozenClock);

    $service->resolve('dispute-1', DisputeResolutionOutcome::ReleaseToSeller, null, 'admin-1', 'evidence supports the seller', false);

    $found = $disputes->findById('dispute-1');
    expect($found->status())->toBe(DisputeStatus::Resolved)
        ->and($found->resolutionOutcome())->toBe(DisputeResolutionOutcome::ReleaseToSeller)
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(DisputeResolved::class);
});

it('resolves a dispute with RefundToBuyer and a refund amount', function () {
    $disputes = new InMemoryDisputeRepository;
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $dispute->releaseEvents();
    $disputes->save($dispute);
    $amount = new Money(11000, new Currency('USD'));
    $service = new DisputeResolutionService($disputes, new RecordingTransactionManager, new RecordingDomainEventPublisher, new FrozenClock);

    $service->resolve('dispute-1', DisputeResolutionOutcome::RefundToBuyer, $amount, 'admin-1', 'buyer is correct', false);

    $found = $disputes->findById('dispute-1');
    expect($found->resolutionOutcome())->toBe(DisputeResolutionOutcome::RefundToBuyer)
        ->and($found->refundAmount())->toBe($amount);
});

it('records a fraudulent-evidence finding as part of the resolution', function () {
    $disputes = new InMemoryDisputeRepository;
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $dispute->releaseEvents();
    $disputes->save($dispute);
    $service = new DisputeResolutionService($disputes, new RecordingTransactionManager, new RecordingDomainEventPublisher, new FrozenClock);

    $service->resolve('dispute-1', DisputeResolutionOutcome::ReleaseToSeller, null, 'admin-1', 'fabricated evidence', true);

    expect($disputes->findById('dispute-1')->evidenceFoundFraudulent())->toBeTrue();
});

it('throws NotFoundException when resolving a dispute that does not exist', function () {
    $service = new DisputeResolutionService(
        new InMemoryDisputeRepository,
        new RecordingTransactionManager,
        new RecordingDomainEventPublisher,
        new FrozenClock,
    );

    expect(fn () => $service->resolve('missing', DisputeResolutionOutcome::Cancelled, null, 'admin-1', 'reason', false))
        ->toThrow(NotFoundException::class);
});

it('propagates InvalidDisputeResolution for an inconsistent outcome/amount pair', function () {
    $disputes = new InMemoryDisputeRepository;
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $dispute->releaseEvents();
    $disputes->save($dispute);
    $service = new DisputeResolutionService($disputes, new RecordingTransactionManager, new RecordingDomainEventPublisher, new FrozenClock);

    expect(fn () => $service->resolve('dispute-1', DisputeResolutionOutcome::RefundToBuyer, null, 'admin-1', 'reason', false))
        ->toThrow(InvalidDisputeResolution::class);
});
