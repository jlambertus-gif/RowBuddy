<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Transfers\Infrastructure\Eloquent\TransferModel;

uses(RefreshDatabase::class);

function createTransferRowForShowTest(User $seller, User $buyer, array $overrides = []): string
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
        ...$overrides,
    ]);

    return $id;
}

it('redirects unauthenticated users to login', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transferId = createTransferRowForShowTest($seller, $buyer);

    $response = $this->get("/transfers/{$transferId}");

    $response->assertRedirect('/login');
});

it('shows the seller their own role and confirmation status', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transferId = createTransferRowForShowTest($seller, $buyer);

    $response = $this->actingAs($seller)->get("/transfers/{$transferId}");

    $response->assertOk();
    expect($response->json('data'))->toMatchArray([
        'id' => $transferId,
        'status' => 'issued',
        'role' => 'seller',
        'seller_confirmed' => false,
        'buyer_confirmed' => false,
    ]);
});

it('shows the buyer their own role', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transferId = createTransferRowForShowTest($seller, $buyer);

    $response = $this->actingAs($buyer)->get("/transfers/{$transferId}");

    $response->assertOk();
    expect($response->json('data.role'))->toBe('buyer');
});

it('never exposes the QR token hash or a raw storage reference', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transferId = createTransferRowForShowTest($seller, $buyer);

    $response = $this->actingAs($seller)->get("/transfers/{$transferId}");

    $body = $response->json('data');
    expect($body)->not->toHaveKey('qr_token_hash')
        ->and(json_encode($body))->not->toContain(hash('sha256', 'plaintext-token'));
});

it('returns 404 for an unknown transfer', function () {
    $seller = User::factory()->create();

    $response = $this->actingAs($seller)->get('/transfers/'.(string) Str::uuid());

    $response->assertNotFound();
});

it('forbids an unrelated authenticated user, distinct from not found', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $stranger = User::factory()->create();
    $transferId = createTransferRowForShowTest($seller, $buyer);

    $response = $this->actingAs($stranger)->get("/transfers/{$transferId}");

    $response->assertStatus(403);
});
