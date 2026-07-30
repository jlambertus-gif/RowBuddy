<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Contracts;

use RowBuddy\Notifications\ValueObjects\NotificationType;

/**
 * The idempotency mechanism ADR-025 §7 requires: a delivery ledger keyed
 * by a stable logical identity — the originating domain event's own
 * stable identifier (e.g. an `auctionId`, `transferId`, or `disputeId`
 * that identifies a specific, at-most-once occurrence of that event
 * type — never a transport-specific identifier such as a mail-provider
 * message id, which only exists after a send attempt), together with
 * the recipient and the notification type.
 *
 * This is an operational correctness mechanism, not a business audit
 * trail (ADR-025 §7) — no separate audit record is created alongside
 * it; the originating business action already satisfies CLAUDE.md's
 * audit requirement at the moment it occurred.
 */
interface NotificationDeliveryLedger
{
    public function alreadyDelivered(string $domainEventId, string $recipientId, NotificationType $type): bool;

    public function recordDelivered(string $domainEventId, string $recipientId, NotificationType $type): void;
}
