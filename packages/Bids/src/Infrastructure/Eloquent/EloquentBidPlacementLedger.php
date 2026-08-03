<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Infrastructure\Eloquent;

use Illuminate\Database\UniqueConstraintViolationException;
use RowBuddy\Bids\Contracts\BidPlacementLedger;
use RowBuddy\Bids\Exceptions\BidIdempotencyKeyReused;
use RowBuddy\Bids\Exceptions\BidPlacementInProgress;
use RowBuddy\Bids\ValueObjects\BidClaimResult;
use RowBuddy\Bids\ValueObjects\BidRejectionReason;

final class EloquentBidPlacementLedger implements BidPlacementLedger
{
    public function claim(string $bidderId, string $idempotencyKey, string $auctionId, string $requestFingerprint): BidClaimResult
    {
        try {
            BidPlacementClaimModel::query()->create([
                'bidder_id' => $bidderId,
                'idempotency_key' => $idempotencyKey,
                'auction_id' => $auctionId,
                'request_fingerprint' => $requestFingerprint,
            ]);

            return BidClaimResult::fresh();
        } catch (UniqueConstraintViolationException) {
            return $this->resolveExistingClaim($bidderId, $idempotencyKey, $requestFingerprint);
        }
    }

    public function recordAccepted(string $bidderId, string $idempotencyKey, string $bidId): void
    {
        BidPlacementClaimModel::query()
            ->where('bidder_id', $bidderId)
            ->where('idempotency_key', $idempotencyKey)
            ->update(['outcome' => 'accepted', 'bid_id' => $bidId]);
    }

    public function recordRejected(string $bidderId, string $idempotencyKey, BidRejectionReason $reason): void
    {
        BidPlacementClaimModel::query()
            ->where('bidder_id', $bidderId)
            ->where('idempotency_key', $idempotencyKey)
            ->update(['outcome' => 'rejected', 'rejection_reason' => $reason->name]);
    }

    public function release(string $bidderId, string $idempotencyKey): void
    {
        BidPlacementClaimModel::query()
            ->where('bidder_id', $bidderId)
            ->where('idempotency_key', $idempotencyKey)
            ->delete();
    }

    private function resolveExistingClaim(string $bidderId, string $idempotencyKey, string $requestFingerprint): BidClaimResult
    {
        /** @var BidPlacementClaimModel|null $existing */
        $existing = BidPlacementClaimModel::query()
            ->where('bidder_id', $bidderId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing === null) {
            // The row we just failed to insert is gone by the time we
            // re-read it (e.g. release() ran concurrently) — safe to treat
            // exactly like a fresh claim attempt.
            return BidClaimResult::fresh();
        }

        if ($existing->request_fingerprint !== $requestFingerprint) {
            throw BidIdempotencyKeyReused::forKey($idempotencyKey);
        }

        if ($existing->outcome === null) {
            throw BidPlacementInProgress::forKey($idempotencyKey);
        }

        if ($existing->outcome === 'accepted' && $existing->bid_id !== null) {
            return BidClaimResult::resolvedAccepted($existing->bid_id);
        }

        return BidClaimResult::resolvedRejected($this->reasonFromName((string) $existing->rejection_reason));
    }

    private function reasonFromName(string $name): BidRejectionReason
    {
        return match ($name) {
            'AuctionNotFound' => BidRejectionReason::AuctionNotFound,
            'AuctionNotOpenForBidding' => BidRejectionReason::AuctionNotOpenForBidding,
            'CurrencyMismatch' => BidRejectionReason::CurrencyMismatch,
            'BidTooLow' => BidRejectionReason::BidTooLow,
            'SellerCannotBidOnOwnAuction' => BidRejectionReason::SellerCannotBidOnOwnAuction,
            'BidderAccountSuspended' => BidRejectionReason::BidderAccountSuspended,
            default => throw new \RuntimeException("Unknown cached bid-rejection reason [{$name}]."),
        };
    }
}
