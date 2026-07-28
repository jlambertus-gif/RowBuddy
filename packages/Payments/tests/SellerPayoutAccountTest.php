<?php

declare(strict_types=1);

use RowBuddy\Payments\Events\SellerPayoutAccountLinked;
use RowBuddy\Payments\SellerPayoutAccount;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('links a payout account and raises a SellerPayoutAccountLinked event', function () {
    $linkedAt = new DateTimeImmutable('2026-10-14 10:00:00');

    $account = SellerPayoutAccount::link('101', 'acct_123', new FrozenClock($linkedAt));

    expect($account->sellerId)->toBe('101')
        ->and($account->stripeAccountId)->toBe('acct_123')
        ->and($account->linkedAt)->toEqual($linkedAt);

    $events = $account->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(SellerPayoutAccountLinked::class)
        ->and($events[0]->payload())->toBe([
            'seller_id' => '101',
            'stripe_account_id' => 'acct_123',
        ]);
});

it('releasing events clears them so they are not dispatched twice', function () {
    $account = SellerPayoutAccount::link('101', 'acct_123', new FrozenClock);

    $account->releaseEvents();

    expect($account->releaseEvents())->toBe([]);
});

it('reconstitutes from persistence without raising any events', function () {
    $linkedAt = new DateTimeImmutable('2026-10-14 10:00:00');

    $account = SellerPayoutAccount::fromPersistence('101', 'acct_123', $linkedAt);

    expect($account->stripeAccountId)->toBe('acct_123')
        ->and($account->releaseEvents())->toBe([]);
});
