<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
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

        $table->unique(['code', 'jurisdiction_country']);
    });

    Capsule::connection()->statement(
        'CREATE UNIQUE INDEX restricted_categories_global_code_unique '
        .'ON restricted_categories (code) '
        .'WHERE jurisdiction_country IS NULL'
    );
});

afterEach(function () {
    Capsule::schema()->dropIfExists('restricted_categories');
});

it('rejects a second global rule for a category already globally restricted', function () {
    RestrictedCategoryModel::query()->create(['id' => 'a', 'code' => 'medical_emergency', 'jurisdiction_country' => null, 'active' => true]);

    RestrictedCategoryModel::query()->create(['id' => 'b', 'code' => 'medical_emergency', 'jurisdiction_country' => null, 'active' => true]);
})->throws(QueryException::class);

it('still rejects a duplicate jurisdiction-specific rule for the same category and country', function () {
    RestrictedCategoryModel::query()->create(['id' => 'a', 'code' => 'concert', 'jurisdiction_country' => 'FR', 'active' => true]);

    RestrictedCategoryModel::query()->create(['id' => 'b', 'code' => 'concert', 'jurisdiction_country' => 'FR', 'active' => true]);
})->throws(QueryException::class);

it('allows the same category to have distinct rules in different jurisdictions', function () {
    RestrictedCategoryModel::query()->create(['id' => 'a', 'code' => 'concert', 'jurisdiction_country' => 'FR', 'active' => true]);
    RestrictedCategoryModel::query()->create(['id' => 'b', 'code' => 'concert', 'jurisdiction_country' => 'US', 'active' => true]);

    expect(RestrictedCategoryModel::query()->where('code', 'concert')->count())->toBe(2);
});

it('allows a global rule and a jurisdiction-specific rule to coexist for different categories', function () {
    RestrictedCategoryModel::query()->create(['id' => 'a', 'code' => 'medical_emergency', 'jurisdiction_country' => null, 'active' => true]);
    RestrictedCategoryModel::query()->create(['id' => 'b', 'code' => 'concert', 'jurisdiction_country' => 'FR', 'active' => true]);

    expect(RestrictedCategoryModel::query()->count())->toBe(2);
});
