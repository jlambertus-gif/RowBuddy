<?php

declare(strict_types=1);

use RowBuddy\Queues\ValueObjects\JurisdictionRule;

it('is not effective before its start date', function () {
    $rule = new JurisdictionRule('US', null, true, new DateTimeImmutable('2026-06-01'), null, true);

    expect($rule->isEffectiveAt(new DateTimeImmutable('2026-05-31')))->toBeFalse();
});

it('is effective on and after its start date when open-ended', function () {
    $rule = new JurisdictionRule('US', null, true, new DateTimeImmutable('2026-06-01'), null, true);

    expect($rule->isEffectiveAt(new DateTimeImmutable('2026-06-01')))->toBeTrue()
        ->and($rule->isEffectiveAt(new DateTimeImmutable('2030-01-01')))->toBeTrue();
});

it('is not effective on or after its end date', function () {
    $rule = new JurisdictionRule('US', null, true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-06-01'), true);

    expect($rule->isEffectiveAt(new DateTimeImmutable('2026-03-01')))->toBeTrue()
        ->and($rule->isEffectiveAt(new DateTimeImmutable('2026-06-01')))->toBeFalse()
        ->and($rule->isEffectiveAt(new DateTimeImmutable('2026-07-01')))->toBeFalse();
});

it('a null category applies to every category', function () {
    $rule = new JurisdictionRule('US', null, true, new DateTimeImmutable('2026-01-01'), null, true);

    expect($rule->appliesToCategory('concert'))->toBeTrue()
        ->and($rule->appliesToCategory('anything'))->toBeTrue();
});

it('a specific category only applies to itself', function () {
    $rule = new JurisdictionRule('US', 'concert', true, new DateTimeImmutable('2026-01-01'), null, true);

    expect($rule->appliesToCategory('concert'))->toBeTrue()
        ->and($rule->appliesToCategory('sports'))->toBeFalse();
});
