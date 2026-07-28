<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Application;

use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Contracts\AuctionDurationPolicy;
use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Auctions\Contracts\DomainEventPublisher;
use RowBuddy\Auctions\Contracts\SellerPresenceVerification;
use RowBuddy\Auctions\Exceptions\InsufficientConfidenceTier;
use RowBuddy\Auctions\Exceptions\PresenceSessionAlreadyConsumed;
use RowBuddy\Auctions\ValueObjects\ConfidenceTier;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Orchestrates auction creation, gating it on ADR-010 §1's Evidence
 * Verified minimum confidence tier via the ADR-009 {@see SellerPresenceVerification}
 * port. Depends only on domain-facing ports — no Eloquent, no Laravel
 * container — so the gate is testable with plain in-memory fakes, no
 * database.
 *
 * Only the confidence-tier gate lives here (checked once, at creation,
 * per ADR-010 §2). Live-proximity and closing-deadline enforcement while
 * an auction is already open are the concern of LiveProximityChecker and
 * AuctionClosingEvaluator respectively, invoked lazily elsewhere
 * (ADR-011, ADR-013) — not this service.
 *
 * The closing deadline itself (ADR-013 §1) is computed here, via
 * AuctionDurationPolicy, and handed to Auction::open() as an explicit
 * value — this service does not hard-code a duration; it only asks the
 * policy for one.
 */
final class AuctionService
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly SellerPresenceVerification $presenceVerification,
        private readonly AuctionDurationPolicy $durationPolicy,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @throws InsufficientConfidenceTier if the seller's presence/confidence
     *                                    state for this queue does not meet
     *                                    ADR-010's Evidence Verified minimum
     *                                    — including when no verification
     *                                    record exists at all (fail closed)
     * @throws PresenceSessionAlreadyConsumed if this presence session
     *                                        already backs another auction (ADR-009 §4)
     */
    public function open(
        string $auctionId,
        string $queueId,
        string $sellerId,
        string $presenceSessionId,
        Money $startingPrice,
    ): Auction {
        $verification = $this->presenceVerification->verificationFor($sellerId, $queueId);

        if ($verification === null || $verification->tier !== ConfidenceTier::EvidenceVerified) {
            throw InsufficientConfidenceTier::forSellerAndQueue($sellerId, $queueId);
        }

        $durationInSeconds = $this->durationPolicy->durationInSecondsFor($queueId);
        $closesAt = $this->clock->now()->modify("+{$durationInSeconds} seconds");

        $auction = Auction::open($auctionId, $queueId, $sellerId, $presenceSessionId, $startingPrice, $closesAt, $this->clock);

        $this->auctions->save($auction);

        foreach ($auction->releaseEvents() as $event) {
            $this->events->publish($event);
        }

        return $auction;
    }
}
