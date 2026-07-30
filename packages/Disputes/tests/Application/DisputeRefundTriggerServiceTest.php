<?php

declare(strict_types=1);

use RowBuddy\Disputes\Application\DisputeRefundTriggerService;
use RowBuddy\Disputes\Tests\Fakes\FakePaymentRefundGateway;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

it('calls Payments for RefundToBuyer with the given amount and reason', function () {
    $gateway = new FakePaymentRefundGateway;
    $service = new DisputeRefundTriggerService($gateway);
    $amount = new Money(11000, new Currency('USD'));

    $service->handle('dispute-1', 'auction-1', DisputeResolutionOutcome::RefundToBuyer, $amount, 'buyer is correct');

    expect($gateway->refundCalls)->toBe([
        ['auctionId' => 'auction-1', 'disputeId' => 'dispute-1', 'amount' => $amount, 'reason' => 'buyer is correct'],
    ]);
});

it('calls Payments for Split with the given (smaller) amount and reason', function () {
    $gateway = new FakePaymentRefundGateway;
    $service = new DisputeRefundTriggerService($gateway);
    $amount = new Money(5000, new Currency('USD'));

    $service->handle('dispute-1', 'auction-1', DisputeResolutionOutcome::Split, $amount, 'partial fault on both sides');

    expect($gateway->refundCalls)->toBe([
        ['auctionId' => 'auction-1', 'disputeId' => 'dispute-1', 'amount' => $amount, 'reason' => 'partial fault on both sides'],
    ]);
});

it('never calls Payments for ReleaseToSeller', function () {
    $gateway = new FakePaymentRefundGateway;
    $service = new DisputeRefundTriggerService($gateway);

    $service->handle('dispute-1', 'auction-1', DisputeResolutionOutcome::ReleaseToSeller, null, 'evidence supports the seller');

    expect($gateway->refundCalls)->toBe([]);
});

it('never calls Payments for Cancelled', function () {
    $gateway = new FakePaymentRefundGateway;
    $service = new DisputeRefundTriggerService($gateway);

    $service->handle('dispute-1', 'auction-1', DisputeResolutionOutcome::Cancelled, null, 'buyer withdrew the claim');

    expect($gateway->refundCalls)->toBe([]);
});

it('defensively no-ops for RefundToBuyer with a null amount, rather than forwarding a null', function () {
    $gateway = new FakePaymentRefundGateway;
    $service = new DisputeRefundTriggerService($gateway);

    $service->handle('dispute-1', 'auction-1', DisputeResolutionOutcome::RefundToBuyer, null, 'reason');

    expect($gateway->refundCalls)->toBe([]);
});

it('forwards every invocation unconditionally — it carries no de-duplication logic of its own', function () {
    // DisputeRefundTriggerService is a stateless forwarder, deliberately:
    // it has no visibility into whether Payments already applied a given
    // refund. Calling it twice here forwards twice, by design — safety
    // against duplicate execution is Payments' PaymentCaptureService's
    // job, proven by its own "retrying the same refund after it already
    // succeeded only calls Stripe once" test
    // (packages/Payments/tests/Application/PaymentCaptureServiceTest.php),
    // and, end-to-end through the real container wiring, by
    // apps/web/tests/Feature/DisputeRefundTriggerWiringTest.php.
    $gateway = new FakePaymentRefundGateway;
    $service = new DisputeRefundTriggerService($gateway);
    $amount = new Money(11000, new Currency('USD'));

    $service->handle('dispute-1', 'auction-1', DisputeResolutionOutcome::RefundToBuyer, $amount, 'reason');
    $service->handle('dispute-1', 'auction-1', DisputeResolutionOutcome::RefundToBuyer, $amount, 'reason');

    expect($gateway->refundCalls)->toHaveCount(2);
});
