<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `seller_id` is an unsigned bigint (matching apps/web's `users.id`, a
 * plain auto-incrementing key — not a uuid like `queues.id`), stored as a
 * plain column with no cross-package foreign-key constraint, consistent
 * with the modular monolith's no-direct-cross-module-access rule.
 *
 * The partial unique index enforces "one active PresenceSession per seller
 * per queue" at the persistence boundary: both PostgreSQL and SQLite
 * support a `WHERE` clause on a unique index with identical syntax (same
 * approach as
 * `2026_07_23_000002_add_unique_index_to_restricted_categories_global_rows.php`
 * in packages/Queues). A table-level unique(seller_id, queue_id) alone
 * would wrongly forbid a seller ever returning to the same queue after
 * ending an earlier session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('queue_id');
            $table->foreignId('seller_id');
            $table->timestamp('started_at');
            $table->string('status');
            $table->timestamps();

            $table->index('queue_id');
            $table->index('seller_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX presence_sessions_one_active_per_seller_per_queue '
            .'ON presence_sessions (seller_id, queue_id) '
            ."WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_sessions');
    }
};
