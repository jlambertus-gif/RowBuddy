<?php

declare(strict_types=1);

use RowBuddy\Disputes\Application\FixedDisputeFilingDeadlinePolicy;

it('returns whichever duration it was constructed with', function () {
    expect((new FixedDisputeFilingDeadlinePolicy(3600))->durationInSecondsFor('transfer-1'))->toBe(3600)
        ->and((new FixedDisputeFilingDeadlinePolicy(604800))->durationInSecondsFor('transfer-2'))->toBe(604800);
});
