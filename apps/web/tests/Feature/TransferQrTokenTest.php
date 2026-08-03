<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RowBuddy\Transfers\Infrastructure\Eloquent\TransferModel;

uses(RefreshDatabase::class);

function createTransferRowForQrTest(User $seller, User $buyer): string
{
    $id = (string) Str::uuid();

    TransferModel::query()->create([
        'id' => $id,
        'auction_id' => (string) Str::uuid(),
        'winning_bid_id' => (string) Str::uuid(),
        'seller_id' => $seller->id,
        'buyer_id' => $buyer->id,
        'qr_token_hash' => hash('sha256', 'plaintext-token'),
        'issued_at' => now(),
        'expires_at' => now()->addHours(24),
        'status' => 'issued',
    ]);

    return $id;
}

it('redirects unauthenticated users to login', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transferId = createTransferRowForQrTest($seller, $buyer);
    Cache::put("transfers.{$transferId}.qr_token", 'plaintext-token', now()->addHours(24));

    $response = $this->get("/transfers/{$transferId}/qr-token");

    $response->assertRedirect('/login');
});

it('lets the buyer retrieve the cached plaintext QR token', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transferId = createTransferRowForQrTest($seller, $buyer);
    Cache::put("transfers.{$transferId}.qr_token", 'plaintext-token', now()->addHours(24));

    $response = $this->actingAs($buyer)->get("/transfers/{$transferId}/qr-token");

    $response->assertOk();
    expect($response->json('data.qr_token'))->toBe('plaintext-token');
});

it('forbids the seller from retrieving the buyer-only QR token', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transferId = createTransferRowForQrTest($seller, $buyer);
    Cache::put("transfers.{$transferId}.qr_token", 'plaintext-token', now()->addHours(24));

    $response = $this->actingAs($seller)->get("/transfers/{$transferId}/qr-token");

    $response->assertStatus(403);
});

it('forbids an unrelated authenticated user', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $stranger = User::factory()->create();
    $transferId = createTransferRowForQrTest($seller, $buyer);
    Cache::put("transfers.{$transferId}.qr_token", 'plaintext-token', now()->addHours(24));

    $response = $this->actingAs($stranger)->get("/transfers/{$transferId}/qr-token");

    $response->assertStatus(403);
});

it('returns 404 for an unknown transfer', function () {
    $buyer = User::factory()->create();

    $response = $this->actingAs($buyer)->get('/transfers/'.(string) Str::uuid().'/qr-token');

    $response->assertNotFound();
});

it('returns a clean error once the cached token has expired or was never issued', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transferId = createTransferRowForQrTest($seller, $buyer);
    // Deliberately never cached, simulating an expired cache entry.

    $response = $this->actingAs($buyer)->get("/transfers/{$transferId}/qr-token");

    $response->assertNotFound();
});
