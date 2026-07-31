<?php

declare(strict_types=1);

/**
 * ADR-027 Decision 5: Laravel's native health-check route
 * (`bootstrap/app.php`'s `health: '/up'`), verified rather than merely
 * assumed. No guard needed — the health endpoint is intentionally public
 * so an external uptime monitor can reach it without authenticating.
 */
it('reports healthy on the native /up health-check route', function () {
    $response = $this->get('/up');

    $response->assertStatus(200);
});
