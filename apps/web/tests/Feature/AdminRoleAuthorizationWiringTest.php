<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use RowBuddy\Administration\Contracts\AdminRoleAssignmentRepository;
use RowBuddy\Administration\ValueObjects\AdminCapability;
use RowBuddy\Administration\ValueObjects\AdminRole;

uses(RefreshDatabase::class);

it('denies the queues.moderate capability for a user with no role assigned', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows(AdminCapability::QueuesModerate->value))->toBeFalse();
});

it('grants the queues.moderate capability once a role is assigned via the real repository', function () {
    $user = User::factory()->create();

    app(AdminRoleAssignmentRepository::class)->assignRole((string) $user->id, AdminRole::Moderator, null);

    expect(Gate::forUser($user)->allows(AdminCapability::QueuesModerate->value))->toBeTrue();
});

it('denies the restrictions.moderate capability for a user with no role assigned', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows(AdminCapability::RestrictionsModerate->value))->toBeFalse();
});

it('denies the restrictions.moderate capability for a Moderator, per ADR-026 §5', function () {
    $user = User::factory()->create();

    app(AdminRoleAssignmentRepository::class)->assignRole((string) $user->id, AdminRole::Moderator, null);

    expect(Gate::forUser($user)->allows(AdminCapability::RestrictionsModerate->value))->toBeFalse();
});

it('grants the restrictions.moderate capability to an Administrator via the real repository', function () {
    $user = User::factory()->create();

    app(AdminRoleAssignmentRepository::class)->assignRole((string) $user->id, AdminRole::Administrator, null);

    expect(Gate::forUser($user)->allows(AdminCapability::RestrictionsModerate->value))->toBeTrue();
});

it('denies the audit.view capability for a user with no role assigned', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows(AdminCapability::AuditView->value))->toBeFalse();
});

it('denies the audit.view capability for a Moderator, per ADR-026 §6', function () {
    $user = User::factory()->create();

    app(AdminRoleAssignmentRepository::class)->assignRole((string) $user->id, AdminRole::Moderator, null);

    expect(Gate::forUser($user)->allows(AdminCapability::AuditView->value))->toBeFalse();
});

it('grants the audit.view capability to an Administrator', function () {
    $user = User::factory()->create();

    app(AdminRoleAssignmentRepository::class)->assignRole((string) $user->id, AdminRole::Administrator, null);

    expect(Gate::forUser($user)->allows(AdminCapability::AuditView->value))->toBeTrue();
});

it('denies the horizon.view capability for a user with no role assigned', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows(AdminCapability::HorizonView->value))->toBeFalse();
});

it('denies the horizon.view capability for a Moderator, per ADR-027 Decision 5', function () {
    $user = User::factory()->create();

    app(AdminRoleAssignmentRepository::class)->assignRole((string) $user->id, AdminRole::Moderator, null);

    expect(Gate::forUser($user)->allows(AdminCapability::HorizonView->value))->toBeFalse();
});

it('grants the horizon.view capability to an Administrator', function () {
    $user = User::factory()->create();

    app(AdminRoleAssignmentRepository::class)->assignRole((string) $user->id, AdminRole::Administrator, null);

    expect(Gate::forUser($user)->allows(AdminCapability::HorizonView->value))->toBeTrue();
});

it('denies Horizon dashboard access (the real viewHorizon gate) for a guest', function () {
    expect(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse();
});

it('denies Horizon dashboard access (the real viewHorizon gate) for a Moderator', function () {
    $user = User::factory()->create();

    app(AdminRoleAssignmentRepository::class)->assignRole((string) $user->id, AdminRole::Moderator, null);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeFalse();
});

it('grants Horizon dashboard access (the real viewHorizon gate) for an Administrator', function () {
    $user = User::factory()->create();

    app(AdminRoleAssignmentRepository::class)->assignRole((string) $user->id, AdminRole::Administrator, null);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeTrue();
});

it('assigns a role end-to-end through the documented engineering console command', function () {
    $user = User::factory()->create(['email' => 'future-admin@example.com']);

    $exitCode = Artisan::call('admin:assign-role', [
        'email' => 'future-admin@example.com',
        'role' => 'administrator',
    ]);

    expect($exitCode)->toBe(0)
        ->and(app(AdminRoleAssignmentRepository::class)->findRoleForUser((string) $user->id))->toBe(AdminRole::Administrator)
        ->and(Gate::forUser($user)->allows(AdminCapability::QueuesModerate->value))->toBeTrue();
});

it('the console command fails cleanly for an invalid role', function () {
    User::factory()->create(['email' => 'invalid-role@example.com']);

    $exitCode = Artisan::call('admin:assign-role', [
        'email' => 'invalid-role@example.com',
        'role' => 'super-admin',
    ]);

    expect($exitCode)->toBe(1);
});

it('the console command fails cleanly for an unknown email', function () {
    $exitCode = Artisan::call('admin:assign-role', [
        'email' => 'nobody@example.com',
        'role' => 'administrator',
    ]);

    expect($exitCode)->toBe(1);
});
