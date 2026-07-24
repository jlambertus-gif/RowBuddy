<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * apps/web's test suite runs against in-memory SQLite (Sprint 4's env
 * fix), which has no spatial support at all — PostGISQueueDiscoveryRepository
 * no-ops on any non-pgsql driver (ADR-007 §8). This suite therefore
 * proves routing, validation, and public (no-auth) access only; real
 * spatial matching is proven against real PostgreSQL/PostGIS in
 * packages/Queues/tests/Integration/.
 */
it('does not require authentication', function () {
    $response = $this->get('/queues/discover?latitude=32.7157&longitude=-117.1611');

    $response->assertOk();
});

it('returns an empty result set under sqlite, with correct pagination metadata', function () {
    $response = $this->get('/queues/discover?latitude=32.7157&longitude=-117.1611');

    $response->assertOk()->assertJson([
        'data' => [],
        'meta' => [
            'page' => 1,
            'per_page' => 20,
            'total' => 0,
            'has_more' => false,
        ],
    ]);
});

it('honors page and per_page query parameters', function () {
    $response = $this->get('/queues/discover?latitude=0&longitude=0&page=2&per_page=5');

    $response->assertOk()->assertJson([
        'meta' => [
            'page' => 2,
            'per_page' => 5,
        ],
    ]);
});

it('requires latitude and longitude', function () {
    $response = $this->get('/queues/discover');

    $response->assertSessionHasErrors(['latitude', 'longitude']);
});

it('rejects out-of-range coordinates', function () {
    $response = $this->get('/queues/discover?latitude=200&longitude=-117.1611');

    $response->assertSessionHasErrors('latitude');
});

it('rejects a per_page above the server-enforced cap', function () {
    $response = $this->get('/queues/discover?latitude=0&longitude=0&per_page=500');

    $response->assertSessionHasErrors('per_page');
});
