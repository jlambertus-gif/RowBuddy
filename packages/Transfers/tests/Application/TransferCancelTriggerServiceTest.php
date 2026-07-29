<?php

declare(strict_types=1);

use RowBuddy\Transfers\Application\TransferCancelTriggerService;
use RowBuddy\Transfers\Tests\Fakes\FakePaymentCaptureGateway;

it('cancels the authorization for the given auction and reason', function () {
    $captureGateway = new FakePaymentCaptureGateway;
    $service = new TransferCancelTriggerService($captureGateway);

    $service->handle('auction-1', 'transfer window expired with no confirmation');

    expect($captureGateway->cancelCalls)->toBe([
        ['auctionId' => 'auction-1', 'reason' => 'transfer window expired with no confirmation'],
    ]);
});

it('can be invoked more than once for the same auction', function () {
    $captureGateway = new FakePaymentCaptureGateway;
    $service = new TransferCancelTriggerService($captureGateway);

    $service->handle('auction-1', 'transfer window expired with no confirmation');
    $service->handle('auction-1', 'transfer window expired with no confirmation');

    expect($captureGateway->cancelCalls)->toHaveCount(2);
});
