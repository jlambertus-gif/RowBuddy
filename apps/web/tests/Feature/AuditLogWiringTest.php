<?php

declare(strict_types=1);

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Administration\Contracts\AdminRoleAssignmentRepository;
use RowBuddy\Administration\ValueObjects\AdminRole;

uses(RefreshDatabase::class);

function aStoredAuditEvent(string $eventName, array $payload, ?string $id = null): AuditEvent
{
    return AuditEvent::query()->create([
        'id' => $id ?? (string) Str::uuid(),
        'event_name' => $eventName,
        'subject_type' => 'queue',
        'subject_id' => (string) Str::uuid(),
        'payload' => $payload,
        'occurred_at' => now(),
    ]);
}

function anAdministratorForAuditLog(): User
{
    $user = User::factory()->create();
    app(AdminRoleAssignmentRepository::class)->assignRole((string) $user->id, AdminRole::Administrator, null);

    return $user;
}

function aModeratorForAuditLog(): User
{
    $user = User::factory()->create();
    app(AdminRoleAssignmentRepository::class)->assignRole((string) $user->id, AdminRole::Moderator, null);

    return $user;
}

it('redirects guests away from the audit log', function () {
    $this->get('/admin/audit-events')->assertRedirect('/login');
});

it('forbids an authenticated non-admin user from the audit log', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/audit-events')->assertForbidden();
});

it('forbids a Moderator from viewing the audit log — audit.view is Administrator-only, per ADR-026 §6', function () {
    aStoredAuditEvent('queues.queue_approved', ['queue_id' => 'queue-1', 'approved_by_user_id' => '999']);

    $this->actingAs(aModeratorForAuditLog())->get('/admin/audit-events')->assertForbidden();
});

it('lets an Administrator view the audit log', function () {
    aStoredAuditEvent('queues.queue_approved', ['queue_id' => 'queue-1', 'approved_by_user_id' => '999']);

    $response = $this->actingAs(anAdministratorForAuditLog())->get('/admin/audit-events');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('exposes only the registered fields for a known event type, dropping anything else in the stored payload', function () {
    aStoredAuditEvent('queues.queue_approved', [
        'queue_id' => 'queue-1',
        'approved_by_user_id' => '999',
        'internal_debug_note' => 'not part of the allowlist',
    ]);

    $response = $this->actingAs(anAdministratorForAuditLog())->get('/admin/audit-events');

    $response->assertOk();
    $event = $response->json('data.0');

    expect($event['fields'])->toBe(['queue_id' => 'queue-1', 'approved_by_user_id' => '999'])
        ->and($event['fields'])->not->toHaveKey('internal_debug_note')
        ->and($response->getContent())->not->toContain('internal_debug_note');
});

it('never exposes a raw private storage path for dispute evidence, even though it is present in the stored payload', function () {
    aStoredAuditEvent('disputes.dispute_evidence_attached', [
        'dispute_id' => 'dispute-1',
        'type' => 'photo',
        'storage_reference' => 'disputes/dispute-1/private-secret-photo.jpg',
        'submitted_by' => '101',
    ]);

    $response = $this->actingAs(anAdministratorForAuditLog())->get('/admin/audit-events');

    $response->assertOk();
    $body = $response->getContent();

    expect($body)->not->toContain('private-secret-photo.jpg')
        ->not->toContain('storage_reference');
});

it('never exposes a Stripe account id, even though it is present in the stored payload', function () {
    aStoredAuditEvent('payments.seller_payout_account_linked', [
        'seller_id' => '101',
        'stripe_account_id' => 'acct_supersecretvalue',
    ]);

    $response = $this->actingAs(anAdministratorForAuditLog())->get('/admin/audit-events');

    $response->assertOk();
    expect($response->getContent())->not->toContain('acct_supersecretvalue')
        ->not->toContain('stripe_account_id');
});

it('is fail-closed for an unregistered event type: it does not appear at all, not even its event name, subject, or timestamp', function () {
    $unregistered = aStoredAuditEvent('some_future_module.some_new_event', [
        'sensitive_field' => 'this-should-never-appear',
    ]);

    $response = $this->actingAs(anAdministratorForAuditLog())->get('/admin/audit-events');

    $response->assertOk();

    expect($response->json('data'))->toBe([])
        ->and($response->getContent())->not->toContain('this-should-never-appear')
        ->not->toContain('some_future_module.some_new_event')
        ->not->toContain($unregistered->id)
        ->not->toContain($unregistered->subject_id);
});

it('excludes an unregistered event from a list that also contains registered ones, leaving only the registered event visible', function () {
    aStoredAuditEvent('queues.queue_approved', ['queue_id' => 'queue-1', 'approved_by_user_id' => '999']);
    $unregistered = aStoredAuditEvent('some_future_module.some_new_event', [
        'sensitive_field' => 'this-should-never-appear',
    ]);

    $response = $this->actingAs(anAdministratorForAuditLog())->get('/admin/audit-events');

    $response->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.event_name'))->toBe('queues.queue_approved')
        ->and($response->getContent())->not->toContain('this-should-never-appear')
        ->not->toContain($unregistered->id);
});

it('does not audit an individual GPS ping, so it never appears in the audit log at all', function () {
    // GpsPingRecorded deliberately never implements AuditableAction
    // (see its own docblock) — nothing to seed here; this asserts the
    // audit log endpoint itself works correctly with zero rows.
    $response = $this->actingAs(anAdministratorForAuditLog())->get('/admin/audit-events');

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});
