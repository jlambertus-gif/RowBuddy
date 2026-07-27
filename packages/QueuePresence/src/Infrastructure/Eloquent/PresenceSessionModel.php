<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `presence_sessions` table row. Deliberately dumb (no business rules):
 * the Active/Ended lifecycle rules live only on the {@see
 * \RowBuddy\QueuePresence\PresenceSession} aggregate, not here.
 *
 * @property string $id
 * @property string $queue_id
 * @property int $seller_id
 * @property Carbon $started_at
 * @property string $status
 */
final class PresenceSessionModel extends Model
{
    public $incrementing = false;

    protected $table = 'presence_sessions';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'queue_id',
        'seller_id',
        'started_at',
        'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'started_at' => 'datetime',
    ];
}
