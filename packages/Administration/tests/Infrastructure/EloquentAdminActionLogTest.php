<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Administration\Infrastructure\Eloquent\AdminActionModel;
use RowBuddy\Administration\Infrastructure\Eloquent\EloquentAdminActionLog;
use RowBuddy\Administration\ValueObjects\AccountStandingState;
use RowBuddy\Administration\ValueObjects\AdministrativeActionType;
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

    Capsule::schema()->create('admin_actions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->unsignedBigInteger('admin_id');
        $table->string('action_type');
        $table->string('target_type');
        $table->string('target_id');
        $table->text('reason');
        $table->json('previous_state')->nullable();
        $table->json('new_state')->nullable();
        $table->timestamp('created_at');
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('admin_actions');
});

it('records a typed administrative action with explicit previous/new state', function () {
    $recordedAt = new DateTimeImmutable('2026-08-01 10:00:00');
    $log = new EloquentAdminActionLog(new FrozenClock($recordedAt));

    $log->record(
        AdministrativeActionType::AccountSuspended,
        '999',
        'user',
        '101',
        'Fraudulent evidence submitted.',
        AccountStandingState::Active,
        AccountStandingState::Suspended,
    );

    $row = AdminActionModel::query()->first();

    expect($row)->not->toBeNull()
        ->and($row->admin_id)->toBe(999)
        ->and($row->action_type)->toBe(AdministrativeActionType::AccountSuspended->value)
        ->and($row->target_type)->toBe('user')
        ->and($row->target_id)->toBe('101')
        ->and($row->reason)->toBe('Fraudulent evidence submitted.')
        ->and($row->previous_state)->toBe(AccountStandingState::Active->value)
        ->and($row->new_state)->toBe(AccountStandingState::Suspended->value)
        ->and($row->created_at)->toEqual($recordedAt);
});

it('records more than one action independently', function () {
    $log = new EloquentAdminActionLog(new FrozenClock);

    $log->record(AdministrativeActionType::AccountSuspended, '999', 'user', '101', 'reason one', AccountStandingState::Active, AccountStandingState::Suspended);
    $log->record(AdministrativeActionType::AccountReinstated, '998', 'user', '101', 'reason two', AccountStandingState::Suspended, AccountStandingState::Active);

    expect(AdminActionModel::query()->count())->toBe(2);
});
