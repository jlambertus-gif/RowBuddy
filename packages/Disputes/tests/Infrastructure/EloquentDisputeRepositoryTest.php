<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Disputes\Dispute;
use RowBuddy\Disputes\Exceptions\DisputeAlreadyExistsForTransfer;
use RowBuddy\Disputes\Infrastructure\Eloquent\EloquentDisputeRepository;
use RowBuddy\Disputes\ValueObjects\DisputeEvidenceType;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\Disputes\ValueObjects\DisputeStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('disputes', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('transfer_id')->unique();
        $table->string('auction_id');
        $table->unsignedBigInteger('buyer_id');
        $table->unsignedBigInteger('seller_id');
        $table->text('reason');
        $table->timestamp('opened_at');
        $table->string('status');
        $table->string('resolution_outcome')->nullable();
        $table->bigInteger('refund_amount_minor_units')->nullable();
        $table->string('refund_amount_currency', 3)->nullable();
        $table->unsignedBigInteger('resolved_by')->nullable();
        $table->text('resolution_notes')->nullable();
        $table->boolean('evidence_found_fraudulent')->default(false);
        $table->timestamp('resolved_at')->nullable();
        $table->timestamps();
    });

    Capsule::schema()->create('dispute_evidence', function (Blueprint $table) {
        $table->id();
        $table->string('dispute_id');
        $table->string('type');
        $table->text('storage_reference');
        $table->unsignedBigInteger('submitted_by');
        $table->timestamp('submitted_at');
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('dispute_evidence');
    Capsule::schema()->dropIfExists('disputes');
});

it('saves and finds a dispute by id, round-tripping every field', function () {
    $repository = new EloquentDisputeRepository;
    $openedAt = new DateTimeImmutable('2026-08-05 10:00:00');

    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'Not as described', new FrozenClock($openedAt));
    $repository->save($dispute);

    $found = $repository->findById('dispute-1');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe('dispute-1')
        ->and($found->transferId)->toBe('transfer-1')
        ->and($found->auctionId)->toBe('auction-1')
        ->and($found->buyerId)->toBe('101')
        ->and($found->sellerId)->toBe('102')
        ->and($found->reason)->toBe('Not as described')
        ->and($found->openedAt)->toEqual($openedAt)
        ->and($found->status())->toBe(DisputeStatus::Opened)
        ->and($found->resolutionOutcome())->toBeNull()
        ->and($found->refundAmount())->toBeNull()
        ->and($found->evidenceRecords())->toBe([]);
});

it('round-trips a resolved dispute with a refund amount', function () {
    $repository = new EloquentDisputeRepository;
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);

    $dispute->resolve(
        DisputeResolutionOutcome::Split,
        new Money(5000, new Currency('USD')),
        '999',
        'Partial fault on both sides',
        true,
        new FrozenClock(new DateTimeImmutable('2026-08-07 10:00:00')),
    );

    $repository->save($dispute);

    $found = $repository->findById('dispute-1');

    expect($found->status())->toBe(DisputeStatus::Resolved)
        ->and($found->resolutionOutcome())->toBe(DisputeResolutionOutcome::Split)
        ->and($found->refundAmount()->minorUnits)->toBe(5000)
        ->and((string) $found->refundAmount()->currency)->toBe('USD')
        ->and($found->resolvedBy())->toBe('999')
        ->and($found->resolutionNotes())->toBe('Partial fault on both sides')
        ->and($found->evidenceFoundFraudulent())->toBeTrue()
        ->and($found->resolvedAt())->not->toBeNull();
});

it('returns null when the dispute does not exist', function () {
    expect((new EloquentDisputeRepository)->findById('missing'))->toBeNull();
});

it('finds a dispute by transfer id', function () {
    $repository = new EloquentDisputeRepository;
    $repository->save(Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock));

    $found = $repository->findByTransferId('transfer-1');

    expect($found)->not->toBeNull()->and($found->id)->toBe('dispute-1');
});

it('returns null for findByTransferId when no dispute exists for that transfer', function () {
    expect((new EloquentDisputeRepository)->findByTransferId('transfer-missing'))->toBeNull();
});

it('rejects opening a second dispute for the same transfer', function () {
    $repository = new EloquentDisputeRepository;
    $repository->save(Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock));

    $second = Dispute::open('dispute-2', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);

    expect(fn () => $repository->save($second))->toThrow(DisputeAlreadyExistsForTransfer::class);
});

it('locks and finds a dispute via findByIdForUpdate', function () {
    $repository = new EloquentDisputeRepository;
    $repository->save(Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock));

    $found = $repository->findByIdForUpdate('dispute-1');

    expect($found)->not->toBeNull()->and($found->id)->toBe('dispute-1');
});

it('finds every dispute, most recently opened first', function () {
    $repository = new EloquentDisputeRepository;
    $repository->save(Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock(new DateTimeImmutable('2026-08-01 00:00:00'))));
    $repository->save(Dispute::open('dispute-2', 'transfer-2', 'auction-2', '103', '104', 'reason', new FrozenClock(new DateTimeImmutable('2026-08-03 00:00:00'))));
    $repository->save(Dispute::open('dispute-3', 'transfer-3', 'auction-3', '105', '106', 'reason', new FrozenClock(new DateTimeImmutable('2026-08-02 00:00:00'))));

    $all = $repository->findAll();

    expect($all)->toHaveCount(3)
        ->and(array_map(fn (Dispute $d): string => $d->id, $all))->toBe(['dispute-2', 'dispute-3', 'dispute-1']);
});

it('returns an empty list when no disputes exist', function () {
    expect((new EloquentDisputeRepository)->findAll())->toBe([]);
});

it('records evidence and returns it in submission order when finding the dispute', function () {
    $repository = new EloquentDisputeRepository;
    $dispute = Dispute::open('dispute-1', 'transfer-1', 'auction-1', '101', '102', 'reason', new FrozenClock);
    $repository->save($dispute);

    $dispute->attachEvidence(DisputeEvidenceType::Photo, 'disputes/dispute-1/first.jpg', '101', new FrozenClock(new DateTimeImmutable('2026-08-05 11:00:00')));
    $dispute->attachEvidence(DisputeEvidenceType::WrittenStatement, 'The buyer confirmed receipt.', '102', new FrozenClock(new DateTimeImmutable('2026-08-05 12:00:00')));

    foreach ($dispute->evidenceRecords() as $record) {
        $repository->recordEvidence('dispute-1', $record);
    }

    $found = $repository->findById('dispute-1');

    expect($found->evidenceRecords())->toHaveCount(2);
    expect($found->evidenceRecords()[0]->storageReference)->toBe('disputes/dispute-1/first.jpg')
        ->and($found->evidenceRecords()[0]->submittedBy)->toBe('101')
        ->and($found->evidenceRecords()[1]->storageReference)->toBe('The buyer confirmed receipt.')
        ->and($found->evidenceRecords()[1]->type)->toBe(DisputeEvidenceType::WrittenStatement);
});
