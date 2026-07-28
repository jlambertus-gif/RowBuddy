<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\SellerOnboardingService;
use RowBuddy\Payments\Events\SellerPayoutAccountLinked;
use RowBuddy\Payments\Tests\Fakes\FakeConnectAccountGateway;
use RowBuddy\Payments\Tests\Fakes\InMemorySellerPayoutAccountRepository;
use RowBuddy\Payments\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Payments\ValueObjects\PayoutEligibility;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('links a new account and returns an onboarding link when the seller has none yet', function () {
    $accounts = new InMemorySellerPayoutAccountRepository;
    $gateway = new FakeConnectAccountGateway;
    $gateway->nextStripeAccountId = 'acct_new';
    $gateway->nextOnboardingUrl = 'https://connect.stripe.com/setup/new';
    $events = new RecordingDomainEventPublisher;
    $service = new SellerOnboardingService($accounts, $gateway, $events, new FrozenClock);

    $url = $service->startOnboarding('101', 'https://app.example/return', 'https://app.example/refresh');

    expect($url)->toBe('https://connect.stripe.com/setup/new')
        ->and($gateway->createExpressAccountCalls)->toBe(['101'])
        ->and($gateway->createOnboardingLinkCalls)->toBe([[
            'stripeAccountId' => 'acct_new',
            'returnUrl' => 'https://app.example/return',
            'refreshUrl' => 'https://app.example/refresh',
        ]])
        ->and($accounts->findBySellerId('101')?->stripeAccountId)->toBe('acct_new');

    expect($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(SellerPayoutAccountLinked::class);
});

it('reuses the existing linked account without creating a second one', function () {
    $accounts = new InMemorySellerPayoutAccountRepository;
    $gateway = new FakeConnectAccountGateway;
    $events = new RecordingDomainEventPublisher;
    $service = new SellerOnboardingService($accounts, $gateway, $events, new FrozenClock);

    $service->startOnboarding('101', 'https://app.example/return', 'https://app.example/refresh');
    $service->startOnboarding('101', 'https://app.example/return', 'https://app.example/refresh');

    expect($gateway->createExpressAccountCalls)->toHaveCount(1)
        ->and($gateway->createOnboardingLinkCalls)->toHaveCount(2)
        ->and($events->published)->toHaveCount(1);
});

it('returns null eligibility check when the seller has never linked an account', function () {
    $service = new SellerOnboardingService(
        new InMemorySellerPayoutAccountRepository,
        new FakeConnectAccountGateway,
        new RecordingDomainEventPublisher,
        new FrozenClock,
    );

    expect($service->checkEligibility('101'))->toBeNull();
});

it('reads eligibility live from the gateway for a linked account', function () {
    $accounts = new InMemorySellerPayoutAccountRepository;
    $gateway = new FakeConnectAccountGateway;
    $service = new SellerOnboardingService($accounts, $gateway, new RecordingDomainEventPublisher, new FrozenClock);
    $service->startOnboarding('101', 'https://app.example/return', 'https://app.example/refresh');

    $gateway->nextEligibility = new PayoutEligibility(true, true);

    $eligibility = $service->checkEligibility('101');

    expect($eligibility?->isPayoutReady())->toBeTrue();
});

it('reports not payout ready when only one of the two Stripe flags is enabled', function () {
    $accounts = new InMemorySellerPayoutAccountRepository;
    $gateway = new FakeConnectAccountGateway;
    $service = new SellerOnboardingService($accounts, $gateway, new RecordingDomainEventPublisher, new FrozenClock);
    $service->startOnboarding('101', 'https://app.example/return', 'https://app.example/refresh');

    $gateway->nextEligibility = new PayoutEligibility(true, false);

    expect($service->checkEligibility('101')?->isPayoutReady())->toBeFalse();
});
