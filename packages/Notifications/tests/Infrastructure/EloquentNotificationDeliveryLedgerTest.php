<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Notifications\Infrastructure\Eloquent\EloquentNotificationDeliveryLedger;
use RowBuddy\Notifications\ValueObjects\NotificationType;
use RowBuddy\SharedKernel\Support\FrozenClock;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('notification_deliveries', function (Blueprint $table) {
        $table->id();
        $table->string('domain_event_id');
        $table->unsignedBigInteger('recipient_id');
        $table->string('notification_type');
        $table->string('channel')->default('email');
        $table->timestamp('delivered_at');
        $table->timestamps();

        $table->unique(['domain_event_id', 'recipient_id', 'notification_type', 'channel']);
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('notification_deliveries');
});

it('reports not-yet-delivered before any record exists', function () {
    $ledger = new EloquentNotificationDeliveryLedger(new FrozenClock);

    expect($ledger->alreadyDelivered('auction-1', '101', NotificationType::AuctionWon))->toBeFalse();
});

it('reports delivered once recorded', function () {
    $ledger = new EloquentNotificationDeliveryLedger(new FrozenClock);

    $ledger->recordDelivered('auction-1', '101', NotificationType::AuctionWon);

    expect($ledger->alreadyDelivered('auction-1', '101', NotificationType::AuctionWon))->toBeTrue();
});

it('distinguishes by domain event id, recipient, and notification type independently', function () {
    $ledger = new EloquentNotificationDeliveryLedger(new FrozenClock);
    $ledger->recordDelivered('auction-1', '101', NotificationType::AuctionWon);

    expect($ledger->alreadyDelivered('auction-2', '101', NotificationType::AuctionWon))->toBeFalse()
        ->and($ledger->alreadyDelivered('auction-1', '102', NotificationType::AuctionWon))->toBeFalse()
        ->and($ledger->alreadyDelivered('auction-1', '101', NotificationType::DisputeOpened))->toBeFalse();
});

it('tolerates recording the same logical delivery twice without throwing', function () {
    $ledger = new EloquentNotificationDeliveryLedger(new FrozenClock);

    $ledger->recordDelivered('auction-1', '101', NotificationType::AuctionWon);
    $ledger->recordDelivered('auction-1', '101', NotificationType::AuctionWon);

    expect($ledger->alreadyDelivered('auction-1', '101', NotificationType::AuctionWon))->toBeTrue();
});

it('defaults to the email channel when none is specified, matching every pre-existing call site', function () {
    $ledger = new EloquentNotificationDeliveryLedger(new FrozenClock);

    $ledger->recordDelivered('auction-1', '101', NotificationType::AuctionWon);

    expect($ledger->alreadyDelivered('auction-1', '101', NotificationType::AuctionWon, 'email'))->toBeTrue();
});

it('tracks the push channel independently of email — one never suppresses the other (ADR-028 Decision 6)', function () {
    $ledger = new EloquentNotificationDeliveryLedger(new FrozenClock);

    $ledger->recordDelivered('auction-1', '101', NotificationType::AuctionWon, 'email');

    expect($ledger->alreadyDelivered('auction-1', '101', NotificationType::AuctionWon, 'push'))->toBeFalse();

    $ledger->recordDelivered('auction-1', '101', NotificationType::AuctionWon, 'push');

    expect($ledger->alreadyDelivered('auction-1', '101', NotificationType::AuctionWon, 'email'))->toBeTrue()
        ->and($ledger->alreadyDelivered('auction-1', '101', NotificationType::AuctionWon, 'push'))->toBeTrue();
});
