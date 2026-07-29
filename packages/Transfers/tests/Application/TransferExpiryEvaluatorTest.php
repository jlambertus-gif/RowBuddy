<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\Transfers\Application\TransferExpiryEvaluator;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

it('expires an Issued transfer once its deadline has passed', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('2026-10-28 10:00:00'), new FrozenClock(new DateTimeImmutable('2026-10-27 10:00:00')));
    $transfer->releaseEvents();
    $evaluator = new TransferExpiryEvaluator(new FrozenClock(new DateTimeImmutable('2026-10-28 10:00:01')));

    $evaluator->evaluate($transfer);

    expect($transfer->status())->toBe(TransferStatus::Expired)
        ->and($transfer->releaseEvents())->toHaveCount(1);
});

it('does nothing to an Issued transfer whose deadline has not yet passed', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('2026-10-28 10:00:00'), new FrozenClock(new DateTimeImmutable('2026-10-27 10:00:00')));
    $transfer->releaseEvents();
    $evaluator = new TransferExpiryEvaluator(new FrozenClock(new DateTimeImmutable('2026-10-28 09:59:59')));

    $evaluator->evaluate($transfer);

    expect($transfer->status())->toBe(TransferStatus::Issued)
        ->and($transfer->releaseEvents())->toBe([]);
});

it('does nothing to a transfer that is not Issued, even if past its original deadline', function () {
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('2026-10-28 10:00:00'), new FrozenClock(new DateTimeImmutable('2026-10-27 10:00:00')));
    $transfer->confirmBySeller(geoPoint(32.7157, -117.1611), new FrozenClock(new DateTimeImmutable('2026-10-27 12:00:00')));
    $transfer->confirmByBuyer(geoPoint(32.7157, -117.1611), new FrozenClock(new DateTimeImmutable('2026-10-27 12:05:00')));
    $transfer->releaseEvents();
    $evaluator = new TransferExpiryEvaluator(new FrozenClock(new DateTimeImmutable('2026-10-28 10:00:01')));

    $evaluator->evaluate($transfer);

    expect($transfer->status())->toBe(TransferStatus::Confirmed)
        ->and($transfer->releaseEvents())->toBe([]);
});
