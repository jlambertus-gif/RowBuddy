<?php

declare(strict_types=1);

namespace RowBuddy\Administration\ValueObjects;

/**
 * A single suspended/active distinction (ADR-026 §4) — no additional
 * standing tiers (`Restricted`, `UnderReview`) exist in the MVP.
 */
enum AccountStandingState: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
