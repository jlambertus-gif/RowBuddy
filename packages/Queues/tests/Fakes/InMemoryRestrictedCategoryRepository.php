<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Tests\Fakes;

use RowBuddy\Queues\Contracts\RestrictedCategoryRepository;

final class InMemoryRestrictedCategoryRepository implements RestrictedCategoryRepository
{
    /** @var list<array{code: string, jurisdictionCountry: string|null}> */
    private array $restrictions = [];

    public function restrict(string $code, ?string $jurisdictionCountry): void
    {
        $this->restrictions[] = ['code' => $code, 'jurisdictionCountry' => $jurisdictionCountry];
    }

    public function isCategoryRestricted(string $category, string $jurisdictionCountry): bool
    {
        foreach ($this->restrictions as $restriction) {
            if ($restriction['code'] !== $category) {
                continue;
            }

            if ($restriction['jurisdictionCountry'] === null || $restriction['jurisdictionCountry'] === $jurisdictionCountry) {
                return true;
            }
        }

        return false;
    }
}
