<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

use RowBuddy\Payments\Exceptions\WebhookEventAlreadyProcessed;
use RowBuddy\Payments\ProcessedWebhookEvent;

/**
 * Domain-facing persistence port. Deliberately exposes only `record()` —
 * the idempotency ledger's only real operation is "has this Stripe event
 * id been recorded already," enforced by the unique-constraint-driven
 * exception, not by a separate read-then-write check (which would leave
 * a race window between the read and the write).
 */
interface WebhookEventRepository
{
    /**
     * @throws WebhookEventAlreadyProcessed if this Stripe event id has
     *                                      already been recorded
     */
    public function record(ProcessedWebhookEvent $event): void;
}
