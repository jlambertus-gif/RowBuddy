<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Payments\Application\PaymentCaptureService;
use RowBuddy\Transfers\Contracts\PaymentCaptureGateway;

/**
 * Bridges Transfers' {@see PaymentCaptureGateway} port to Payments' own
 * {@see PaymentCaptureService} (ADR-019 §4) — this class is the one
 * place in the codebase allowed to depend on both packages at once,
 * because apps/web is the composition root, not either module itself
 * (see tests/Architecture/ModuleBoundaryTest.php). A thin passthrough:
 * `PaymentCaptureService` already fully owns and audits the captured/
 * capture-failed/cancelled outcome on its own side — Transfers only
 * needs to know that it asked, not what Stripe actually did.
 */
final class EloquentPaymentCaptureGateway implements PaymentCaptureGateway
{
    public function __construct(private readonly PaymentCaptureService $paymentCaptureService) {}

    public function capture(string $auctionId): void
    {
        $this->paymentCaptureService->capture($auctionId);
    }

    public function cancel(string $auctionId, string $reason): void
    {
        $this->paymentCaptureService->cancel($auctionId, $reason);
    }
}
