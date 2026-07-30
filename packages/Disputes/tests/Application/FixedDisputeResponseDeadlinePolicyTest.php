<?php

declare(strict_types=1);

use RowBuddy\Disputes\Application\FixedDisputeResponseDeadlinePolicy;

it('returns whichever duration it was constructed with', function () {
    expect((new FixedDisputeResponseDeadlinePolicy(3600))->durationInSecondsFor('dispute-1'))->toBe(3600)
        ->and((new FixedDisputeResponseDeadlinePolicy(432000))->durationInSecondsFor('dispute-2'))->toBe(432000);
});
