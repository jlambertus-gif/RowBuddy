<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\FixedPlatformFeePolicy;

it('returns whichever percentage it was constructed with', function () {
    expect((new FixedPlatformFeePolicy(10))->percentage())->toBe(10)
        ->and((new FixedPlatformFeePolicy(15))->percentage())->toBe(15);
});
