<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\ValueObjects;

use RowBuddy\SharedKernel\Exceptions\ValidationException;

/**
 * An ISO 4217 currency code. Deliberately does not maintain an exhaustive
 * allow-list of currencies here — RowBuddy must support multiple countries,
 * and which currencies are actually usable in a given market is a policy
 * decision that belongs to a module (e.g. Queues/Payments reference data),
 * not to this shared value type. This class only guarantees the *shape* of
 * a currency code is valid.
 */
final class Currency
{
    public readonly string $code;

    public function __construct(string $code)
    {
        $normalized = strtoupper(trim($code));

        if (! preg_match('/^[A-Z]{3}$/', $normalized)) {
            throw new ValidationException(
                "Invalid currency code [{$code}]: expected a 3-letter ISO 4217 code."
            );
        }

        $this->code = $normalized;
    }

    public function equals(Currency $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
