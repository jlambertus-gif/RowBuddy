<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\FixedPaymentProcessingCostPolicy;
use RowBuddy\Payments\Application\SellerSettlementCalculator;

it('subtracts the estimated processing cost from the winning amount', function () {
    $calculator = new SellerSettlementCalculator(new FixedPaymentProcessingCostPolicy(3, usd(30)));

    $settlement = $calculator->calculate(usd(10000));

    expect($settlement->equals(usd(9670)))->toBeTrue();
});

it('never subtracts a platform fee, only the estimated processing cost', function () {
    // A settlement calculated from the winning-bid-only portion (not the
    // buyer's total, which includes the platform fee) should differ only
    // by the processing cost estimate, never by any fee percentage.
    $calculator = new SellerSettlementCalculator(new FixedPaymentProcessingCostPolicy(3, usd(30)));

    $settlement = $calculator->calculate(usd(20000));

    expect($settlement->equals(usd(19370)))->toBeTrue();
});
