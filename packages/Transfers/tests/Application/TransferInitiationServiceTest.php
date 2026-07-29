<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\Transfers\Application\FixedTransferWindowPolicy;
use RowBuddy\Transfers\Application\TransferInitiationService;
use RowBuddy\Transfers\Events\TransferIssued;
use RowBuddy\Transfers\Tests\Fakes\InMemoryTransferRepository;
use RowBuddy\Transfers\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

it('issues a transfer with a hashed QR token and returns the plaintext once', function () {
    $transfers = new InMemoryTransferRepository;
    $events = new RecordingDomainEventPublisher;
    $issuedAt = new DateTimeImmutable('2026-10-30 10:00:00');
    $service = new TransferInitiationService($transfers, new FixedTransferWindowPolicy(86400), $events, new FrozenClock($issuedAt));

    $issuance = $service->handle('transfer-1', 'auction-1', 'bid-1', '101', '102');

    expect($issuance->plaintextQrToken)->not->toBeNull()
        ->and(mb_strlen($issuance->plaintextQrToken))->toBeGreaterThan(32)
        ->and($issuance->transfer->qrTokenHash)->toBe(hash('sha256', $issuance->plaintextQrToken))
        ->and($issuance->transfer->status())->toBe(TransferStatus::Issued)
        ->and($issuance->transfer->issuedAt)->toEqual($issuedAt)
        ->and($issuance->transfer->expiresAt)->toEqual($issuedAt->modify('+86400 seconds'))
        ->and($transfers->findById('transfer-1'))->not->toBeNull();

    expect($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(TransferIssued::class);
});

it('generates a different plaintext token for each new transfer', function () {
    $transfers = new InMemoryTransferRepository;
    $service = new TransferInitiationService($transfers, new FixedTransferWindowPolicy(86400), new RecordingDomainEventPublisher, new FrozenClock);

    $first = $service->handle('transfer-1', 'auction-1', 'bid-1', '101', '102');
    $second = $service->handle('transfer-2', 'auction-2', 'bid-2', '103', '104');

    expect($first->plaintextQrToken)->not->toBe($second->plaintextQrToken);
});

it('is idempotent: a second call for the same auction returns the existing transfer with no plaintext token', function () {
    $transfers = new InMemoryTransferRepository;
    $events = new RecordingDomainEventPublisher;
    $service = new TransferInitiationService($transfers, new FixedTransferWindowPolicy(86400), $events, new FrozenClock);

    $first = $service->handle('transfer-1', 'auction-1', 'bid-1', '101', '102');
    $second = $service->handle('transfer-2', 'auction-1', 'bid-1', '101', '102');

    expect($second->transfer->id)->toBe($first->transfer->id)
        ->and($second->plaintextQrToken)->toBeNull()
        ->and($transfers->saved)->toHaveCount(1)
        ->and($events->published)->toHaveCount(1);
});
