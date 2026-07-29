<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\ValueObjects;

/**
 * Deliberately extensible (ADR-020 §4) — Phase 6 (Disputes) is the
 * anticipated reason more cases get added later (e.g. a
 * dispute-submitted photo, a written statement), without redesigning
 * `Transfer` or its persistence.
 */
enum TransferEvidenceType: string
{
    case Photo = 'photo';
}
