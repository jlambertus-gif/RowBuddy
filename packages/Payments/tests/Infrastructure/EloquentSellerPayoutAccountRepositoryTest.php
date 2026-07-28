<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Payments\Exceptions\SellerPayoutAccountAlreadyLinked;
use RowBuddy\Payments\Infrastructure\Eloquent\EloquentSellerPayoutAccountRepository;
use RowBuddy\Payments\SellerPayoutAccount;
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

    Capsule::schema()->create('seller_payout_accounts', function (Blueprint $table) {
        $table->unsignedBigInteger('seller_id')->primary();
        $table->string('stripe_account_id')->unique();
        $table->timestamp('linked_at');
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('seller_payout_accounts');
});

it('records a linked account and finds it by seller id', function () {
    $repository = new EloquentSellerPayoutAccountRepository;
    $linkedAt = new DateTimeImmutable('2026-10-14 10:00:00');

    $repository->record(SellerPayoutAccount::link('101', 'acct_123', new FrozenClock($linkedAt)));

    $found = $repository->findBySellerId('101');

    expect($found)->not->toBeNull()
        ->and($found->sellerId)->toBe('101')
        ->and($found->stripeAccountId)->toBe('acct_123')
        ->and($found->linkedAt)->toEqual($linkedAt);
});

it('returns null when the seller has no linked account', function () {
    expect((new EloquentSellerPayoutAccountRepository)->findBySellerId('missing'))->toBeNull();
});

it('rejects linking a second account for the same seller', function () {
    $repository = new EloquentSellerPayoutAccountRepository;
    $repository->record(SellerPayoutAccount::link('101', 'acct_123', new FrozenClock));

    expect(fn () => $repository->record(SellerPayoutAccount::link('101', 'acct_456', new FrozenClock)))
        ->toThrow(SellerPayoutAccountAlreadyLinked::class);
});
