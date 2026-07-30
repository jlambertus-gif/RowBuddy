<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Ratings\Contracts\TransferParticipantLookup;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\Transfer;

uses(RefreshDatabase::class);

it('returns null for a transfer that does not exist', function () {
    expect(app(TransferParticipantLookup::class)->findByTransferId((string) Str::uuid()))->toBeNull();
});

it('reports isConfirmed false for a merely Issued transfer', function () {
    $transfers = app(TransferRepository::class);
    $clock = app(ClockInterface::class);
    $transferId = (string) Str::uuid();

    $transfer = Transfer::issue(
        $transferId,
        (string) Str::uuid(),
        (string) Str::uuid(),
        '1',
        '2',
        hash('sha256', 'plaintext-token'),
        $clock->now()->modify('+24 hours'),
        $clock,
    );
    $transfer->releaseEvents();
    $transfers->save($transfer);

    $snapshot = app(TransferParticipantLookup::class)->findByTransferId($transferId);

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->isConfirmed)->toBeFalse()
        ->and($snapshot->buyerId)->toBe('2')
        ->and($snapshot->sellerId)->toBe('1');
});

it('reports isConfirmed true for a Confirmed transfer', function () {
    $transfers = app(TransferRepository::class);
    $clock = app(ClockInterface::class);
    $transferId = (string) Str::uuid();

    $transfer = Transfer::issue(
        $transferId,
        (string) Str::uuid(),
        (string) Str::uuid(),
        '1',
        '2',
        hash('sha256', 'plaintext-token'),
        $clock->now()->modify('+24 hours'),
        $clock,
    );
    $geo = new GeoPoint(32.7157, -117.1611);
    $transfer->confirmBySeller($geo, $clock);
    $transfer->confirmByBuyer($geo, $clock);
    $transfer->releaseEvents();
    $transfers->save($transfer);

    $snapshot = app(TransferParticipantLookup::class)->findByTransferId($transferId);

    expect($snapshot->isConfirmed)->toBeTrue()
        ->and($snapshot->buyerId)->toBe('2')
        ->and($snapshot->sellerId)->toBe('1');
});
