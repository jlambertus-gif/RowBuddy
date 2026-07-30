<?php

declare(strict_types=1);

use RowBuddy\Ratings\Application\FixedRatingRevealDeadlinePolicy;

it('returns whichever duration it was constructed with', function () {
    expect((new FixedRatingRevealDeadlinePolicy(3600))->durationInSecondsFor('transfer-1'))->toBe(3600)
        ->and((new FixedRatingRevealDeadlinePolicy(604800))->durationInSecondsFor('transfer-2'))->toBe(604800);
});
