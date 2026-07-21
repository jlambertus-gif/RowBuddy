<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\Exceptions;

/**
 * Thrown when a value object is constructed with data that violates its own
 * invariants (e.g. a negative Money amount, an out-of-range GeoPoint). This
 * is distinct from Laravel's HTTP-layer validation — it protects the
 * domain layer even when called from somewhere other than a request.
 */
class ValidationException extends DomainException {}
