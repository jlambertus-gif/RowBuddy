<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\Exceptions;

use RuntimeException;

/**
 * Base marker exception for domain-rule violations across every module.
 * Module-specific exceptions (e.g. an Auctions "IllegalStateTransition")
 * should extend this rather than a bare RuntimeException, so cross-cutting
 * error handling can catch "any domain rule violation" generically.
 */
abstract class DomainException extends RuntimeException {}
