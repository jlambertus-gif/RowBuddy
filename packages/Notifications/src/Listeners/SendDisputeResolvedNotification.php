<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RowBuddy\Disputes\Events\DisputeResolved;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\Notifications\Contracts\DisputeParticipantLookup;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Mail\DisputeResolvedMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\ValueObjects\NotificationType;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The real `DisputeResolved` consumer for Phase 7 (ADR-025 §6) —
 * `DisputeResolved` carries only `disputeId` and resolution facts, never
 * `buyerId`/`sellerId`, so resolving both recipients needs
 * {@see DisputeParticipantLookup}. Reports only the outcome and refund
 * amount (if any) — never `resolutionNotes` or `evidenceFoundFraudulent`.
 */
final class SendDisputeResolvedNotification implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly DisputeParticipantLookup $disputeParticipants,
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
    public function handle(DisputeResolved $event): void
    {
        $payload = $event->payload();
        $disputeId = (string) $payload['dispute_id'];
        $outcome = DisputeResolutionOutcome::from((string) $payload['outcome']);

        $refundAmount = null;

        if ($payload['refund_amount_minor_units'] !== null) {
            $refundAmount = new Money(
                (int) $payload['refund_amount_minor_units'],
                new Currency((string) $payload['refund_amount_currency']),
            );
        }

        $snapshot = $this->disputeParticipants->findByDisputeId($disputeId);

        if ($snapshot === null) {
            throw new NotificationRecipientUnresolved(
                "DisputeResolved for dispute [{$disputeId}]: no matching dispute found."
            );
        }

        $mailableFactory = fn (string $language) => new DisputeResolvedMail($disputeId, $outcome, $refundAmount, $language);

        $this->pipeline->deliver($disputeId, $snapshot->buyerId, NotificationType::DisputeResolved, $mailableFactory);
        $this->pipeline->deliver($disputeId, $snapshot->sellerId, NotificationType::DisputeResolved, $mailableFactory);
    }
}
