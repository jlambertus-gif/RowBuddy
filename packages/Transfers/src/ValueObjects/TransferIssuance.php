<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\ValueObjects;

use RowBuddy\Transfers\Transfer;

/**
 * The result of `TransferInitiationService::handle()`. `plaintextQrToken`
 * is the buyer's one-time proof-of-claim (ADR-017 §5) — available only
 * on the call that actually created the `Transfer`. On an idempotent
 * replay (an already-issued transfer for this auction), it is `null`:
 * the plaintext is never persisted (ADR-017 §2), so a second call has no
 * way to recover it. This is an accepted limitation for this sprint —
 * real delivery of the plaintext to the buyer is a delivery-layer
 * concern that does not exist yet, so no retry-after-partial-delivery
 * scenario is reachable today; it must be revisited once a real
 * notification/HTTP layer is built.
 */
final class TransferIssuance
{
    public function __construct(
        public readonly Transfer $transfer,
        public readonly ?string $plaintextQrToken,
    ) {}
}
