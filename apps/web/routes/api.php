<?php

declare(strict_types=1);

use App\Http\Controllers\BeginBuyerPaymentMethodSetupController;
use App\Http\Controllers\CompleteBuyerPaymentMethodSetupController;
use App\Http\Controllers\ConfirmTransferAsBuyerController;
use App\Http\Controllers\ConfirmTransferAsSellerController;
use App\Http\Controllers\FileDisputeController;
use App\Http\Controllers\ListTransferRatingsController;
use App\Http\Controllers\LoginApiUserController;
use App\Http\Controllers\LogoutApiUserController;
use App\Http\Controllers\PlaceBidController;
use App\Http\Controllers\RegisterApiUserController;
use App\Http\Controllers\RegisterDeviceTokenController;
use App\Http\Controllers\ResetApiPasswordController;
use App\Http\Controllers\SendApiEmailVerificationNotificationController;
use App\Http\Controllers\SendApiPasswordResetLinkController;
use App\Http\Controllers\ShowApiCurrentUserController;
use App\Http\Controllers\ShowDisputeController;
use App\Http\Controllers\ShowProfileController;
use App\Http\Controllers\ShowTransferController;
use App\Http\Controllers\ShowTransferQrTokenController;
use App\Http\Controllers\SubmitQueueController;
use App\Http\Controllers\SubmitRatingController;
use App\Http\Controllers\UpdateProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API — v1 (ADR-028)
|--------------------------------------------------------------------------
|
| Versioned from day one: a native client can't be force-refreshed the
| way a web page can, so retrofitting a version segment later would be
| far more disruptive than reserving it now. Every route below is
| Sanctum-token-guarded where authentication is required — never the
| web session guard, never CSRF. Every controller here is a thin wrapper
| over an already-existing domain service/Fortify action; no new
| business logic is introduced by this file.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::post('auth/register', RegisterApiUserController::class);
    Route::post('auth/login', LoginApiUserController::class)->middleware('throttle:login');
    Route::post('auth/forgot-password', SendApiPasswordResetLinkController::class);
    Route::post('auth/reset-password', ResetApiPasswordController::class);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', LogoutApiUserController::class);
        Route::post(
            'auth/email/verification-notification',
            SendApiEmailVerificationNotificationController::class,
        )->middleware('throttle:'.config('fortify.limiters.verification', '6,1'));

        Route::get('me', ShowApiCurrentUserController::class);

        // Mobile Sprint 2. Reuses PlaceBidController verbatim — the
        // exact same controller web.php's own /auctions/{id}/bids route
        // already uses, with the identical throttle + verified
        // middleware. Unlike the public GET /queues/discover and
        // GET /auctions/{id} (which mobile calls at their existing
        // web.php paths directly, since they need no auth at all and
        // are already stable, public JSON contracts), bid placement's
        // only existing route sits under the web *session* guard — a
        // native client has no cookie/session to present, so this one
        // genuinely needs its own auth:sanctum-guarded route, not a
        // stylistic mirror.
        Route::post('auctions/{auctionId}/bids', PlaceBidController::class)
            ->middleware(['throttle:bid-placement', 'verified']);

        // Mobile Sprint 3. All six routes below reuse their existing
        // web.php controllers verbatim (ADR-027 Architecture Refinements
        // §4/§5) — every one of them already derives buyerId/sellerId/
        // requestingUserId exclusively from $request->user()->id, so
        // they are guard-agnostic. They sit under the web *session*
        // guard there for the same reason bid placement did: a native
        // client has no cookie/session to present.
        Route::post('buyer-payment-methods/setup-intent', BeginBuyerPaymentMethodSetupController::class)
            ->middleware('verified');
        Route::post('buyer-payment-methods', CompleteBuyerPaymentMethodSetupController::class)
            ->middleware('verified');

        Route::get('transfers/{transferId}', ShowTransferController::class);
        Route::get('transfers/{transferId}/qr-token', ShowTransferQrTokenController::class);
        Route::post('transfers/{transferId}/confirm-as-seller', ConfirmTransferAsSellerController::class)
            ->middleware('throttle:transfer-confirmation');
        Route::post('transfers/{transferId}/confirm-as-buyer', ConfirmTransferAsBuyerController::class)
            ->middleware('throttle:transfer-confirmation');

        // Mobile Sprint 4 (ADR-028 §3). Unlike every controller Sprints
        // 1-3 added, these two have no existing web route to mirror —
        // this is the first HTTP surface RatingSubmissionService/
        // RatingRevealEvaluator have ever had, on any client.
        Route::post('transfers/{transferId}/ratings', SubmitRatingController::class)
            ->middleware('verified');
        Route::get('transfers/{transferId}/ratings', ListTransferRatingsController::class);

        // Mobile Sprint 4 (ADR-028 §3). The first HTTP surface
        // DisputeFilingService has ever had. Dispute *resolution*
        // remains exclusively the existing admin-only web surface
        // (admin.disputes.*) — unaffected, untouched.
        Route::post('transfers/{transferId}/disputes', FileDisputeController::class)
            ->middleware('verified');
        Route::get('disputes/{disputeId}', ShowDisputeController::class);

        // Mobile Sprint 4 (ADR-028 §3/§4). UpdateProfileController reuses
        // Fortify's own UpdatesUserProfileInformation contract verbatim —
        // the same implementation web's PUT user/profile-information
        // route already uses.
        Route::get('profile', ShowProfileController::class);
        Route::put('profile', UpdateProfileController::class);

        // Mobile Sprint 4 (ADR-028 Decision 6). Registering/refreshing a
        // token is always a POST regardless of whether it is the
        // device's first registration or a refresh — the upsert-by-token
        // behavior lives entirely in EloquentDeviceTokenRepository, not
        // in a REST verb distinction here.
        Route::post('devices', RegisterDeviceTokenController::class);

        // Mobile Sprint 5 (ADR-028 §3). Reuses QueueSubmissionService and
        // SubmitQueueRequest verbatim — the same validation and domain
        // logic web's own /queues route already uses, just a JSON
        // response instead of an Inertia-form redirect. Matches web's
        // own 'verified' gate (Phase 9 Sprint 5 security review: starting
        // a queue submission is new transactional activity).
        Route::post('queues', SubmitQueueController::class)
            ->middleware('verified');
    });
});
