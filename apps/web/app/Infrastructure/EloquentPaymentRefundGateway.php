<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Disputes\Contracts\PaymentRefundGateway;
use RowBuddy\Payments\Application\PaymentCaptureService;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Bridges Disputes' {@see PaymentRefundGateway} port to Payments' own
 * {@see PaymentCaptureService} (ADR-022) — this class is the one place
 * in the codebase allowed to depend on both packages at once, because
 * apps/web is the composition root, not either module itself (see
 * tests/Architecture/ModuleBoundaryTest.php). A thin passthrough,
 * mirroring EloquentPaymentCaptureGateway's identical shape one hop
 * further.
 */
final class EloquentPaymentRefundGateway implements PaymentRefundGateway
{
    public function __construct(private readonly PaymentCaptureService $paymentCaptureService) {}

    public function refund(string $auctionId, string $disputeId, Money $amount, string $reason): void
    {
        $this->paymentCaptureService->refund($auctionId, $disputeId, $amount, $reason);
    }
}
