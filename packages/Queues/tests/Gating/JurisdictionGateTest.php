<?php

declare(strict_types=1);

use RowBuddy\Queues\Gating\JurisdictionGate;
use RowBuddy\Queues\ValueObjects\JurisdictionRule;

it('fails closed when no rule exists for the country at all', function () {
    $gate = new JurisdictionGate;

    expect($gate->isPermitted([], 'concert', new DateTimeImmutable('2026-01-01')))->toBeFalse();
});

it('is permitted when a country-wide rule allows it', function () {
    $gate = new JurisdictionGate;
    $rules = [new JurisdictionRule('US', null, true, new DateTimeImmutable('2026-01-01'), null)];

    expect($gate->isPermitted($rules, 'concert', new DateTimeImmutable('2026-06-01')))->toBeTrue();
});

it('is blocked when a country-wide rule forbids it', function () {
    $gate = new JurisdictionGate;
    $rules = [new JurisdictionRule('US', null, false, new DateTimeImmutable('2026-01-01'), null)];

    expect($gate->isPermitted($rules, 'concert', new DateTimeImmutable('2026-06-01')))->toBeFalse();
});

it('prefers a category-specific rule over a country-wide rule', function () {
    $gate = new JurisdictionGate;
    $rules = [
        new JurisdictionRule('US', null, true, new DateTimeImmutable('2026-01-01'), null),
        new JurisdictionRule('US', 'concert', false, new DateTimeImmutable('2026-01-01'), null),
    ];

    expect($gate->isPermitted($rules, 'concert', new DateTimeImmutable('2026-06-01')))->toBeFalse()
        ->and($gate->isPermitted($rules, 'sports', new DateTimeImmutable('2026-06-01')))->toBeTrue();
});

it('picks the rule version in effect as of the given date, not the newest one overall', function () {
    $gate = new JurisdictionGate;
    $rules = [
        new JurisdictionRule('FR', 'concert', true, new DateTimeImmutable('2020-01-01'), new DateTimeImmutable('2026-01-01')),
        new JurisdictionRule('FR', 'concert', false, new DateTimeImmutable('2026-01-01'), null),
    ];

    expect($gate->isPermitted($rules, 'concert', new DateTimeImmutable('2025-06-01')))->toBeTrue()
        ->and($gate->isPermitted($rules, 'concert', new DateTimeImmutable('2026-06-01')))->toBeFalse();
});

it('ignores rules for a different category and falls back to fail-closed', function () {
    $gate = new JurisdictionGate;
    $rules = [new JurisdictionRule('US', 'sports', true, new DateTimeImmutable('2026-01-01'), null)];

    expect($gate->isPermitted($rules, 'concert', new DateTimeImmutable('2026-06-01')))->toBeFalse();
});
