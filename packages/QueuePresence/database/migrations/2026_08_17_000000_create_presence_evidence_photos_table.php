<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `storage_reference` is an opaque path handed back by whatever {@see
 * \RowBuddy\QueuePresence\Contracts\EvidenceStorage} adapter is bound
 * (Laravel's private local disk for Phase 2, per the approved storage
 * decision — never a public URL, and never assumed to be a local
 * filesystem path by anything outside that adapter).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_evidence_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('presence_session_id');
            $table->string('storage_reference');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index('presence_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_evidence_photos');
    }
};
