<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Administration\Infrastructure\Eloquent\AdminRoleAssignmentModel;
use RowBuddy\Administration\Infrastructure\Eloquent\EloquentAdminRoleAssignmentRepository;
use RowBuddy\Administration\ValueObjects\AdminRole;
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

    Capsule::schema()->create('admin_role_assignments', function (Blueprint $table) {
        $table->unsignedBigInteger('user_id')->primary();
        $table->string('role');
        $table->unsignedBigInteger('assigned_by')->nullable();
        $table->timestamp('assigned_at');
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('admin_role_assignments');
});

it('returns null when no role is assigned', function () {
    expect((new EloquentAdminRoleAssignmentRepository(new FrozenClock))->findRoleForUser('1'))->toBeNull();
});

it('assigns and finds a role for a user', function () {
    $repository = new EloquentAdminRoleAssignmentRepository(new FrozenClock);

    $repository->assignRole('1', AdminRole::Administrator, '2');

    expect($repository->findRoleForUser('1'))->toBe(AdminRole::Administrator);
});

it('assigning a role again replaces the prior assignment rather than adding a second one', function () {
    $repository = new EloquentAdminRoleAssignmentRepository(new FrozenClock);

    $repository->assignRole('1', AdminRole::Moderator, null);
    $repository->assignRole('1', AdminRole::Administrator, '2');

    expect($repository->findRoleForUser('1'))->toBe(AdminRole::Administrator)
        ->and(AdminRoleAssignmentModel::query()->count())->toBe(1);
});

it('supports a null assigned_by for engineering-controlled assignment', function () {
    $repository = new EloquentAdminRoleAssignmentRepository(new FrozenClock);

    $repository->assignRole('1', AdminRole::Administrator, null);

    expect($repository->findRoleForUser('1'))->toBe(AdminRole::Administrator);
});
