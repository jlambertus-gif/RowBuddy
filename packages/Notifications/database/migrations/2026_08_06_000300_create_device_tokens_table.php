<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Device push-token registration (ADR-028 Decision 6) — owned by
 * `packages/Notifications`, not Identity/User, since Notifications is
 * the sole consumer of this data (the same "consumer owns the port"
 * posture Ratings/Disputes already take for their own read ports into
 * Transfers). `expo_push_token` is unique: Expo issues one token per
 * physical device+app install, so the token string itself — not
 * `(user_id, platform)`, which would collide across two devices of the
 * same platform for the same user — is the natural registration key.
 * `user_id` is a plain unsigned bigint matching `users.id`, no
 * cross-package foreign key, the same convention `disputes.buyer_id`/
 * `ratings.rater_id` already use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('platform');
            $table->string('expo_push_token')->unique();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
