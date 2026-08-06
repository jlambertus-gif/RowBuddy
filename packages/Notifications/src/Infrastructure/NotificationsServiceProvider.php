<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Infrastructure;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Disputes\Events\DisputeOpened;
use RowBuddy\Disputes\Events\DisputeResolved;
use RowBuddy\Notifications\Contracts\DeviceTokenRepository;
use RowBuddy\Notifications\Contracts\NotificationDeliveryLedger;
use RowBuddy\Notifications\Contracts\PushNotificationSender;
use RowBuddy\Notifications\Infrastructure\Eloquent\EloquentDeviceTokenRepository;
use RowBuddy\Notifications\Infrastructure\Eloquent\EloquentNotificationDeliveryLedger;
use RowBuddy\Notifications\Listeners\SendAuctionWonNotification;
use RowBuddy\Notifications\Listeners\SendDisputeOpenedNotification;
use RowBuddy\Notifications\Listeners\SendDisputeResolvedNotification;
use RowBuddy\Notifications\Listeners\SendPaymentAuthorizationFailedNotification;
use RowBuddy\Notifications\Listeners\SendTransferCancelledNotification;
use RowBuddy\Notifications\Listeners\SendTransferConfirmedNotification;
use RowBuddy\Notifications\Listeners\SendTransferExpiredNotification;
use RowBuddy\Notifications\Listeners\SendTransferIssuedNotification;
use RowBuddy\Payments\Events\PaymentAuthorizationFailed;
use RowBuddy\Transfers\Events\TransferCancelled;
use RowBuddy\Transfers\Events\TransferConfirmed;
use RowBuddy\Transfers\Events\TransferExpired;
use RowBuddy\Transfers\Events\TransferIssued;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationDeliveryLedger::class, EloquentNotificationDeliveryLedger::class);
        $this->app->bind(DeviceTokenRepository::class, EloquentDeviceTokenRepository::class);
        $this->app->bind(PushNotificationSender::class, ExpoPushNotificationSender::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // This module's own reactions, registered against real,
        // committed domain events — this codebase's first genuine
        // Laravel Event::listen() wiring (ADR-025 §Consequences). Every
        // event here is the complete, closed set ADR-025 §6 approves —
        // no ninth event may be added without a new, separately
        // approved decision.
        Event::listen(AuctionWon::class, SendAuctionWonNotification::class);
        Event::listen(PaymentAuthorizationFailed::class, SendPaymentAuthorizationFailedNotification::class);
        Event::listen(TransferIssued::class, SendTransferIssuedNotification::class);
        Event::listen(TransferConfirmed::class, SendTransferConfirmedNotification::class);
        Event::listen(TransferExpired::class, SendTransferExpiredNotification::class);
        Event::listen(TransferCancelled::class, SendTransferCancelledNotification::class);
        Event::listen(DisputeOpened::class, SendDisputeOpenedNotification::class);
        Event::listen(DisputeResolved::class, SendDisputeResolvedNotification::class);
    }
}
