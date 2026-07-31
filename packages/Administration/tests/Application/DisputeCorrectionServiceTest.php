<?php

declare(strict_types=1);

use RowBuddy\Administration\Application\DisputeCorrectionService;
use RowBuddy\Administration\Exceptions\AdministrativeActionReasonRequired;
use RowBuddy\Administration\Exceptions\AdministrativeTargetNotFound;
use RowBuddy\Administration\Tests\Fakes\InMemoryAdminActionLog;
use RowBuddy\Administration\Tests\Fakes\InMemoryDisputeCaseLookup;
use RowBuddy\Administration\ValueObjects\AdministrativeActionType;
use RowBuddy\Administration\ValueObjects\DisputeCaseSnapshot;

function aDisputeCaseSnapshotForCorrectionTest(string $id): DisputeCaseSnapshot
{
    return new DisputeCaseSnapshot(
        id: $id,
        transferId: 'transfer-1',
        auctionId: 'auction-1',
        buyerId: '101',
        sellerId: '102',
        reason: 'Not as described',
        openedAt: new DateTimeImmutable('2026-08-01 00:00:00'),
        status: 'resolved',
        resolutionOutcome: 'release_to_seller',
        refundAmountMinorUnits: null,
        refundAmountCurrency: null,
        resolvedBy: '999',
        resolutionNotes: 'Evidence supported the seller.',
        evidenceFoundFraudulent: false,
        resolvedAt: new DateTimeImmutable('2026-08-03 00:00:00'),
        evidence: [],
    );
}

it('records a correction and logs exactly one admin action, mutating nothing about the dispute itself', function () {
    $disputes = new InMemoryDisputeCaseLookup;
    $disputes->disputes['dispute-1'] = aDisputeCaseSnapshotForCorrectionTest('dispute-1');
    $actions = new InMemoryAdminActionLog;
    $service = new DisputeCorrectionService($disputes, $actions);

    $service->recordCorrection('dispute-1', '999', 'On review, the refund should have been split 50/50.');

    expect($actions->recorded)->toHaveCount(1)
        ->and($actions->recorded[0]['type'])->toBe(AdministrativeActionType::DisputeCorrectionRecorded)
        ->and($actions->recorded[0]['adminId'])->toBe('999')
        ->and($actions->recorded[0]['targetType'])->toBe('dispute')
        ->and($actions->recorded[0]['targetId'])->toBe('dispute-1')
        ->and($actions->recorded[0]['reason'])->toBe('On review, the refund should have been split 50/50.')
        ->and($actions->recorded[0]['previousState'])->toBeNull()
        ->and($actions->recorded[0]['newState'])->toBeNull();
});

it('rejects recording a correction with a blank note', function () {
    $disputes = new InMemoryDisputeCaseLookup;
    $disputes->disputes['dispute-1'] = aDisputeCaseSnapshotForCorrectionTest('dispute-1');
    $service = new DisputeCorrectionService($disputes, new InMemoryAdminActionLog);

    expect(fn () => $service->recordCorrection('dispute-1', '999', '   '))
        ->toThrow(AdministrativeActionReasonRequired::class);
});

it('rejects recording a correction against a dispute that does not exist', function () {
    $service = new DisputeCorrectionService(new InMemoryDisputeCaseLookup, new InMemoryAdminActionLog);

    expect(fn () => $service->recordCorrection('missing', '999', 'note'))
        ->toThrow(AdministrativeTargetNotFound::class);
});

it('records multiple corrections against the same dispute independently', function () {
    $disputes = new InMemoryDisputeCaseLookup;
    $disputes->disputes['dispute-1'] = aDisputeCaseSnapshotForCorrectionTest('dispute-1');
    $actions = new InMemoryAdminActionLog;
    $service = new DisputeCorrectionService($disputes, $actions);

    $service->recordCorrection('dispute-1', '999', 'First correction note.');
    $service->recordCorrection('dispute-1', '998', 'Second, independent correction note.');

    expect($actions->recorded)->toHaveCount(2)
        ->and($actions->recorded[0]['reason'])->toBe('First correction note.')
        ->and($actions->recorded[1]['reason'])->toBe('Second, independent correction note.');
});
