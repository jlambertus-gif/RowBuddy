<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Contracts;

use RowBuddy\Disputes\ValueObjects\TransferCaseSnapshot;

/**
 * Disputes-owned read port into Transfers (Phase 6 architecture review
 * §4/§13), mirroring `TransferGeofenceLookup`'s exact "consumer owns the
 * port" shape one hop further — `packages/Disputes` reads a `Transfer`'s
 * eligibility-relevant facts and evidence read-only, and gains no
 * dependency on `packages/Transfers`' internals. Implemented by a new
 * `apps/web` adapter bridging to `TransferRepository`.
 */
interface TransferCaseLookup
{
    public function findByTransferId(string $transferId): ?TransferCaseSnapshot;
}
