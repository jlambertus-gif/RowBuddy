<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile return URL (ADR-028 Decision 7)
    |--------------------------------------------------------------------------
    |
    | The single, server-configured deep link the verification/password-
    | reset "return to the app" interstitial redirects to. Never derived
    | from client input — the interstitial only ever redirects here,
    | regardless of any request parameter. During development this is the
    | approved custom app scheme; before production it must be replaced
    | with a verified universal/app link (a config change, not a code
    | change).
    |
    */
    'return_url' => env('MOBILE_APP_RETURN_URL', 'rowbuddy://auth/callback'),
];
