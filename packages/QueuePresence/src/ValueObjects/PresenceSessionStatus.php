<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\ValueObjects;

/**
 * A presence session's lifecycle (Phase 2, docs/roadmap.md): a seller
 * claims physical presence at a queue, optionally records GPS pings and
 * evidence while Active, then Ends the claim. Unlike Queue's four-state
 * moderation lifecycle, there is no review gate here — only two states.
 */
enum PresenceSessionStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
}
