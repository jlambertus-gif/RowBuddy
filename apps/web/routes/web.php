<?php

declare(strict_types=1);

use App\Http\Controllers\ApproveQueueController;
use App\Http\Controllers\PendingQueuesController;
use App\Http\Controllers\PublishQueueController;
use App\Http\Controllers\QueueSubmissionController;
use App\Http\Controllers\RejectQueueController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::post('/queues', QueueSubmissionController::class)->name('queues.store');

    Route::middleware('can:queues.moderate')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/queues', [PendingQueuesController::class, 'index'])->name('queues.index');
        Route::post('/queues/{queueId}/approve', ApproveQueueController::class)->name('queues.approve');
        Route::post('/queues/{queueId}/reject', RejectQueueController::class)->name('queues.reject');
        Route::post('/queues/{queueId}/publish', PublishQueueController::class)->name('queues.publish');
    });
});