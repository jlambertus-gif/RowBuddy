<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Tests\Fakes;

use RowBuddy\Bids\Contracts\BidPlacementLedger;
use RowBuddy\Bids\Exceptions\BidIdempotencyKeyReused;
use RowBuddy\Bids\Exceptions\BidPlacementInProgress;
use RowBuddy\Bids\ValueObjects\BidClaimResult;
use RowBuddy\Bids\ValueObjects\BidRejectionReason;

final class FakeBidPlacementLedger implements BidPlacementLedger
{
    /** @var array<string, array{auctionId: string, fingerprint: string, outcome: ?string, bidId: ?string, reason: ?BidRejectionReason}> */
    public array $claims = [];

    public int $claimCalls = 0;

    public function claim(string $bidderId, string $idempotencyKey, string $auctionId, string $requestFingerprint): BidClaimResult
    {
        $this->claimCalls++;
        $key = $this->key($bidderId, $idempotencyKey);

        if (! isset($this->claims[$key])) {
            $this->claims[$key] = [
                'auctionId' => $auctionId,
                'fingerprint' => $requestFingerprint,
                'outcome' => null,
                'bidId' => null,
                'reason' => null,
            ];

            return BidClaimResult::fresh();
        }

        $existing = $this->claims[$key];

        if ($existing['fingerprint'] !== $requestFingerprint) {
            throw BidIdempotencyKeyReused::forKey($idempotencyKey);
        }

        if ($existing['outcome'] === null) {
            throw BidPlacementInProgress::forKey($idempotencyKey);
        }

        return $existing['outcome'] === 'accepted'
            ? BidClaimResult::resolvedAccepted((string) $existing['bidId'])
            : BidClaimResult::resolvedRejected($existing['reason']);
    }

    public function recordAccepted(string $bidderId, string $idempotencyKey, string $bidId): void
    {
        $key = $this->key($bidderId, $idempotencyKey);
        $this->claims[$key]['outcome'] = 'accepted';
        $this->claims[$key]['bidId'] = $bidId;
    }

    public function recordRejected(string $bidderId, string $idempotencyKey, BidRejectionReason $reason): void
    {
        $key = $this->key($bidderId, $idempotencyKey);
        $this->claims[$key]['outcome'] = 'rejected';
        $this->claims[$key]['reason'] = $reason;
    }

    public function release(string $bidderId, string $idempotencyKey): void
    {
        unset($this->claims[$this->key($bidderId, $idempotencyKey)]);
    }

    private function key(string $bidderId, string $idempotencyKey): string
    {
        return $bidderId.'|'.$idempotencyKey;
    }
}
