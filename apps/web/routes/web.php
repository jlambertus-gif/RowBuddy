<?php

declare(strict_types=1);

use App\Http\Controllers\ApproveQueueController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DiscoverQueuesController;
use App\Http\Controllers\DisputeReviewController;
use App\Http\Controllers\EndPresenceSessionController;
use App\Http\Controllers\PendingQueuesController;
use App\Http\Controllers\PublishQueueController;
use App\Http\Controllers\QueueSubmissionController;
use App\Http\Controllers\RecordDisputeCorrectionController;
use App\Http\Controllers\RecordGpsPingController;
use App\Http\Controllers\RejectQueueController;
use App\Http\Controllers\ShowEvidencePhotoUrlController;
use App\Http\Controllers\StartPresenceSessionController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\UploadEvidencePhotoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Called only by Stripe's own systems, authenticated by signature header,
// not a session/user — CSRF-exempt (see bootstrap/app.php).
Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

// Public JSON API: no authentication required to search published queues.
Route::get('/queues/discover', DiscoverQueuesController::class)->name('queues.discover');

// Public page: mirrors the API's accessibility above — the discovery
// page itself fetches from /queues/discover client-side (Sprint 7).
Route::get('/discover', function () {
    return Inertia::render('Queues/Discover');
})->name('queues.discover-page');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/queues/submit', function () {
        return Inertia::render('Queues/Submit');
    })->name('queues.submit-page');

    Route::post('/queues', QueueSubmissionController::class)->name('queues.store');

    Route::get('/queues/{queueId}/presence', function (string $queueId) {
        return Inertia::render('Queues/Presence', ['queueId' => $queueId]);
    })->name('queues.presence-page');

    Route::post('/presence-sessions', StartPresenceSessionController::class)->name('presence-sessions.start');
    Route::post('/presence-sessions/{sessionId}/gps-pings', RecordGpsPingController::class)->name('presence-sessions.gps-pings.record');
    Route::post('/presence-sessions/{sessionId}/end', EndPresenceSessionController::class)->name('presence-sessions.end');
    Route::post('/presence-sessions/{sessionId}/evidence-photos', UploadEvidencePhotoController::class)->name('presence-sessions.evidence-photos.upload');
    Route::get('/presence-sessions/{sessionId}/evidence-photos/{photoId}', ShowEvidencePhotoUrlController::class)->name('presence-sessions.evidence-photos.show');

    Route::middleware('can:queues.moderate')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/queues', [PendingQueuesController::class, 'index'])->name('queues.index');
        Route::get('/queues/moderation', function () {
            return Inertia::render('Admin/Moderation');
        })->name('queues.moderation-page');
        Route::post('/queues/{queueId}/approve', ApproveQueueController::class)->name('queues.approve');
        Route::post('/queues/{queueId}/reject', RejectQueueController::class)->name('queues.reject');
        Route::post('/queues/{queueId}/publish', PublishQueueController::class)->name('queues.publish');
    });

    Route::middleware('can:disputes.review')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/disputes', DisputeReviewController::class)->name('disputes.index');
        Route::get('/disputes/review', function () {
            return Inertia::render('Admin/DisputeReview');
        })->name('disputes.review-page');
        Route::post('/disputes/{disputeId}/corrections', RecordDisputeCorrectionController::class)->name('disputes.corrections.store');
    });

    Route::middleware('can:audit.view')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/audit-events', AuditLogController::class)->name('audit-events.index');
        Route::get('/audit-log', function () {
            return Inertia::render('Admin/AuditLog');
        })->name('audit-log-page');
    });
});
