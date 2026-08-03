<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\BuyerPaymentMethodSetupService;
use RowBuddy\Payments\Events\BuyerPaymentMethodSaved;
use RowBuddy\Payments\Exceptions\SetupIntentBuyerMismatch;
use RowBuddy\Payments\Exceptions\SetupIntentNotConfirmed;
use RowBuddy\Payments\Tests\Fakes\FakeBuyerPaymentMethodGateway;
use RowBuddy\Payments\Tests\Fakes\InMemoryBuyerPaymentMethodRepository;
use RowBuddy\Payments\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('creates a new Stripe Customer and SetupIntent when the buyer has no saved method yet', function () {
    $methods = new InMemoryBuyerPaymentMethodRepository;
    $gateway = new FakeBuyerPaymentMethodGateway;
    $gateway->nextStripeCustomerId = 'cus_new';
    $service = new BuyerPaymentMethodSetupService($methods, $gateway, new RecordingDomainEventPublisher, new FrozenClock);

    $draft = $service->beginSetup('501');

    expect($draft->stripeCustomerId)->toBe('cus_new')
        ->and($gateway->createCustomerCalls)->toBe(['501'])
        ->and($gateway->createSetupIntentCalls)->toBe(['cus_new']);
});

it('reuses the existing Stripe Customer without creating a second one', function () {
    $methods = new InMemoryBuyerPaymentMethodRepository;
    $gateway = new FakeBuyerPaymentMethodGateway;
    $gateway->nextStripeCustomerId = 'cus_501';
    $service = new BuyerPaymentMethodSetupService($methods, $gateway, new RecordingDomainEventPublisher, new FrozenClock);
    $service->completeSetup('501', 'seti_first');

    $draft = $service->beginSetup('501');

    expect($draft->stripeCustomerId)->toBe('cus_501')
        ->and($gateway->createCustomerCalls)->toBe([])
        ->and($gateway->createSetupIntentCalls)->toBe(['cus_501']);
});

it('completes setup and persists the confirmed payment method, raising BuyerPaymentMethodSaved', function () {
    $methods = new InMemoryBuyerPaymentMethodRepository;
    $gateway = new FakeBuyerPaymentMethodGateway;
    $gateway->nextStripeCustomerId = 'cus_501';
    $gateway->nextStripePaymentMethodId = 'pm_501';
    $events = new RecordingDomainEventPublisher;
    $service = new BuyerPaymentMethodSetupService($methods, $gateway, $events, new FrozenClock);

    $method = $service->completeSetup('501', 'seti_501');

    expect($method->buyerId)->toBe('501')
        ->and($method->stripeCustomerId)->toBe('cus_501')
        ->and($method->stripePaymentMethodId)->toBe('pm_501')
        ->and($methods->findByBuyerId('501')?->stripePaymentMethodId)->toBe('pm_501');

    expect($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(BuyerPaymentMethodSaved::class);
});

it('replaces a previously saved payment method rather than creating a second row', function () {
    $methods = new InMemoryBuyerPaymentMethodRepository;
    $gateway = new FakeBuyerPaymentMethodGateway;
    $service = new BuyerPaymentMethodSetupService($methods, $gateway, new RecordingDomainEventPublisher, new FrozenClock);
    $service->completeSetup('501', 'seti_first');

    $gateway->nextStripePaymentMethodId = 'pm_replacement';
    $service->completeSetup('501', 'seti_second');

    expect($methods->findByBuyerId('501')?->stripePaymentMethodId)->toBe('pm_replacement')
        ->and($methods->saved)->toHaveCount(1);
});

it('never persists anything when the SetupIntent has not succeeded yet', function () {
    $methods = new InMemoryBuyerPaymentMethodRepository;
    $gateway = new FakeBuyerPaymentMethodGateway;
    $gateway->nextSetupIntentSucceeded = false;
    $service = new BuyerPaymentMethodSetupService($methods, $gateway, new RecordingDomainEventPublisher, new FrozenClock);

    expect(fn () => $service->completeSetup('501', 'seti_pending'))
        ->toThrow(SetupIntentNotConfirmed::class);

    expect($methods->findByBuyerId('501'))->toBeNull();
});

it('never persists anything when the SetupIntent belongs to a different buyer', function () {
    $methods = new InMemoryBuyerPaymentMethodRepository;
    $gateway = new FakeBuyerPaymentMethodGateway;
    $gateway->nextSetupIntentBuyerId = '999';
    $service = new BuyerPaymentMethodSetupService($methods, $gateway, new RecordingDomainEventPublisher, new FrozenClock);

    expect(fn () => $service->completeSetup('501', 'seti_stolen'))
        ->toThrow(SetupIntentBuyerMismatch::class);

    expect($methods->findByBuyerId('501'))->toBeNull();
});
