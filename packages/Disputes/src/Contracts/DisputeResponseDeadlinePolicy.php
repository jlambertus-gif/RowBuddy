<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Contracts;

/**
 * ADR-021 §4 — mirrors `DisputeFilingDeadlinePolicy`'s exact shape: a
 * swappable policy, not an aggregate constant. Deliberately independent
 * of, and shorter than, the filing deadline — the seller is reacting to
 * an already-filed, already-evidenced claim, not discovering a problem
 * from scratch.
 *
 * This deadline never auto-resolves a `Dispute` and gates no domain
 * operation — `Dispute::resolve()` remains callable regardless of
 * whether the window has passed (ADR-021 §4: seller silence implies no
 * outcome). It exists purely so a future admin read-model can compute
 * "is this case overdue for a response" and surface it lazily — no
 * scheduler, no automated consequence.
 */
interface DisputeResponseDeadlinePolicy
{
    public function durationInSecondsFor(string $disputeId): int;
}
