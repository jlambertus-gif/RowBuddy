<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Needed to compute an accurate, frozen presence duration for Ended
 * sessions (Sprint 6's confidence-scoring integration): duration must stop
 * growing at the moment a session ends, not keep counting from `now()`
 * indefinitely afterward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presence_sessions', function (Blueprint $table) {
            $table->timestamp('ended_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('presence_sessions', function (Blueprint $table) {
            $table->dropColumn('ended_at');
        });
    }
};
