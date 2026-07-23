<?php

declare(strict_types=1);

namespace RowBuddy\Queues\ValueObjects;

/**
 * The two queue-origination sources from ADR-005
 * (docs/decisions/005-hybrid-queue-authorship.md). AdminCurated queues
 * publish directly; UserSubmitted queues must clear the pending review
 * gate before they can be published.
 */
enum QueueAuthorship: string
{
    case AdminCurated = 'admin_curated';
    case UserSubmitted = 'user_submitted';
}
