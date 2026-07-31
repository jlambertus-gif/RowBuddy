<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Administration\Infrastructure\Eloquent\AccountStandingModel;
use RowBuddy\Administration\Infrastructure\Eloquent\EloquentAccountStandingRepository;
use RowBuddy\Administration\ValueObjects\AccountStandingState;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('account_standings', function (Blueprint $table) {
        $table->unsignedBigInteger('user_id')->primary();
        $table->string('state');
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('account_standings');
});

it('reports Active for a user with no persisted row', function () {
    expect((new EloquentAccountStandingRepository)->findStanding('101'))->toBe(AccountStandingState::Active);
});

it('persists and finds a suspended standing', function () {
    $repository = new EloquentAccountStandingRepository;

    $repository->setStanding('101', AccountStandingState::Suspended);

    expect($repository->findStanding('101'))->toBe(AccountStandingState::Suspended);
});

it('setting a standing again replaces the prior value rather than adding a second row', function () {
    $repository = new EloquentAccountStandingRepository;

    $repository->setStanding('101', AccountStandingState::Suspended);
    $repository->setStanding('101', AccountStandingState::Active);

    expect($repository->findStanding('101'))->toBe(AccountStandingState::Active)
        ->and(AccountStandingModel::query()->count())->toBe(1);
});
