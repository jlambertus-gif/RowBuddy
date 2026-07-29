<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\Transfers\Exceptions\TransferAlreadyIssuedForAuction;
use RowBuddy\Transfers\Infrastructure\Eloquent\EloquentTransferRepository;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferEvidenceType;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('transfers', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('auction_id')->unique();
        $table->string('winning_bid_id');
        $table->unsignedBigInteger('seller_id');
        $table->unsignedBigInteger('buyer_id');
        $table->string('qr_token_hash');
        $table->timestamp('issued_at');
        $table->timestamp('expires_at');
        $table->string('status');
        $table->timestamp('seller_confirmed_at')->nullable();
        $table->decimal('seller_confirmed_latitude', 10, 7)->nullable();
        $table->decimal('seller_confirmed_longitude', 10, 7)->nullable();
        $table->timestamp('buyer_confirmed_at')->nullable();
        $table->decimal('buyer_confirmed_latitude', 10, 7)->nullable();
        $table->decimal('buyer_confirmed_longitude', 10, 7)->nullable();
        $table->timestamp('confirmed_at')->nullable();
        $table->timestamps();
    });

    Capsule::schema()->create('transfer_evidence', function (Blueprint $table) {
        $table->id();
        $table->string('transfer_id');
        $table->string('type');
        $table->string('storage_reference');
        $table->unsignedBigInteger('submitted_by');
        $table->timestamp('submitted_at');
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('transfer_evidence');
    Capsule::schema()->dropIfExists('transfers');
});

it('saves and finds a transfer by id, round-tripping every field', function () {
    $repository = new EloquentTransferRepository;
    $issuedAt = new DateTimeImmutable('2026-10-28 10:00:00');
    $expiresAt = $issuedAt->modify('+24 hours');

    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hashed-token', $expiresAt, new FrozenClock($issuedAt));
    $repository->save($transfer);

    $found = $repository->findById('transfer-1');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe('transfer-1')
        ->and($found->auctionId)->toBe('auction-1')
        ->and($found->winningBidId)->toBe('bid-1')
        ->and($found->sellerId)->toBe('101')
        ->and($found->buyerId)->toBe('102')
        ->and($found->qrTokenHash)->toBe('hashed-token')
        ->and($found->issuedAt)->toEqual($issuedAt)
        ->and($found->expiresAt)->toEqual($expiresAt)
        ->and($found->status())->toBe(TransferStatus::Issued)
        ->and($found->sellerConfirmedAt())->toBeNull()
        ->and($found->evidenceRecords())->toBe([]);
});

it('round-trips confirmation timestamps and geolocation for both parties', function () {
    $repository = new EloquentTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);

    $sellerGeo = geoPoint(32.7157, -117.1611);
    $buyerGeo = geoPoint(32.7160, -117.1600);
    $transfer->confirmBySeller($sellerGeo, new FrozenClock(new DateTimeImmutable('2026-10-28 11:00:00')));
    $transfer->confirmByBuyer($buyerGeo, new FrozenClock(new DateTimeImmutable('2026-10-28 11:05:00')));

    $repository->save($transfer);

    $found = $repository->findById('transfer-1');

    expect($found->status())->toBe(TransferStatus::Confirmed)
        ->and($found->sellerConfirmedGeo()->latitude)->toEqualWithDelta($sellerGeo->latitude, 0.0000001)
        ->and($found->sellerConfirmedGeo()->longitude)->toEqualWithDelta($sellerGeo->longitude, 0.0000001)
        ->and($found->buyerConfirmedGeo()->latitude)->toEqualWithDelta($buyerGeo->latitude, 0.0000001)
        ->and($found->confirmedAt())->not->toBeNull();
});

it('returns null when the transfer does not exist', function () {
    expect((new EloquentTransferRepository)->findById('missing'))->toBeNull();
});

it('finds a transfer by auction id', function () {
    $repository = new EloquentTransferRepository;
    $repository->save(Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock));

    $found = $repository->findByAuctionId('auction-1');

    expect($found)->not->toBeNull()->and($found->id)->toBe('transfer-1');
});

it('returns null for findByAuctionId when no transfer exists for that auction', function () {
    expect((new EloquentTransferRepository)->findByAuctionId('auction-missing'))->toBeNull();
});

it('rejects issuing a second transfer for the same auction', function () {
    $repository = new EloquentTransferRepository;
    $repository->save(Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock));

    $second = Transfer::issue('transfer-2', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);

    expect(fn () => $repository->save($second))->toThrow(TransferAlreadyIssuedForAuction::class);
});

it('locks and finds a transfer via findByIdForUpdate', function () {
    $repository = new EloquentTransferRepository;
    $repository->save(Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock));

    $found = $repository->findByIdForUpdate('transfer-1');

    expect($found)->not->toBeNull()->and($found->id)->toBe('transfer-1');
});

it('records evidence and returns it in submission order when finding the transfer', function () {
    $repository = new EloquentTransferRepository;
    $transfer = Transfer::issue('transfer-1', 'auction-1', 'bid-1', '101', '102', 'hash', new DateTimeImmutable('+24 hours'), new FrozenClock);
    $repository->save($transfer);

    $transfer->attachEvidence(TransferEvidenceType::Photo, 'evidence/first.jpg', '101', new FrozenClock(new DateTimeImmutable('2026-10-28 11:00:00')));
    $transfer->attachEvidence(TransferEvidenceType::Photo, 'evidence/second.jpg', '102', new FrozenClock(new DateTimeImmutable('2026-10-28 12:00:00')));

    foreach ($transfer->evidenceRecords() as $record) {
        $repository->recordEvidence('transfer-1', $record);
    }

    $found = $repository->findById('transfer-1');

    expect($found->evidenceRecords())->toHaveCount(2);
    expect($found->evidenceRecords()[0]->storageReference)->toBe('evidence/first.jpg')
        ->and($found->evidenceRecords()[0]->submittedBy)->toBe('101')
        ->and($found->evidenceRecords()[1]->storageReference)->toBe('evidence/second.jpg');
});
