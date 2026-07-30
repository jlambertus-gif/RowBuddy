<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rating reveal window
    |--------------------------------------------------------------------------
    |
    | Whole-number days from a rating's own submittedAt before it reveals
    | on its own, even with no counterpart rating yet (ADR-024 §5).
    | Provisional MVP configuration, not a permanent domain invariant —
    | see RowBuddy\Ratings\Contracts\RatingRevealDeadlinePolicy.
    |
    */

    'reveal_window_days' => (int) env('RATINGS_REVEAL_WINDOW_DAYS', 7),

];
