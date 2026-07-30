<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Exceptions;

use RuntimeException;

/**
 * Raised when a listener cannot resolve who to notify or how to reach
 * them (no matching bidder, no email on file). Deliberately thrown
 * rather than silently returning: per ADR-025 §11, delivery failure
 * relies entirely on Laravel's own retry/`failed_jobs` mechanism — a
 * silent no-op here would be invisible everywhere, defeating that
 * mechanism for exactly the failure mode it exists to catch.
 */
final class NotificationRecipientUnresolved extends RuntimeException {}
