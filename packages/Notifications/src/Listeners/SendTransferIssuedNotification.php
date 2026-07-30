<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RowBuddy\Notifications\Mail\TransferIssuedMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\ValueObjects\NotificationType;
use RowBuddy\Transfers\Events\TransferIssued;

/**
 * The real `TransferIssued` consumer for Phase 7 (ADR-025 §6) — notifies
 * both parties (ADR-025 §10: rendered independently per recipient).
 * Unlike `TransferConfirmed`/`TransferExpired`/`TransferCancelled`,
 * `TransferIssued` carries `buyerId`/`sellerId` directly, so no
 * additional read port is needed to resolve its recipients.
 */
final class SendTransferIssuedNotification implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly NotificationDeliveryPipeline $pipeline,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(TransferIssued $event): void
    {
        $payload = $event->payload();
        $transferId = (string) $payload['transfer_id'];
        $buyerId = (string) $payload['buyer_id'];
        $sellerId = (string) $payload['seller_id'];

        $mailableFactory = fn (string $language) => new TransferIssuedMail($transferId);

        $this->pipeline->deliver($transferId, $buyerId, NotificationType::TransferIssued, $mailableFactory);
        $this->pipeline->deliver($transferId, $sellerId, NotificationType::TransferIssued, $mailableFactory);
    }
}
