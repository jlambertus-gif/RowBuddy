<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Queues\Infrastructure\Eloquent\JurisdictionRuleModel;

/**
 * Proves the exact schema operation
 * `2026_07_31_000300_add_active_to_jurisdiction_rules_table.php` performs
 * (ADR-026 Architecture Refinements §6): adding a `boolean` column with
 * `default(true)` backfills every pre-existing row to active, with no
 * separate backfill statement required.
 */
beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    // The pre-migration schema, matching
    // 2026_07_23_000003_create_jurisdiction_rules_table.php exactly —
    // no `active` column yet.
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

it('backfills every pre-existing row to active when the column is added', function () {
    Capsule::connection()->table('jurisdiction_rules')->insert([
        'id' => 'rule-pre-existing',
        'jurisdiction_country' => 'US',
        'category' => null,
        'permitted' => true,
        'effective_from' => '2026-01-01 00:00:00',
        'effective_to' => null,
    ]);

    Capsule::schema()->table('jurisdiction_rules', function (Blueprint $table) {
        $table->boolean('active')->default(true)->after('effective_to');
    });

    $rule = JurisdictionRuleModel::query()->find('rule-pre-existing');

    expect($rule)->not->toBeNull()
        ->and($rule->active)->toBeTrue();
});

it('defaults a new row created after the migration to active as well', function () {
    Capsule::schema()->table('jurisdiction_rules', function (Blueprint $table) {
        $table->boolean('active')->default(true)->after('effective_to');
    });

    Capsule::connection()->table('jurisdiction_rules')->insert([
        'id' => 'rule-new',
        'jurisdiction_country' => 'US',
        'category' => null,
        'permitted' => true,
        'effective_from' => '2026-01-01 00:00:00',
        'effective_to' => null,
    ]);

    expect(JurisdictionRuleModel::query()->find('rule-new')->active)->toBeTrue();
});
