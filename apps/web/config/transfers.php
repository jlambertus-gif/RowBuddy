<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Transfer confirmation window
    |--------------------------------------------------------------------------
    |
    | Whole-number hours a buyer/seller have to confirm the handoff before
    | the transfer expires unconfirmed (ADR-018 §1). Provisional MVP
    | configuration, not a permanent domain invariant — see
    | RowBuddy\Transfers\Contracts\TransferWindowPolicy.
    |
    */

    'window_hours' => (int) env('TRANSFERS_WINDOW_HOURS', 24),

];
