<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Tests\Fakes;

use RowBuddy\Disputes\Contracts\PaymentRefundGateway;
use RowBuddy\SharedKernel\ValueObjects\Money;

final class FakePaymentRefundGateway implements PaymentRefundGateway
{
    /** @var list<array{auctionId: string, disputeId: string, amount: Money, reason: string}> */
    public array $refundCalls = [];

    public function refund(string $auctionId, string $disputeId, Money $amount, string $reason): void
    {
        $this->refundCalls[] = [
            'auctionId' => $auctionId,
            'disputeId' => $disputeId,
            'amount' => $amount,
            'reason' => $reason,
        ];
    }
}
