<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * At most one administrative role per user for MVP (ADR-026 §2,
 * Architecture Refinements §2) — `user_id` is the primary key rather
 * than a separate auto-increment id with a unique constraint, since a
 * "current assignment" table has exactly one row per assigned user by
 * definition. `assigned_by` is nullable: engineering-controlled role
 * assignment (a console command, not an admin UI) has no admin actor to
 * record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_role_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('role');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_assignments');
    }
};
