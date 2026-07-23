<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentRestrictedCategoryRepository;
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

it('blocks a category with a global (null-jurisdiction) restriction', function () {
    RestrictedCategoryModel::query()->create([
        'id' => 'rc-1',
        'code' => 'medical_emergency',
        'jurisdiction_country' => null,
        'active' => true,
    ]);

    $repository = new EloquentRestrictedCategoryRepository;

    expect($repository->isCategoryRestricted('medical_emergency', 'US'))->toBeTrue()
        ->and($repository->isCategoryRestricted('medical_emergency', 'ES'))->toBeTrue();
});

it('blocks a category only in the jurisdiction it is restricted for', function () {
    RestrictedCategoryModel::query()->create([
        'id' => 'rc-2',
        'code' => 'concert',
        'jurisdiction_country' => 'FR',
        'active' => true,
    ]);

    $repository = new EloquentRestrictedCategoryRepository;

    expect($repository->isCategoryRestricted('concert', 'FR'))->toBeTrue()
        ->and($repository->isCategoryRestricted('concert', 'US'))->toBeFalse();
});

it('does not block an inactive restriction', function () {
    RestrictedCategoryModel::query()->create([
        'id' => 'rc-3',
        'code' => 'school_admissions',
        'jurisdiction_country' => null,
        'active' => false,
    ]);

    expect((new EloquentRestrictedCategoryRepository)->isCategoryRestricted('school_admissions', 'US'))->toBeFalse();
});

it('does not block a category with no matching rule at all', function () {
    expect((new EloquentRestrictedCategoryRepository)->isCategoryRestricted('concert', 'US'))->toBeFalse();
});
