<?php

declare(strict_types=1);

use RowBuddy\Administration\Support\AdminRoleCapabilityMap;
use RowBuddy\Administration\ValueObjects\AdminCapability;
use RowBuddy\Administration\ValueObjects\AdminRole;

it('grants queues.moderate to the Moderator role', function () {
    $map = new AdminRoleCapabilityMap;

    expect($map->roleGrants(AdminRole::Moderator, AdminCapability::QueuesModerate))->toBeTrue();
});

it('grants queues.moderate to the Administrator role', function () {
    $map = new AdminRoleCapabilityMap;

    expect($map->roleGrants(AdminRole::Administrator, AdminCapability::QueuesModerate))->toBeTrue();
});

it('returns the full capability list for a role', function () {
    $map = new AdminRoleCapabilityMap;

    expect($map->capabilitiesFor(AdminRole::Moderator))->toBe([
        AdminCapability::QueuesModerate,
    ])
        ->and($map->capabilitiesFor(AdminRole::Administrator))->toBe([
            AdminCapability::QueuesModerate,
            AdminCapability::RestrictionsModerate,
            AdminCapability::DisputesReview,
            AdminCapability::AuditView,
            AdminCapability::HorizonView,
        ]);
});

it('grants restrictions.moderate to the Administrator role only', function () {
    $map = new AdminRoleCapabilityMap;

    expect($map->roleGrants(AdminRole::Administrator, AdminCapability::RestrictionsModerate))->toBeTrue()
        ->and($map->roleGrants(AdminRole::Moderator, AdminCapability::RestrictionsModerate))->toBeFalse();
});

it('grants disputes.review to the Administrator role only', function () {
    $map = new AdminRoleCapabilityMap;

    expect($map->roleGrants(AdminRole::Administrator, AdminCapability::DisputesReview))->toBeTrue()
        ->and($map->roleGrants(AdminRole::Moderator, AdminCapability::DisputesReview))->toBeFalse();
});

it('grants audit.view to the Administrator role only', function () {
    $map = new AdminRoleCapabilityMap;

    expect($map->roleGrants(AdminRole::Administrator, AdminCapability::AuditView))->toBeTrue()
        ->and($map->roleGrants(AdminRole::Moderator, AdminCapability::AuditView))->toBeFalse();
});

it('grants horizon.view to the Administrator role only', function () {
    $map = new AdminRoleCapabilityMap;

    expect($map->roleGrants(AdminRole::Administrator, AdminCapability::HorizonView))->toBeTrue()
        ->and($map->roleGrants(AdminRole::Moderator, AdminCapability::HorizonView))->toBeFalse();
});
