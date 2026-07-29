<?php

declare(strict_types=1);

use RowBuddy\Transfers\Application\FixedTransferWindowPolicy;

it('returns whichever duration it was constructed with', function () {
    expect((new FixedTransferWindowPolicy(3600))->durationInSecondsFor('auction-1'))->toBe(3600)
        ->and((new FixedTransferWindowPolicy(86400))->durationInSecondsFor('auction-2'))->toBe(86400);
});
