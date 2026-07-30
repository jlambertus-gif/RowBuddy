<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RowBuddy\Notifications\Contracts\WinningBidderLookup;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Mail\PaymentAuthorizationFailedMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\ValueObjects\NotificationType;
use RowBuddy\Payments\Events\PaymentAuthorizationFailed;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The real `PaymentAuthorizationFailed` consumer for Phase 7 (ADR-025
 * §6) — reuses the same {@see WinningBidderLookup} hop `AuctionWon`
 * needs, since this event also only carries `winningBidId`, not the
 * buyer's own id. Ledger key is this event's own `auditSubjectId()`
 * (`paymentIntentId`) — the stable identity for at-most-one failed
 * authorization occurrence.
 */
final class SendPaymentAuthorizationFailedNotification implements ShouldQueue
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
    public function handle(PaymentAuthorizationFailed $event): void
    {
        $payload = $event->payload();
        $auctionId = (string) $payload['auction_id'];
        $winningBidId = (string) $payload['winning_bid_id'];

        $recipientId = $this->winningBidders->findBidderIdByBidId($winningBidId);

        if ($recipientId === null) {
            throw new NotificationRecipientUnresolved(
                "PaymentAuthorizationFailed for auction [{$auctionId}]: no bidder found for winning bid [{$winningBidId}]."
            );
        }

        $amount = new Money(
            (int) $payload['amount_minor_units'],
            new Currency((string) $payload['amount_currency']),
        );

        $this->pipeline->deliver(
            (string) $event->auditSubjectId(),
            $recipientId,
            NotificationType::PaymentAuthorizationFailed,
            fn (string $language) => new PaymentAuthorizationFailedMail($auctionId, $amount, $language),
        );
    }
}
