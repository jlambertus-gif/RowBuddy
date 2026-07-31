<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes `users.is_admin` only after every existing administrator has
 * been backfilled into `admin_role_assignments` (the prior migration)
 * and `queues.moderate` has been re-pointed at the capability-based
 * Gate (`AdministrationServiceProvider`) — ADR-026 Architecture
 * Refinements §2's "remove the boolean only after all consumers have
 * moved."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });
    }
};
