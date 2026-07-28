<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\FixedTransactionValueLimitPolicy;

it('returns whichever Money value it was constructed with', function () {
    $policy = new FixedTransactionValueLimitPolicy(usd(50000));

    expect($policy->maximum()->equals(usd(50000)))->toBeTrue();
});
