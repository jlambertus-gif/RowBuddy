<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentRestrictedCategoryWriteRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\RestrictedCategoryModel;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('restricted_categories', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('code');
        $table->string('jurisdiction_country', 2)->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('restricted_categories');
});

it('returns null for a category that does not exist', function () {
    expect((new EloquentRestrictedCategoryWriteRepository)->findActiveState('missing'))->toBeNull();
});

it('reads back the current active state', function () {
    RestrictedCategoryModel::query()->create(['id' => 'rc-1', 'code' => 'concert', 'jurisdiction_country' => 'FR', 'active' => true]);

    expect((new EloquentRestrictedCategoryWriteRepository)->findActiveState('rc-1'))->toBeTrue();
});

it('deactivates a category without altering its legal content', function () {
    RestrictedCategoryModel::query()->create(['id' => 'rc-1', 'code' => 'concert', 'jurisdiction_country' => 'FR', 'active' => true]);

    (new EloquentRestrictedCategoryWriteRepository)->setActive('rc-1', false);

    $model = RestrictedCategoryModel::query()->find('rc-1');

    expect($model->active)->toBeFalse()
        ->and($model->code)->toBe('concert')
        ->and($model->jurisdiction_country)->toBe('FR');
});

it('reactivates a previously deactivated category', function () {
    RestrictedCategoryModel::query()->create(['id' => 'rc-1', 'code' => 'concert', 'jurisdiction_country' => 'FR', 'active' => false]);

    (new EloquentRestrictedCategoryWriteRepository)->setActive('rc-1', true);

    expect((new EloquentRestrictedCategoryWriteRepository)->findActiveState('rc-1'))->toBeTrue();
});
