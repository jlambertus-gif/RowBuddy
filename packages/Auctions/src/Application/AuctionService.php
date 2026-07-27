<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Application;

use RowBuddy\Auctions\Auction;
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
 * per ADR-010 §2). Live-proximity enforcement while an auction is already
 * open (ADR-010 §3) is explicitly out of scope until Sprint 4.
 */
final class AuctionService
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly SellerPresenceVerification $presenceVerification,
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

        $auction = Auction::open($auctionId, $queueId, $sellerId, $presenceSessionId, $startingPrice, $this->clock);

        $this->auctions->save($auction);

        foreach ($auction->releaseEvents() as $event) {
            $this->events->publish($event);
        }

        return $auction;
    }
}
