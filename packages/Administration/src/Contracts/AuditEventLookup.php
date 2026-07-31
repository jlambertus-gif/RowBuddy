<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Contracts;

use RowBuddy\Administration\ValueObjects\AuditEventSnapshot;

/**
 * Administration's own consumer-owned read port onto the platform-wide
 * audit sink (ADR-026 §6) — mirrors the "consumer owns the port"
 * discipline already proven across this codebase. An apps/web adapter
 * bridges to `App\Models\AuditEvent` (the audit sink lives in apps/web
 * itself; there is no separate Audit package to depend on). Read-only:
 * nothing here can write to, mutate, or delete an audit record.
 */
interface AuditEventLookup
{
    /**
     * Most recently occurred first.
     *
     * @return list<AuditEventSnapshot>
     */
    public function listRecent(int $limit): array;

    public function findById(string $id): ?AuditEventSnapshot;
}
