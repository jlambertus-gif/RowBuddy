<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A minimal admin flag to authorize the Sprint 1 Administration moderation
 * queue (ADR-005). A full roles/permissions model belongs to the Identity
 * bounded context once it becomes its own module (see
 * docs/product/claude-mvp-analysis.md's Identity & Access aggregate) — not
 * invented speculatively here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
