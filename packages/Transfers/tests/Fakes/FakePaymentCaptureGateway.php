<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Tests\Fakes;

use RowBuddy\Transfers\Contracts\PaymentCaptureGateway;

final class FakePaymentCaptureGateway implements PaymentCaptureGateway
{
    /** @var list<string> */
    public array $captureCalls = [];

    /** @var list<array{auctionId: string, reason: string}> */
    public array $cancelCalls = [];

    public function capture(string $auctionId): void
    {
        $this->captureCalls[] = $auctionId;
    }

    public function cancel(string $auctionId, string $reason): void
    {
        $this->cancelCalls[] = ['auctionId' => $auctionId, 'reason' => $reason];
    }
}
