<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RowBuddy\Notifications\Contracts\TransferParticipantLookup;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Mail\TransferExpiredMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\ValueObjects\NotificationType;
use RowBuddy\Transfers\Events\TransferExpired;

/**
 * The real `TransferExpired` consumer for Phase 7 (ADR-025 §6) — mirrors
 * {@see SendTransferConfirmedNotification}'s shape; content must stay
 * symmetric and blame-free per ADR-018's no-fault posture.
 */
final class SendTransferExpiredNotification implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly TransferParticipantLookup $transferParticipants,
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
    public function handle(TransferExpired $event): void
    {
        $transferId = (string) $event->auditSubjectId();
        $snapshot = $this->transferParticipants->findByTransferId($transferId);

        if ($snapshot === null) {
            throw new NotificationRecipientUnresolved(
                "TransferExpired for transfer [{$transferId}]: no matching transfer found."
            );
        }

        $mailableFactory = fn (string $language) => new TransferExpiredMail($transferId);
        $pushContentFactory = fn (string $language) => (new TransferExpiredMail($transferId))->toPushContent($language);

        $this->pipeline->deliver($transferId, $snapshot->buyerId, NotificationType::TransferExpired, $mailableFactory);
        $this->pipeline->deliver($transferId, $snapshot->sellerId, NotificationType::TransferExpired, $mailableFactory);
        $this->pipeline->deliverPush($transferId, $snapshot->buyerId, NotificationType::TransferExpired, $pushContentFactory);
        $this->pipeline->deliverPush($transferId, $snapshot->sellerId, NotificationType::TransferExpired, $pushContentFactory);
    }
}
