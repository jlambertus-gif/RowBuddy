<?php

declare(strict_types=1);

use RowBuddy\Queues\Application\RestrictedCategoryActivationService;
use RowBuddy\Queues\Tests\Fakes\InMemoryRestrictedCategoryWriteRepository;

it('reports null for a category that does not exist', function () {
    $service = new RestrictedCategoryActivationService(new InMemoryRestrictedCategoryWriteRepository);

    expect($service->findActiveState('missing'))->toBeNull();
});

it('toggles a category active and reports the new state', function () {
    $categories = new InMemoryRestrictedCategoryWriteRepository;
    $categories->states['rc-1'] = true;
    $service = new RestrictedCategoryActivationService($categories);

    $service->setActive('rc-1', false);

    expect($service->findActiveState('rc-1'))->toBeFalse();
});
