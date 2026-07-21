<?php

declare(strict_types=1);

/**
 * Enforces the repository's core architectural boundary (see
 * docs/architecture/architecture-overview.md and
 * docs/decisions/001-modular-monolith.md): every bounded context lives in
 * its own Composer package under packages/*, never as a namespace inside
 * apps/web/app. apps/web is a composition root (routes, controllers,
 * Inertia pages) that consumes packages — it must never grow its own copy
 * of a module's domain logic.
 */
it('keeps bounded-context modules out of apps/web/app', function () {
    $forbiddenModuleFolders = [
        'Identity',
        'Localization',
        'Queues',
        'QueuePresence',
        'Verification',
        'Auctions',
        'Bids',
        'Payments',
        'Transfers',
        'Disputes',
        'Ratings',
        'Notifications',
        'Administration',
        'Audit',
        'FraudRisk',
    ];

    $appPath = base_path('app');

    foreach ($forbiddenModuleFolders as $folder) {
        $path = $appPath.DIRECTORY_SEPARATOR.$folder;

        expect(is_dir($path))->toBeFalse(
            "Found app/{$folder} inside apps/web. Bounded-context modules must live in packages/{$folder}, not in apps/web/app."
        );
    }
});
