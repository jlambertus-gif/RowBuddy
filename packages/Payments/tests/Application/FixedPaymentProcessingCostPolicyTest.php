<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\FixedPaymentProcessingCostPolicy;

it('estimates a flat percentage plus a fixed fee', function () {
    $policy = new FixedPaymentProcessingCostPolicy(3, usd(30));

    $estimate = $policy->estimate(usd(10000));

    expect($estimate->equals(usd(330)))->toBeTrue();
});

it('rounds the percentage portion to the nearest minor unit', function () {
    $policy = new FixedPaymentProcessingCostPolicy(3, usd(30));

    $estimate = $policy->estimate(usd(999));

    expect($estimate->equals(usd(60)))->toBeTrue();
});
