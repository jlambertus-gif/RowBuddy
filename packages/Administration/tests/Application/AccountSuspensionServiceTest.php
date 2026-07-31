<?php

declare(strict_types=1);

use RowBuddy\Administration\Application\AccountSuspensionService;
use RowBuddy\Administration\Exceptions\AccountAlreadyActive;
use RowBuddy\Administration\Exceptions\AccountAlreadySuspended;
use RowBuddy\Administration\Exceptions\AdministrativeActionReasonRequired;
use RowBuddy\Administration\Tests\Fakes\InMemoryAccountStandingRepository;
use RowBuddy\Administration\Tests\Fakes\InMemoryAdminActionLog;
use RowBuddy\Administration\ValueObjects\AccountStandingState;
use RowBuddy\Administration\ValueObjects\AdministrativeActionType;

it('suspends an active account and records exactly one admin action', function () {
    $standings = new InMemoryAccountStandingRepository;
    $actions = new InMemoryAdminActionLog;
    $service = new AccountSuspensionService($standings, $actions);

    $service->suspend('101', '999', 'Fraudulent evidence submitted.');

    expect($standings->findStanding('101'))->toBe(AccountStandingState::Suspended)
        ->and($actions->recorded)->toHaveCount(1)
        ->and($actions->recorded[0]['type'])->toBe(AdministrativeActionType::AccountSuspended)
        ->and($actions->recorded[0]['adminId'])->toBe('999')
        ->and($actions->recorded[0]['targetType'])->toBe('user')
        ->and($actions->recorded[0]['targetId'])->toBe('101')
        ->and($actions->recorded[0]['reason'])->toBe('Fraudulent evidence submitted.')
        ->and($actions->recorded[0]['previousState'])->toBe(AccountStandingState::Active)
        ->and($actions->recorded[0]['newState'])->toBe(AccountStandingState::Suspended);
});

it('rejects suspending an already-suspended account', function () {
    $standings = new InMemoryAccountStandingRepository;
    $actions = new InMemoryAdminActionLog;
    $service = new AccountSuspensionService($standings, $actions);
    $service->suspend('101', '999', 'first reason');

    expect(fn () => $service->suspend('101', '999', 'second reason'))
        ->toThrow(AccountAlreadySuspended::class);

    expect($actions->recorded)->toHaveCount(1);
});

it('rejects suspending with a blank reason', function () {
    $service = new AccountSuspensionService(new InMemoryAccountStandingRepository, new InMemoryAdminActionLog);

    expect(fn () => $service->suspend('101', '999', '   '))
        ->toThrow(AdministrativeActionReasonRequired::class);
});

it('reinstates a suspended account and records exactly one admin action', function () {
    $standings = new InMemoryAccountStandingRepository;
    $actions = new InMemoryAdminActionLog;
    $service = new AccountSuspensionService($standings, $actions);
    $service->suspend('101', '999', 'initial suspension');

    $service->reinstate('101', '998', 'appeal reviewed and granted');

    expect($standings->findStanding('101'))->toBe(AccountStandingState::Active)
        ->and($actions->recorded)->toHaveCount(2)
        ->and($actions->recorded[1]['type'])->toBe(AdministrativeActionType::AccountReinstated)
        ->and($actions->recorded[1]['adminId'])->toBe('998')
        ->and($actions->recorded[1]['reason'])->toBe('appeal reviewed and granted')
        ->and($actions->recorded[1]['previousState'])->toBe(AccountStandingState::Suspended)
        ->and($actions->recorded[1]['newState'])->toBe(AccountStandingState::Active);
});

it('rejects reinstating an already-active account', function () {
    $service = new AccountSuspensionService(new InMemoryAccountStandingRepository, new InMemoryAdminActionLog);

    expect(fn () => $service->reinstate('101', '999', 'no-op attempt'))
        ->toThrow(AccountAlreadyActive::class);
});

it('rejects reinstating with a blank reason', function () {
    $standings = new InMemoryAccountStandingRepository;
    $actions = new InMemoryAdminActionLog;
    $service = new AccountSuspensionService($standings, $actions);
    $service->suspend('101', '999', 'initial suspension');

    expect(fn () => $service->reinstate('101', '999', ''))
        ->toThrow(AdministrativeActionReasonRequired::class);
});
