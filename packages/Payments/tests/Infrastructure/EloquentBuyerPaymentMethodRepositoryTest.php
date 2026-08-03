<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Payments\BuyerPaymentMethod;
use RowBuddy\Payments\Infrastructure\Eloquent\EloquentBuyerPaymentMethodRepository;
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

    Capsule::schema()->create('buyer_payment_methods', function (Blueprint $table) {
        $table->unsignedBigInteger('buyer_id')->primary();
        $table->string('stripe_customer_id');
        $table->string('stripe_payment_method_id');
        $table->timestamp('saved_at');
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('buyer_payment_methods');
});

it('saves a buyer payment method and finds it by buyer id', function () {
    $repository = new EloquentBuyerPaymentMethodRepository;
    $savedAt = new DateTimeImmutable('2026-11-22 10:00:00');

    $repository->save(BuyerPaymentMethod::save('501', 'cus_123', 'pm_123', new FrozenClock($savedAt)));

    $found = $repository->findByBuyerId('501');

    expect($found)->not->toBeNull()
        ->and($found->buyerId)->toBe('501')
        ->and($found->stripeCustomerId)->toBe('cus_123')
        ->and($found->stripePaymentMethodId)->toBe('pm_123')
        ->and($found->savedAt)->toEqual($savedAt);
});

it('returns null when the buyer has no saved payment method', function () {
    expect((new EloquentBuyerPaymentMethodRepository)->findByBuyerId('missing'))->toBeNull();
});

it('overwrites the existing row in place when a buyer replaces their payment method', function () {
    $repository = new EloquentBuyerPaymentMethodRepository;
    $repository->save(BuyerPaymentMethod::save('501', 'cus_123', 'pm_123', new FrozenClock));

    $repository->save(BuyerPaymentMethod::save('501', 'cus_123', 'pm_456', new FrozenClock));

    expect($repository->findByBuyerId('501')?->stripePaymentMethodId)->toBe('pm_456');
});
