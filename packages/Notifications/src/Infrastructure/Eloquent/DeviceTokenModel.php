<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `device_tokens` table row — a pure registration record, no
 * business rules of any kind.
 *
 * @property int $user_id
 * @property string $platform
 * @property string $expo_push_token
 * @property Carbon $last_seen_at
 */
final class DeviceTokenModel extends Model
{
    protected $table = 'device_tokens';

    protected $fillable = [
        'user_id',
        'platform',
        'expo_push_token',
        'last_seen_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'last_seen_at' => 'datetime',
    ];
}
