<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RowBuddy\Notifications\Contracts\TransferParticipantLookup;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Mail\TransferConfirmedMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\ValueObjects\NotificationType;
use RowBuddy\Transfers\Events\TransferConfirmed;

/**
 * The real `TransferConfirmed` consumer for Phase 7 (ADR-025 §6) —
 * unlike `TransferIssued`, this event carries only `transferId`, so
 * resolving both recipients needs {@see TransferParticipantLookup}.
 */
final class SendTransferConfirmedNotification implements ShouldQueue
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
    public function handle(TransferConfirmed $event): void
    {
        $transferId = (string) $event->auditSubjectId();
        $snapshot = $this->transferParticipants->findByTransferId($transferId);

        if ($snapshot === null) {
            throw new NotificationRecipientUnresolved(
                "TransferConfirmed for transfer [{$transferId}]: no matching transfer found."
            );
        }

        $mailableFactory = fn (string $language) => new TransferConfirmedMail($transferId);

        $this->pipeline->deliver($transferId, $snapshot->buyerId, NotificationType::TransferConfirmed, $mailableFactory);
        $this->pipeline->deliver($transferId, $snapshot->sellerId, NotificationType::TransferConfirmed, $mailableFactory);
    }
}
