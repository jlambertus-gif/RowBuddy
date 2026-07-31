<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentJurisdictionRuleWriteRepository;
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
        $table->boolean('active')->default(true);
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('jurisdiction_rules');
});

it('returns null for a rule that does not exist', function () {
    expect((new EloquentJurisdictionRuleWriteRepository)->findActiveState('missing'))->toBeNull();
});

it('reads back the current active state', function () {
    JurisdictionRuleModel::query()->create([
        'id' => 'rule-1',
        'jurisdiction_country' => 'US',
        'category' => null,
        'permitted' => true,
        'effective_from' => '2026-01-01 00:00:00',
        'effective_to' => null,
        'active' => true,
    ]);

    expect((new EloquentJurisdictionRuleWriteRepository)->findActiveState('rule-1'))->toBeTrue();
});

it('deactivates a rule without altering its legal content or effective window', function () {
    JurisdictionRuleModel::query()->create([
        'id' => 'rule-1',
        'jurisdiction_country' => 'US',
        'category' => 'concert',
        'permitted' => true,
        'effective_from' => '2026-01-01 00:00:00',
        'effective_to' => '2026-09-01 00:00:00',
        'active' => true,
    ]);

    (new EloquentJurisdictionRuleWriteRepository)->setActive('rule-1', false);

    $model = JurisdictionRuleModel::query()->find('rule-1');

    expect($model->active)->toBeFalse()
        ->and($model->permitted)->toBeTrue()
        ->and($model->jurisdiction_country)->toBe('US')
        ->and($model->category)->toBe('concert')
        ->and($model->effective_from->toDateTimeString())->toBe('2026-01-01 00:00:00')
        ->and($model->effective_to->toDateTimeString())->toBe('2026-09-01 00:00:00');
});

it('reactivates a previously deactivated rule', function () {
    JurisdictionRuleModel::query()->create([
        'id' => 'rule-1',
        'jurisdiction_country' => 'US',
        'category' => null,
        'permitted' => true,
        'effective_from' => '2026-01-01 00:00:00',
        'effective_to' => null,
        'active' => false,
    ]);

    (new EloquentJurisdictionRuleWriteRepository)->setActive('rule-1', true);

    expect((new EloquentJurisdictionRuleWriteRepository)->findActiveState('rule-1'))->toBeTrue();
});
