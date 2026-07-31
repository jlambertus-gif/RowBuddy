<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfills every existing `is_admin = true` user into the highest
 * administrative role (ADR-026 Architecture Refinements §2) before the
 * boolean column is dropped in the next migration — ensuring no existing
 * administrator ever loses access. `assigned_by` is null: this is an
 * automated, one-time data migration, not an admin action.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $admins = DB::table('users')->where('is_admin', true)->pluck('id');

        foreach ($admins as $userId) {
            DB::table('admin_role_assignments')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'role' => 'administrator',
                    'assigned_by' => null,
                    'assigned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        // Intentionally irreversible as data: the reverse of "grant a
        // role" is a targeted revocation, not blanket deletion of every
        // admin_role_assignments row (which could remove roles assigned
        // by other means after this migration ran). Rolling back this
        // migration is a no-op; roll back is_admin removal manually if
        // ever needed.
    }
};
