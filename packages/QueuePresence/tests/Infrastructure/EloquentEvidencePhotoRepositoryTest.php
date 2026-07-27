<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\EloquentEvidencePhotoRepository;
use RowBuddy\QueuePresence\ValueObjects\EvidencePhotoRecord;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('presence_evidence_photos', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('presence_session_id');
        $table->string('storage_reference');
        $table->string('mime_type');
        $table->unsignedBigInteger('size_bytes');
        $table->timestamp('recorded_at');
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('presence_evidence_photos');
});

it('records and finds an evidence photo by id', function () {
    $repository = new EloquentEvidencePhotoRepository;
    $recordedAt = new DateTimeImmutable('2026-08-17 09:00:00');

    $repository->record(new EvidencePhotoRecord(
        'photo-1',
        'session-1',
        'presence-evidence/session-1/abc.jpg',
        'image/jpeg',
        123_456,
        $recordedAt,
    ));

    $found = $repository->findById('photo-1');

    expect($found)->not->toBeNull()
        ->and($found->presenceSessionId)->toBe('session-1')
        ->and($found->storageReference)->toBe('presence-evidence/session-1/abc.jpg')
        ->and($found->mimeType)->toBe('image/jpeg')
        ->and($found->sizeBytes)->toBe(123_456)
        ->and($found->recordedAt->format(DATE_ATOM))->toBe($recordedAt->format(DATE_ATOM));
});

it('returns null when the evidence photo does not exist', function () {
    expect((new EloquentEvidencePhotoRepository)->findById('missing'))->toBeNull();
});
