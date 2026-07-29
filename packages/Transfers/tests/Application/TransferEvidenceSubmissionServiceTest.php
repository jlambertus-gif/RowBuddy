<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\Transfers\Application\TransferEvidenceSubmissionService;
use RowBuddy\Transfers\Events\TransferEvidenceAttached;
use RowBuddy\Transfers\Tests\Fakes\InMemoryTransferEvidenceStorage;
use RowBuddy\Transfers\Tests\Fakes\InMemoryTransferRepository;
use RowBuddy\Transfers\Tests\Fakes\PassthroughMetadataStripper;
use RowBuddy\Transfers\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferEvidenceType;

it('strips metadata, stores the photo, attaches evidence, and publishes TransferEvidenceAttached', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);

    $storage = new InMemoryTransferEvidenceStorage;
    $events = new RecordingDomainEventPublisher;
    $service = new TransferEvidenceSubmissionService($transfers, $storage, new PassthroughMetadataStripper, $events, new FrozenClock);

    $service->submitPhoto('transfer-1', '101', 'raw-image-bytes');

    $found = $transfers->findById('transfer-1');
    expect($found->evidenceRecords())->toHaveCount(1)
        ->and($found->evidenceRecords()[0]->type)->toBe(TransferEvidenceType::Photo)
        ->and($found->evidenceRecords()[0]->submittedBy)->toBe('101')
        ->and($storage->stored)->toHaveCount(1)
        ->and(reset($storage->stored))->toBe('raw-image-bytes')
        ->and($transfers->recordedEvidence['transfer-1'])->toHaveCount(1)
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(TransferEvidenceAttached::class);
});

it('supports submitting more than one evidence photo for the same transfer', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);

    $service = new TransferEvidenceSubmissionService($transfers, new InMemoryTransferEvidenceStorage, new PassthroughMetadataStripper, new RecordingDomainEventPublisher, new FrozenClock);

    $service->submitPhoto('transfer-1', '101', 'first-photo');
    $service->submitPhoto('transfer-1', '102', 'second-photo');

    expect($transfers->findById('transfer-1')->evidenceRecords())->toHaveCount(2)
        ->and($transfers->recordedEvidence['transfer-1'])->toHaveCount(2);
});

it('allows attaching evidence to a transfer regardless of its confirmation status', function () {
    $transfers = new InMemoryTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $transfer->confirmBySeller(geoPoint(), new FrozenClock);
    $transfer->confirmByBuyer(geoPoint(), new FrozenClock);
    $transfer->releaseEvents();
    $transfers->save($transfer);

    $service = new TransferEvidenceSubmissionService($transfers, new InMemoryTransferEvidenceStorage, new PassthroughMetadataStripper, new RecordingDomainEventPublisher, new FrozenClock);

    $service->submitPhoto('transfer-1', '101', 'photo-after-confirmation');

    expect($transfers->findById('transfer-1')->evidenceRecords())->toHaveCount(1);
});

it('throws NotFoundException when submitting evidence for a transfer that does not exist', function () {
    $service = new TransferEvidenceSubmissionService(
        new InMemoryTransferRepository,
        new InMemoryTransferEvidenceStorage,
        new PassthroughMetadataStripper,
        new RecordingDomainEventPublisher,
        new FrozenClock,
    );

    expect(fn () => $service->submitPhoto('missing', '101', 'raw-image-bytes'))
        ->toThrow(NotFoundException::class);
});
