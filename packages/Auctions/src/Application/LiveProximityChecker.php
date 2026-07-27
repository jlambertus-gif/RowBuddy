<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Application;

use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Auctions\Contracts\DomainEventPublisher;
use RowBuddy\Auctions\Contracts\SellerPresenceVerification;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Enforces ADR-011's live-proximity policy. Invoked only by domain
 * commands that act on an already-existing active auction (bid
 * placement, administrative actions, explicit lifecycle operations, and
 * any future active-auction command) — never by a plain read; reading an
 * auction must stay side-effect-free. There is deliberately no scheduler
 * or background job driving this — see ADR-011 §5 for the accepted
 * limitation that follows from that choice.
 */
final class LiveProximityChecker
{
    private const STALE_AFTER_SECONDS = 15 * 60;

    private const GRACE_PERIOD_SECONDS = 10 * 60;

    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly SellerPresenceVerification $presenceVerification,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    public function check(Auction $auction): Auction
    {
        if ($auction->status() !== AuctionStatus::Open && $auction->status() !== AuctionStatus::Closing) {
            return $auction;
        }

        $verification = $this->presenceVerification->verificationFor($auction->sellerId, $auction->queueId);

        if ($verification === null || ! $verification->sessionActive) {
            $auction->cancelForProximityLoss($this->clock);
            $this->persistAndPublish($auction);

            return $auction;
        }

        $now = $this->clock->now();
        $lastPingAt = $verification->lastWithinGeofencePingAt;
        $isStale = $lastPingAt === null || ($now->getTimestamp() - $lastPingAt->getTimestamp()) > self::STALE_AFTER_SECONDS;
        $proximityAtRiskSince = $auction->proximityAtRiskSince();

        if ($isStale) {
            if ($proximityAtRiskSince === null) {
                $auction->flagProximityAtRisk($this->clock);
                $this->persistAndPublish($auction);
            } elseif (($now->getTimestamp() - $proximityAtRiskSince->getTimestamp()) > self::GRACE_PERIOD_SECONDS) {
                $auction->cancelForProximityLoss($this->clock);
                $this->persistAndPublish($auction);
            }

            return $auction;
        }

        if ($proximityAtRiskSince !== null) {
            $auction->restoreProximity($this->clock);
            $this->persistAndPublish($auction);
        }

        return $auction;
    }

    private function persistAndPublish(Auction $auction): void
    {
        $this->auctions->save($auction);

        foreach ($auction->releaseEvents() as $event) {
            $this->events->publish($event);
        }
    }
}
