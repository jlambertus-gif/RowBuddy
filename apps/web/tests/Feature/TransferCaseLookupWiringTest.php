<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Disputes\Contracts\TransferCaseLookup;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferEvidenceType;

uses(RefreshDatabase::class);

it('returns null for a transfer that does not exist', function () {
    expect(app(TransferCaseLookup::class)->findByTransferId((string) Str::uuid()))->toBeNull();
});

it('reports isConfirmed false and no confirmedAt for a merely Issued transfer', function () {
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

    $snapshot = app(TransferCaseLookup::class)->findByTransferId($transferId);

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->isConfirmed)->toBeFalse()
        ->and($snapshot->confirmedAt)->toBeNull()
        ->and($snapshot->buyerId)->toBe('2')
        ->and($snapshot->sellerId)->toBe('1')
        ->and($snapshot->evidenceRecords)->toBe([]);
});

it('reports isConfirmed true, confirmedAt, and evidence for a Confirmed transfer', function () {
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
    $transfer->attachEvidence(TransferEvidenceType::Photo, 'transfer-evidence/example.jpg', '2', $clock);
    $transfer->releaseEvents();
    $transfers->save($transfer);
    foreach ($transfer->evidenceRecords() as $record) {
        $transfers->recordEvidence($transferId, $record);
    }

    $snapshot = app(TransferCaseLookup::class)->findByTransferId($transferId);

    expect($snapshot->isConfirmed)->toBeTrue()
        ->and($snapshot->confirmedAt)->not->toBeNull()
        ->and($snapshot->evidenceRecords)->toHaveCount(1)
        ->and($snapshot->evidenceRecords[0]->type)->toBe('photo')
        ->and($snapshot->evidenceRecords[0]->storageReference)->toBe('transfer-evidence/example.jpg')
        ->and($snapshot->evidenceRecords[0]->submittedBy)->toBe('2');
});
