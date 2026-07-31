<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RowBuddy\Administration\Application\RestrictionActivationService;
use RowBuddy\Administration\Contracts\AdminRoleAssignmentRepository;
use RowBuddy\Administration\Infrastructure\Eloquent\AdminActionModel;
use RowBuddy\Administration\ValueObjects\AdminCapability;
use RowBuddy\Administration\ValueObjects\AdminRole;
use RowBuddy\Queues\Contracts\RestrictedCategoryRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\JurisdictionRuleModel;
use RowBuddy\Queues\Infrastructure\Eloquent\RestrictedCategoryModel;

uses(RefreshDatabase::class);

/**
 * RestrictionActivationService itself performs no authorization check
 * (ADR-026 §5, corrected before Sprint 3 committed — mirrors
 * AccountSuspensionService's Sprint 2 posture exactly). Capability
 * authorization at the `restrictions.moderate` Gate is proven
 * separately in AdminRoleAuthorizationWiringTest; this file proves the
 * service's own business behavior, plus — in the first test below — the
 * composition-root pattern (Gate::authorize() before invoking the
 * service) that keeps toggling blocked for an unauthorized caller in
 * practice, exactly as a future controller or console command would.
 */
it('blocks an unauthorized caller when the composition root checks the Gate before invoking the service', function () {
    $admin = User::factory()->create();
    $category = RestrictedCategoryModel::query()->create([
        'id' => (string) Str::uuid(),
        'code' => 'wiring-test-category',
        'jurisdiction_country' => null,
        'active' => true,
    ]);

    $attempt = function () use ($admin, $category) {
        Gate::forUser($admin)->authorize(AdminCapability::RestrictionsModerate->value);

        app(RestrictionActivationService::class)->setRestrictedCategoryActive(
            $category->id,
            (string) $admin->id,
            false,
            'attempted toggle',
        );
    };

    expect($attempt)->toThrow(AuthorizationException::class);

    expect($category->refresh()->active)->toBeTrue()
        ->and(AdminActionModel::query()->count())->toBe(0);
});

it('lets an Administrator deactivate a restricted category end-to-end, reflected by the real gate and audit log', function () {
    $admin = User::factory()->create();
    app(AdminRoleAssignmentRepository::class)->assignRole((string) $admin->id, AdminRole::Administrator, null);

    $category = RestrictedCategoryModel::query()->create([
        'id' => (string) Str::uuid(),
        'code' => 'wiring-test-category',
        'jurisdiction_country' => null,
        'active' => true,
    ]);

    expect(app(RestrictedCategoryRepository::class)->isCategoryRestricted('wiring-test-category', 'US'))->toBeTrue();

    Gate::forUser($admin)->authorize(AdminCapability::RestrictionsModerate->value);

    app(RestrictionActivationService::class)->setRestrictedCategoryActive(
        $category->id,
        (string) $admin->id,
        false,
        'Temporary operational pause for legal re-review.',
    );

    expect($category->refresh()->active)->toBeFalse()
        ->and($category->code)->toBe('wiring-test-category')
        ->and(app(RestrictedCategoryRepository::class)->isCategoryRestricted('wiring-test-category', 'US'))->toBeFalse();

    $action = AdminActionModel::query()->sole();

    expect($action->action_type)->toBe('restricted_category_deactivated')
        ->and((string) $action->admin_id)->toBe((string) $admin->id)
        ->and($action->target_type)->toBe('restricted_category')
        ->and($action->target_id)->toBe($category->id)
        ->and($action->reason)->toBe('Temporary operational pause for legal re-review.')
        ->and($action->previous_state)->toBeTrue()
        ->and($action->new_state)->toBeFalse();
});

it('lets an Administrator reactivate a jurisdiction rule end-to-end, restoring gating', function () {
    $admin = User::factory()->create();
    app(AdminRoleAssignmentRepository::class)->assignRole((string) $admin->id, AdminRole::Administrator, null);

    $rule = JurisdictionRuleModel::query()->create([
        'id' => (string) Str::uuid(),
        'jurisdiction_country' => 'US',
        'category' => null,
        'permitted' => true,
        'effective_from' => '2026-01-01 00:00:00',
        'effective_to' => null,
        'active' => false,
    ]);

    Gate::forUser($admin)->authorize(AdminCapability::RestrictionsModerate->value);

    app(RestrictionActivationService::class)->setJurisdictionRuleActive(
        $rule->id,
        (string) $admin->id,
        true,
        'Reinstated after legal re-review.',
    );

    expect($rule->refresh()->active)->toBeTrue()
        ->and($rule->permitted)->toBeTrue()
        ->and($rule->jurisdiction_country)->toBe('US');

    $action = AdminActionModel::query()->sole();

    expect($action->action_type)->toBe('jurisdiction_rule_activated')
        ->and($action->previous_state)->toBeFalse()
        ->and($action->new_state)->toBeTrue();
});
