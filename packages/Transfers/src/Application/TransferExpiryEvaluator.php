<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Application;

use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

/**
 * The single evaluation encoded by ADR-018 §3: is this transfer past
 * `expiresAt` and not yet `Confirmed`? If so, it expires it (ADR-018
 * §4's provisional, symmetric no-fault policy — no capture, no charge,
 * regardless of which party is actually at fault).
 *
 * A pure, dependency-free mutation on an already row-locked `Transfer` —
 * callers (the lazy confirmation-time check and the scheduled sweep) own
 * the lock, the persistence, and the event publication, exactly like the
 * `mutate` callables `TransferConfirmationService` already uses. This
 * keeps the one piece of logic both call sites share in exactly one
 * place, per ADR-018 §3/Consequences.
 *
 * Deliberately does not attempt ADR-018 §2's proactive re-authorization:
 * that requires a stored Stripe authorization-expiry timestamp and an
 * undefined "how much margin counts as approaching it" threshold, neither
 * of which exists anywhere in this codebase yet, and — under the current
 * provisional 24-hour `TransferWindowPolicy` default — the window-close
 * check below always fires first, days before Stripe's own ~5-7 day
 * authorization ceiling could ever be approached. Adding it now would
 * mean inventing an unspecified business parameter rather than reading
 * one from an already-approved decision.
 */
final class TransferExpiryEvaluator
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public function evaluate(Transfer $transfer): void
    {
        if ($transfer->status() !== TransferStatus::Issued) {
            return;
        }

        if ($this->clock->now() < $transfer->expiresAt) {
            return;
        }

        $transfer->expire($this->clock);
    }
}
