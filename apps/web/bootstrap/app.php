<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\EvaluateTransferExpiry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // The scheduled sweep half of ADR-018 §3's hybrid evaluation —
        // catches transfers nobody ever touches again (silence), which
        // the lazy call site in TransferConfirmationService structurally
        // cannot. Every-five-minutes is a provisional operational
        // cadence, not a business rule — easily changed without
        // affecting the Transfers domain itself.
        $schedule->job(new EvaluateTransferExpiry)->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Stripe cannot supply a Laravel CSRF token; the webhook's
        // signature header is its authentication instead.
        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
