<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\FixedPaymentProcessingCostPolicy;
use RowBuddy\Payments\Application\PayoutPreparationService;
use RowBuddy\Payments\Application\SellerSettlementCalculator;
use RowBuddy\Payments\PaymentIntent;
use RowBuddy\Payments\SellerPayoutAccount;
use RowBuddy\Payments\Tests\Fakes\FakeConnectAccountGateway;
use RowBuddy\Payments\Tests\Fakes\InMemoryPaymentIntentRepository;
use RowBuddy\Payments\Tests\Fakes\InMemorySellerPayoutAccountRepository;
use RowBuddy\Payments\ValueObjects\PayoutEligibility;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('reports nothing ready when no payment or payout account exists yet', function () {
    $service = new PayoutPreparationService(
        new InMemoryPaymentIntentRepository,
        new InMemorySellerPayoutAccountRepository,
        new FakeConnectAccountGateway,
        new SellerSettlementCalculator(new FixedPaymentProcessingCostPolicy(3, usd(30))),
    );

    $preparation = $service->prepareFor('auction-1', '101');

    expect($preparation->paymentAuthorized)->toBeFalse()
        ->and($preparation->sellerAccountLinked)->toBeFalse()
        ->and($preparation->sellerPayoutEligible)->toBeFalse()
        ->and($preparation->expectedSettlementAmount)->toBeNull()
        ->and($preparation->isReadyForPayout())->toBeFalse();
});

it('reports ready with the expected settlement when everything is in place', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntents->record(PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', '101', '102', usd(11000), usd(1000), usd(50000), new FrozenClock,
    ));
    $payoutAccounts = new InMemorySellerPayoutAccountRepository;
    $payoutAccounts->record(SellerPayoutAccount::link('101', 'acct_123', new FrozenClock));
    $gateway = new FakeConnectAccountGateway;
    $gateway->nextEligibility = new PayoutEligibility(true, true);

    $service = new PayoutPreparationService(
        $paymentIntents,
        $payoutAccounts,
        $gateway,
        new SellerSettlementCalculator(new FixedPaymentProcessingCostPolicy(3, usd(30))),
    );

    $preparation = $service->prepareFor('auction-1', '101');

    expect($preparation->paymentAuthorized)->toBeTrue()
        ->and($preparation->sellerAccountLinked)->toBeTrue()
        ->and($preparation->sellerPayoutEligible)->toBeTrue()
        ->and($preparation->expectedSettlementAmount?->equals(usd(9670)))->toBeTrue()
        ->and($preparation->isReadyForPayout())->toBeTrue();
});

it('reports payment not authorized when the PaymentIntent was declined', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntents->record(PaymentIntent::declineAuthorization(
        'payment-1', 'auction-1', 'bid-1', '101', '102', usd(11000), usd(1000), usd(50000), 'card_declined', new FrozenClock,
    ));

    $service = new PayoutPreparationService(
        $paymentIntents,
        new InMemorySellerPayoutAccountRepository,
        new FakeConnectAccountGateway,
        new SellerSettlementCalculator(new FixedPaymentProcessingCostPolicy(3, usd(30))),
    );

    $preparation = $service->prepareFor('auction-1', '101');

    expect($preparation->paymentAuthorized)->toBeFalse()
        ->and($preparation->expectedSettlementAmount)->toBeNull()
        ->and($preparation->isReadyForPayout())->toBeFalse();
});

it('reports not eligible when the linked account has not finished onboarding', function () {
    $payoutAccounts = new InMemorySellerPayoutAccountRepository;
    $payoutAccounts->record(SellerPayoutAccount::link('101', 'acct_123', new FrozenClock));
    $gateway = new FakeConnectAccountGateway;
    $gateway->nextEligibility = new PayoutEligibility(true, false);

    $service = new PayoutPreparationService(
        new InMemoryPaymentIntentRepository,
        $payoutAccounts,
        $gateway,
        new SellerSettlementCalculator(new FixedPaymentProcessingCostPolicy(3, usd(30))),
    );

    $preparation = $service->prepareFor('auction-1', '101');

    expect($preparation->sellerAccountLinked)->toBeTrue()
        ->and($preparation->sellerPayoutEligible)->toBeFalse()
        ->and($preparation->isReadyForPayout())->toBeFalse();
});

it('still computes the expected settlement even when the seller has no linked payout account yet', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntents->record(PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', '101', '102', usd(11000), usd(1000), usd(50000), new FrozenClock,
    ));

    $service = new PayoutPreparationService(
        $paymentIntents,
        new InMemorySellerPayoutAccountRepository,
        new FakeConnectAccountGateway,
        new SellerSettlementCalculator(new FixedPaymentProcessingCostPolicy(3, usd(30))),
    );

    $preparation = $service->prepareFor('auction-1', '101');

    expect($preparation->sellerAccountLinked)->toBeFalse()
        ->and($preparation->expectedSettlementAmount?->equals(usd(9670)))->toBeTrue()
        ->and($preparation->isReadyForPayout())->toBeFalse();
});
