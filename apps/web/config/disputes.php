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

    /*
    |--------------------------------------------------------------------------
    | Seller response window
    |--------------------------------------------------------------------------
    |
    | Whole-number days a seller has to submit counter-evidence after
    | DisputeOpened (ADR-021 §4). Provisional MVP configuration, not a
    | permanent domain invariant, and independent of the filing window
    | above — see RowBuddy\Disputes\Contracts\DisputeResponseDeadlinePolicy.
    | Never auto-resolves the dispute; purely informational for a future
    | admin read-model.
    |
    */

    'response_window_days' => (int) env('DISPUTES_RESPONSE_WINDOW_DAYS', 5),

];
