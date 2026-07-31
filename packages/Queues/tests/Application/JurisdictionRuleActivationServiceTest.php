<?php

declare(strict_types=1);

use RowBuddy\Queues\Application\JurisdictionRuleActivationService;
use RowBuddy\Queues\Tests\Fakes\InMemoryJurisdictionRuleWriteRepository;

it('reports null for a rule that does not exist', function () {
    $service = new JurisdictionRuleActivationService(new InMemoryJurisdictionRuleWriteRepository);

    expect($service->findActiveState('missing'))->toBeNull();
});

it('toggles a rule active and reports the new state', function () {
    $rules = new InMemoryJurisdictionRuleWriteRepository;
    $rules->states['rule-1'] = true;
    $service = new JurisdictionRuleActivationService($rules);

    $service->setActive('rule-1', false);

    expect($service->findActiveState('rule-1'))->toBeFalse();
});
