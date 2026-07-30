<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RowBuddy\Disputes\Events\DisputeOpened;
use RowBuddy\Notifications\Mail\DisputeOpenedMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\ValueObjects\NotificationType;

/**
 * The real `DisputeOpened` consumer for Phase 7 (ADR-025 §6) — seller
 * only (ADR-021 §1's buyer-only filing right means the seller is always
 * the respondent, never the filer). `DisputeOpened` carries `sellerId`
 * directly, so no additional read port is needed.
 */
final class SendDisputeOpenedNotification implements ShouldQueue
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

    public function handle(DisputeOpened $event): void
    {
        $payload = $event->payload();
        $disputeId = (string) $payload['dispute_id'];
        $sellerId = (string) $payload['seller_id'];

        $this->pipeline->deliver(
            $disputeId,
            $sellerId,
            NotificationType::DisputeOpened,
            fn (string $language) => new DisputeOpenedMail($disputeId),
        );
    }
}
