<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Dispute filing window
    |--------------------------------------------------------------------------
    |
    | Whole-number days a buyer has to open a dispute after Transfer
    | confirmation (ADR-021 §3). Provisional MVP configuration, not a
    | permanent domain invariant — see
    | RowBuddy\Disputes\Contracts\DisputeFilingDeadlinePolicy.
    |
    */

    'filing_window_days' => (int) env('DISPUTES_FILING_WINDOW_DAYS', 7),

];
