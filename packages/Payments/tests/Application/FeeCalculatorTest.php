<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\FeeCalculator;
use RowBuddy\Payments\Application\FixedPlatformFeePolicy;

it('computes the fee as a percentage of the winning amount', function () {
    $calculator = new FeeCalculator(new FixedPlatformFeePolicy(10));

    $fee = $calculator->calculate(usd(10000));

    expect($fee->equals(usd(1000)))->toBeTrue();
});

it('rounds the computed fee to the nearest minor unit', function () {
    $calculator = new FeeCalculator(new FixedPlatformFeePolicy(10));

    $fee = $calculator->calculate(usd(999));

    expect($fee->equals(usd(100)))->toBeTrue();
});

it('reads the percentage from whichever policy it is given', function () {
    $calculator = new FeeCalculator(new FixedPlatformFeePolicy(15));

    $fee = $calculator->calculate(usd(10000));

    expect($fee->equals(usd(1500)))->toBeTrue();
});
