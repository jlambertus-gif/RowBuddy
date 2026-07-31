<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Administration\Contracts\AdminRoleAssignmentRepository;
use RowBuddy\Administration\Infrastructure\Eloquent\AdminActionModel;
use RowBuddy\Administration\ValueObjects\AdminRole;
use RowBuddy\Disputes\Infrastructure\Eloquent\DisputeEvidenceModel;
use RowBuddy\Disputes\Infrastructure\Eloquent\DisputeModel;

uses(RefreshDatabase::class);

function aStoredOpenedDisputeForReview(?string $id = null): DisputeModel
{
    return DisputeModel::query()->create([
        'id' => $id ?? (string) Str::uuid(),
        'transfer_id' => (string) Str::uuid(),
        'auction_id' => (string) Str::uuid(),
        'buyer_id' => 101,
        'seller_id' => 102,
        'reason' => 'Item not as described.',
        'opened_at' => now()->subDays(2),
        'status' => 'opened',
    ]);
}

function aStoredResolvedDisputeForReview(?string $id = null): DisputeModel
{
    return DisputeModel::query()->create([
        'id' => $id ?? (string) Str::uuid(),
        'transfer_id' => (string) Str::uuid(),
        'auction_id' => (string) Str::uuid(),
        'buyer_id' => 201,
        'seller_id' => 202,
        'reason' => 'Buyer never received the item.',
        'opened_at' => now()->subDays(5),
        'status' => 'resolved',
        'resolution_outcome' => 'release_to_seller',
        'resolved_by' => 999,
        'resolution_notes' => 'Evidence supported the seller.',
        'evidence_found_fraudulent' => false,
        'resolved_at' => now()->subDay(),
    ]);
}

function anAdministratorForDisputeReview(): User
{
    $user = User::factory()->create();
    app(AdminRoleAssignmentRepository::class)->assignRole((string) $user->id, AdminRole::Administrator, null);

    return $user;
}

// --- Authorization ---

it('redirects guests away from every dispute-review endpoint', function () {
    $dispute = aStoredResolvedDisputeForReview();

    $this->get('/admin/disputes')->assertRedirect('/login');
    $this->post("/admin/disputes/{$dispute->id}/corrections", ['note' => 'x'])->assertRedirect('/login');
});

it('forbids an authenticated non-admin user from every dispute-review endpoint', function () {
    $user = User::factory()->create();
    $dispute = aStoredResolvedDisputeForReview();

    $this->actingAs($user)->get('/admin/disputes')->assertForbidden();
    $this->actingAs($user)->post("/admin/disputes/{$dispute->id}/corrections", ['note' => 'x'])->assertForbidden();
});

it('forbids a Moderator, who holds queues.moderate but not disputes.review, from reviewing disputes', function () {
    $moderator = User::factory()->create();
    app(AdminRoleAssignmentRepository::class)->assignRole((string) $moderator->id, AdminRole::Moderator, null);
    $dispute = aStoredResolvedDisputeForReview();

    $this->actingAs($moderator)->get('/admin/disputes')->assertForbidden();
    $this->actingAs($moderator)->post("/admin/disputes/{$dispute->id}/corrections", ['note' => 'x'])->assertForbidden();
});

// --- Listing ---

it('lists every dispute, opened and resolved, with evidence, for an Administrator', function () {
    $opened = aStoredOpenedDisputeForReview();
    $resolved = aStoredResolvedDisputeForReview();
    DisputeEvidenceModel::query()->create([
        'dispute_id' => $opened->id,
        'type' => 'written_statement',
        'storage_reference' => 'The seller confirmed the delay.',
        'submitted_by' => 101,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs(anAdministratorForDisputeReview())->get('/admin/disputes');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);

    $openedResponse = collect($response->json('data'))->firstWhere('id', $opened->id);
    $resolvedResponse = collect($response->json('data'))->firstWhere('id', $resolved->id);

    expect($openedResponse['status'])->toBe('opened')
        ->and($openedResponse['evidence'])->toHaveCount(1)
        ->and($openedResponse['evidence'][0]['type'])->toBe('written_statement')
        ->and($openedResponse['evidence'][0]['content'])->toBe('The seller confirmed the delay.')
        ->and($resolvedResponse['status'])->toBe('resolved')
        ->and($resolvedResponse['resolution_outcome'])->toBe('release_to_seller')
        ->and($resolvedResponse['resolution_notes'])->toBe('Evidence supported the seller.');
});

it('never exposes a raw storage path for photo evidence in the JSON response', function () {
    $dispute = aStoredOpenedDisputeForReview();
    DisputeEvidenceModel::query()->create([
        'dispute_id' => $dispute->id,
        'type' => 'photo',
        'storage_reference' => 'disputes/'.$dispute->id.'/private-photo.jpg',
        'submitted_by' => 101,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs(anAdministratorForDisputeReview())->get('/admin/disputes');

    $response->assertOk();
    $body = $response->getContent();

    expect($body)->not->toContain('private-photo.jpg')
        ->not->toContain('storage_reference');

    $evidence = collect($response->json('data'))->firstWhere('id', $dispute->id)['evidence'][0];
    expect($evidence['type'])->toBe('photo')
        ->and($evidence['content'])->toBeNull();
});

// --- Recording a correction ---

it('lets an Administrator record a correction, without mutating the dispute itself', function () {
    $dispute = aStoredResolvedDisputeForReview();

    $response = $this->actingAs(anAdministratorForDisputeReview())->post("/admin/disputes/{$dispute->id}/corrections", [
        'note' => 'On further review, the refund should have been split 50/50.',
    ]);

    $response->assertOk();

    $fresh = $dispute->fresh();
    expect($fresh->status)->toBe('resolved')
        ->and($fresh->resolution_outcome)->toBe('release_to_seller')
        ->and($fresh->resolution_notes)->toBe('Evidence supported the seller.')
        ->and($fresh->resolved_by)->toBe(999);

    $action = AdminActionModel::query()->sole();
    expect($action->action_type)->toBe('dispute_correction_recorded')
        ->and($action->target_type)->toBe('dispute')
        ->and($action->target_id)->toBe($dispute->id)
        ->and($action->reason)->toBe('On further review, the refund should have been split 50/50.')
        ->and($action->previous_state)->toBeNull()
        ->and($action->new_state)->toBeNull();
});

it('rejects a blank note at the validation layer before reaching the service, whether omitted or whitespace-only', function () {
    $dispute = aStoredResolvedDisputeForReview();
    $admin = anAdministratorForDisputeReview();

    // The global TrimStrings middleware trims request input before
    // Laravel's own `required` rule runs, so a whitespace-only note is
    // indistinguishable from an omitted one at this boundary — both are
    // caught here, never reaching DisputeCorrectionService's own
    // AdministrativeActionReasonRequired guard (proven directly, without
    // HTTP, in packages/Administration's own DisputeCorrectionServiceTest).
    $this->actingAs($admin)->post("/admin/disputes/{$dispute->id}/corrections", [])
        ->assertSessionHasErrors('note');

    $this->actingAs($admin)->post("/admin/disputes/{$dispute->id}/corrections", ['note' => '   '])
        ->assertSessionHasErrors('note');

    expect(AdminActionModel::query()->count())->toBe(0);
});

it('returns a clean 404 when recording a correction against an unknown dispute', function () {
    $response = $this->actingAs(anAdministratorForDisputeReview())->post('/admin/disputes/'.Str::uuid().'/corrections', [
        'note' => 'note',
    ]);

    $response->assertStatus(404);
    expect($response->json('message'))->toBe(__('disputes.review.not_found'));
});

it('allows recording more than one correction against the same dispute', function () {
    $dispute = aStoredResolvedDisputeForReview();
    $admin = anAdministratorForDisputeReview();

    $this->actingAs($admin)->post("/admin/disputes/{$dispute->id}/corrections", ['note' => 'First note.'])->assertOk();
    $this->actingAs($admin)->post("/admin/disputes/{$dispute->id}/corrections", ['note' => 'Second, independent note.'])->assertOk();

    expect(AdminActionModel::query()->count())->toBe(2);
});

// --- Translations ---

it('has en and es translations for every dispute-review message key', function () {
    $keys = [
        'disputes.review.not_found',
        'disputes.review.note_required',
        'disputes.review.correction_recorded',
        'disputes.fields.note',
    ];

    foreach ($keys as $key) {
        $en = __($key, [], 'en');
        $es = __($key, [], 'es');

        expect($en)->not->toBe($key)
            ->and($es)->not->toBe($key)
            ->and($es)->not->toBe($en);
    }
});
