<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\EvaluateTransferExpiry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stripe\Exception\ExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    // Every cross-module domain-event reaction in this codebase is wired
    // through an explicit Event::listen() call (AppServiceProvider::boot()),
    // never left to convention — automatic discovery silently double-
    // registered App\Listeners\BroadcastAuctionSnapshot (once by explicit
    // registration, once by its handle() union-type hint), producing a
    // second accepted-bid broadcast per event (Phase 9, ADR-027 Sprint 2).
    // Discovery is disabled outright so this class of bug cannot recur for
    // any future listener added to app/Listeners.
    ->withEvents(discover: false)
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
        // A raw Stripe SDK exception (e.g. a missing/invalid API key)
        // must never leak its technical message to the client — the
        // full exception is still logged via Laravel's own default
        // exception reporting; only the rendered response is replaced
        // (Phase 9 stabilization, functional-acceptance finding FA-002).
        // Domain-level Stripe outcomes (e.g. InvalidWebhookSignature,
        // SetupIntentNotConfirmed) are RowBuddy's own exception types,
        // already handled by each controller's own catch block, and are
        // unaffected by this — this only guards against the raw Stripe
        // SDK exception classes themselves.
        $exceptions->render(function (ExceptionInterface $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('payments.errors.provider_unavailable')], 503);
            }
        });
    })->create();
