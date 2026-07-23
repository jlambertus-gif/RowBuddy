<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Queues\Database\Seeders\RestrictedCategorySeeder;
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

it('seeds every universally restricted category as an active, global rule', function () {
    (new RestrictedCategorySeeder)->run();

    $repository = new EloquentRestrictedCategoryRepository;

    foreach ([
        'medical_emergency',
        'elections_voting',
        'immigration_consular',
        'courts_legal_proceedings',
        'government_benefits',
        'essential_public_services',
        'school_admissions',
        'disaster_relief',
        'food_aid',
    ] as $code) {
        expect($repository->isCategoryRestricted($code, 'US'))->toBeTrue()
            ->and($repository->isCategoryRestricted($code, 'ES'))->toBeTrue();
    }

    expect($repository->isCategoryRestricted('concert', 'US'))->toBeFalse();
});

it('is idempotent when run twice', function () {
    $seeder = new RestrictedCategorySeeder;
    $seeder->run();
    $seeder->run();

    expect(RestrictedCategoryModel::query()->where('code', 'medical_emergency')->count())->toBe(1);
});
