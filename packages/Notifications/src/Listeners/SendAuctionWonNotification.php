<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Listeners;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Notifications\Contracts\NotificationDeliveryLedger;
use RowBuddy\Notifications\Contracts\RecipientContactLookup;
use RowBuddy\Notifications\Contracts\RecipientLocalePreferenceLookup;
use RowBuddy\Notifications\Contracts\WinningBidderLookup;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Mail\AuctionWonMail;
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
 * §7): the ledger is checked before sending and recorded only after a
 * real send succeeds, keyed by the auction's own id (an `AuctionWon`
 * fires at most once per auction, so `auctionId` is already the stable
 * logical identity this event's occurrence needs — no separate event-id
 * field exists anywhere in this codebase's domain events).
 */
final class SendAuctionWonNotification implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly WinningBidderLookup $winningBidders,
        private readonly RecipientContactLookup $contacts,
        private readonly RecipientLocalePreferenceLookup $localePreferences,
        private readonly NotificationDeliveryLedger $ledger,
        private readonly Mailer $mailer,
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

        if ($this->ledger->alreadyDelivered($auctionId, $recipientId, NotificationType::AuctionWon)) {
            return;
        }

        $email = $this->contacts->findEmailById($recipientId);

        if ($email === null) {
            throw new NotificationRecipientUnresolved(
                "AuctionWon for auction [{$auctionId}]: no email on file for recipient [{$recipientId}]."
            );
        }

        $language = $this->localePreferences->findByRecipientId($recipientId)?->language ?? 'en';

        $winningAmount = new Money(
            (int) $payload['winning_amount_minor_units'],
            new Currency((string) $payload['winning_amount_currency']),
        );

        $mailable = (new AuctionWonMail($auctionId, $winningAmount, $language))->locale($language);

        $this->mailer->to($email)->send($mailable);

        $this->ledger->recordDelivered($auctionId, $recipientId, NotificationType::AuctionWon);
    }
}
