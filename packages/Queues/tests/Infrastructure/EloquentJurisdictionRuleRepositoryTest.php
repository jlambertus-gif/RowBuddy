<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentJurisdictionRuleRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\JurisdictionRuleModel;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('jurisdiction_rules', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('jurisdiction_country', 2);
        $table->string('category')->nullable();
        $table->boolean('permitted');
        $table->timestamp('effective_from');
        $table->timestamp('effective_to')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('jurisdiction_rules');
});

it('maps stored rows into JurisdictionRule value objects, including open-ended and country-wide rules', function () {
    JurisdictionRuleModel::query()->create([
        'id' => 'rule-1',
        'jurisdiction_country' => 'US',
        'category' => null,
        'permitted' => true,
        'effective_from' => '2026-01-01 00:00:00',
        'effective_to' => null,
    ]);

    JurisdictionRuleModel::query()->create([
        'id' => 'rule-2',
        'jurisdiction_country' => 'US',
        'category' => 'concert',
        'permitted' => false,
        'effective_from' => '2026-03-01 00:00:00',
        'effective_to' => '2026-09-01 00:00:00',
    ]);

    $rules = (new EloquentJurisdictionRuleRepository)->findForCountry('US');

    expect($rules)->toHaveCount(2);

    $global = collect($rules)->firstWhere('category', null);
    $categorySpecific = collect($rules)->firstWhere('category', 'concert');

    expect($global->jurisdictionCountry)->toBe('US')
        ->and($global->permitted)->toBeTrue()
        ->and($global->effectiveTo)->toBeNull()
        ->and($categorySpecific->permitted)->toBeFalse()
        ->and($categorySpecific->effectiveFrom)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($categorySpecific->effectiveTo)->toBeInstanceOf(DateTimeImmutable::class);
});

it('returns an empty list for a country with no rules at all', function () {
    expect((new EloquentJurisdictionRuleRepository)->findForCountry('ZZ'))->toBe([]);
});

it('does not return rules belonging to a different country', function () {
    JurisdictionRuleModel::query()->create([
        'id' => 'rule-3',
        'jurisdiction_country' => 'FR',
        'category' => null,
        'permitted' => true,
        'effective_from' => '2026-01-01 00:00:00',
        'effective_to' => null,
    ]);

    expect((new EloquentJurisdictionRuleRepository)->findForCountry('US'))->toBe([]);
});
