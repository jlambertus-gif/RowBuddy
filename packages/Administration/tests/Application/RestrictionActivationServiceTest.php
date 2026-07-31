<?php

declare(strict_types=1);

use RowBuddy\Administration\Application\RestrictionActivationService;
use RowBuddy\Administration\Exceptions\AdministrativeActionReasonRequired;
use RowBuddy\Administration\Exceptions\AdministrativeTargetNotFound;
use RowBuddy\Administration\Tests\Fakes\InMemoryAdminActionLog;
use RowBuddy\Administration\Tests\Fakes\InMemoryJurisdictionRuleActivationGateway;
use RowBuddy\Administration\Tests\Fakes\InMemoryRestrictedCategoryActivationGateway;
use RowBuddy\Administration\ValueObjects\AdministrativeActionType;

/**
 * Authorization is deliberately out of scope here — mirroring
 * AccountSuspensionServiceTest (Sprint 2), this service assumes the
 * caller is already authorized; capability enforcement at the
 * `restrictions.moderate` Gate boundary is proven separately in
 * apps/web's AdminRoleAuthorizationWiringTest.
 */
function makeRestrictionActivationService(
    InMemoryRestrictedCategoryActivationGateway $categories,
    InMemoryJurisdictionRuleActivationGateway $jurisdictionRules,
    InMemoryAdminActionLog $actions,
): RestrictionActivationService {
    return new RestrictionActivationService($categories, $jurisdictionRules, $actions);
}

// --- Restricted categories ---

it('activates a restricted category and records exactly one admin action', function () {
    $categories = new InMemoryRestrictedCategoryActivationGateway;
    $categories->states['rc-1'] = false;
    $actions = new InMemoryAdminActionLog;
    $service = makeRestrictionActivationService($categories, new InMemoryJurisdictionRuleActivationGateway, $actions);

    $service->setRestrictedCategoryActive('rc-1', '999', true, 'Legal review cleared this category.');

    expect($categories->findActiveState('rc-1'))->toBeTrue()
        ->and($actions->recorded)->toHaveCount(1)
        ->and($actions->recorded[0]['type'])->toBe(AdministrativeActionType::RestrictedCategoryActivated)
        ->and($actions->recorded[0]['adminId'])->toBe('999')
        ->and($actions->recorded[0]['targetType'])->toBe('restricted_category')
        ->and($actions->recorded[0]['targetId'])->toBe('rc-1')
        ->and($actions->recorded[0]['reason'])->toBe('Legal review cleared this category.')
        ->and($actions->recorded[0]['previousState'])->toBeFalse()
        ->and($actions->recorded[0]['newState'])->toBeTrue();
});

it('deactivates a restricted category and does not alter its legal content', function () {
    $categories = new InMemoryRestrictedCategoryActivationGateway;
    $categories->states['rc-1'] = true;
    $actions = new InMemoryAdminActionLog;
    $service = makeRestrictionActivationService($categories, new InMemoryJurisdictionRuleActivationGateway, $actions);

    $service->setRestrictedCategoryActive('rc-1', '999', false, 'Temporary operational pause.');

    expect($categories->findActiveState('rc-1'))->toBeFalse()
        ->and($actions->recorded[0]['type'])->toBe(AdministrativeActionType::RestrictedCategoryDeactivated);
});

it('rejects toggling a restricted category with a blank reason', function () {
    $categories = new InMemoryRestrictedCategoryActivationGateway;
    $categories->states['rc-1'] = true;
    $service = makeRestrictionActivationService($categories, new InMemoryJurisdictionRuleActivationGateway, new InMemoryAdminActionLog);

    expect(fn () => $service->setRestrictedCategoryActive('rc-1', '999', false, '   '))
        ->toThrow(AdministrativeActionReasonRequired::class);
});

it('rejects toggling a restricted category that does not exist', function () {
    $service = makeRestrictionActivationService(new InMemoryRestrictedCategoryActivationGateway, new InMemoryJurisdictionRuleActivationGateway, new InMemoryAdminActionLog);

    expect(fn () => $service->setRestrictedCategoryActive('missing', '999', false, 'reason'))
        ->toThrow(AdministrativeTargetNotFound::class);
});

// --- Jurisdiction rules ---

it('activates a jurisdiction rule and records exactly one admin action', function () {
    $jurisdictionRules = new InMemoryJurisdictionRuleActivationGateway;
    $jurisdictionRules->states['rule-1'] = false;
    $actions = new InMemoryAdminActionLog;
    $service = makeRestrictionActivationService(new InMemoryRestrictedCategoryActivationGateway, $jurisdictionRules, $actions);

    $service->setJurisdictionRuleActive('rule-1', '999', true, 'Reinstated after legal review.');

    expect($jurisdictionRules->findActiveState('rule-1'))->toBeTrue()
        ->and($actions->recorded)->toHaveCount(1)
        ->and($actions->recorded[0]['type'])->toBe(AdministrativeActionType::JurisdictionRuleActivated)
        ->and($actions->recorded[0]['targetType'])->toBe('jurisdiction_rule')
        ->and($actions->recorded[0]['targetId'])->toBe('rule-1')
        ->and($actions->recorded[0]['previousState'])->toBeFalse()
        ->and($actions->recorded[0]['newState'])->toBeTrue();
});

it('deactivates a jurisdiction rule and records the deactivation type', function () {
    $jurisdictionRules = new InMemoryJurisdictionRuleActivationGateway;
    $jurisdictionRules->states['rule-1'] = true;
    $actions = new InMemoryAdminActionLog;
    $service = makeRestrictionActivationService(new InMemoryRestrictedCategoryActivationGateway, $jurisdictionRules, $actions);

    $service->setJurisdictionRuleActive('rule-1', '999', false, 'Market under legal re-review.');

    expect($jurisdictionRules->findActiveState('rule-1'))->toBeFalse()
        ->and($actions->recorded[0]['type'])->toBe(AdministrativeActionType::JurisdictionRuleDeactivated);
});

it('rejects toggling a jurisdiction rule with a blank reason', function () {
    $jurisdictionRules = new InMemoryJurisdictionRuleActivationGateway;
    $jurisdictionRules->states['rule-1'] = true;
    $service = makeRestrictionActivationService(new InMemoryRestrictedCategoryActivationGateway, $jurisdictionRules, new InMemoryAdminActionLog);

    expect(fn () => $service->setJurisdictionRuleActive('rule-1', '999', false, ''))
        ->toThrow(AdministrativeActionReasonRequired::class);
});

it('rejects toggling a jurisdiction rule that does not exist', function () {
    $service = makeRestrictionActivationService(new InMemoryRestrictedCategoryActivationGateway, new InMemoryJurisdictionRuleActivationGateway, new InMemoryAdminActionLog);

    expect(fn () => $service->setJurisdictionRuleActive('missing', '999', false, 'reason'))
        ->toThrow(AdministrativeTargetNotFound::class);
});
