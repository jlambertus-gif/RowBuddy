<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\Transfers\Application\TransferEvidenceSubmissionService;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\Transfer;

uses(RefreshDatabase::class);

it('submits evidence end-to-end through the real container-wired storage and metadata stripper', function () {
    Storage::fake('local');

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

    $image = imagecreatetruecolor(4, 4);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 40, 40));
    ob_start();
    imagejpeg($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    app(TransferEvidenceSubmissionService::class)->submitPhoto($transferId, '1', $contents);

    $found = $transfers->findById($transferId);
    expect($found->evidenceRecords())->toHaveCount(1);

    $storageReference = $found->evidenceRecords()[0]->storageReference;
    Storage::disk('local')->assertExists($storageReference);
});
