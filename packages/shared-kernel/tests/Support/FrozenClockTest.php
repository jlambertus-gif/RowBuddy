<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Support\FrozenClock;

it('always returns the same instant', function () {
    $instant = new DateTimeImmutable('2026-01-01T00:00:00Z');
    $clock = new FrozenClock($instant);

    expect($clock->now())->toBe($instant)
        ->and($clock->now())->toBe($clock->now());
});
