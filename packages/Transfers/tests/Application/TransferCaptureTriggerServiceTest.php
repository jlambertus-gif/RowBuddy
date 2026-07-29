<?php

declare(strict_types=1);

use RowBuddy\Transfers\Application\TransferCaptureTriggerService;
use RowBuddy\Transfers\Tests\Fakes\FakePaymentCaptureGateway;

it('captures payment for the given auction', function () {
    $captureGateway = new FakePaymentCaptureGateway;
    $service = new TransferCaptureTriggerService($captureGateway);

    $service->handle('auction-1');

    expect($captureGateway->captureCalls)->toBe(['auction-1']);
});

it('can be invoked more than once for the same auction', function () {
    $captureGateway = new FakePaymentCaptureGateway;
    $service = new TransferCaptureTriggerService($captureGateway);

    $service->handle('auction-1');
    $service->handle('auction-1');

    expect($captureGateway->captureCalls)->toBe(['auction-1', 'auction-1']);
});
