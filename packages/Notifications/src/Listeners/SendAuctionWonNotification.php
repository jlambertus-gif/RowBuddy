<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Notifications\Contracts\WinningBidderLookup;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Mail\AuctionWonMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\ValueObjects\NotificationType;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The real `AuctionWon` consumer for Phase 7 (ADR-025 §6) — this
 * codebase's first real Laravel `Event::listen()`-driven, queued
 * cross-module reaction; every prior phase's reaction was a directly
 * invoked application service. Registered against the real, committed
 * `AuctionWon` event, never against `Auction`'s own status.
 *
 * `$tries`/backoff are explicit and finite (ADR-025 §11) — once
 * exhausted, the job fails into Laravel's own `failed_jobs`, with no
 * bespoke failure tracking of any kind. Delivery is idempotent (ADR-025
 * §7), via {@see NotificationDeliveryPipeline}, keyed by the auction's
 * own id (an `AuctionWon` fires at most once per auction, so `auctionId`
 * — this event's own `auditSubjectId()` — is already the stable logical
 * identity this event's occurrence needs).
 */
final class SendAuctionWonNotification implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly WinningBidderLookup $winningBidders,
        private readonly NotificationDeliveryPipeline $pipeline,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @throws NotificationRecipientUnresolved
     */
    public function handle(AuctionWon $event): void
    {
        $payload = $event->payload();
        $auctionId = (string) $payload['auction_id'];
        $winningBidId = (string) $payload['winning_bid_id'];

        $recipientId = $this->winningBidders->findBidderIdByBidId($winningBidId);

        if ($recipientId === null) {
            throw new NotificationRecipientUnresolved(
                "AuctionWon for auction [{$auctionId}]: no bidder found for winning bid [{$winningBidId}]."
            );
        }

        $winningAmount = new Money(
            (int) $payload['winning_amount_minor_units'],
            new Currency((string) $payload['winning_amount_currency']),
        );

        $this->pipeline->deliver(
            $auctionId,
            $recipientId,
            NotificationType::AuctionWon,
            fn (string $language) => new AuctionWonMail($auctionId, $winningAmount, $language),
        );
    }
}
