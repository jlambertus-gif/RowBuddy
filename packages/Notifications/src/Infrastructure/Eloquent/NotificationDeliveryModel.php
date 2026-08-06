<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `notification_deliveries` table row — a pure operational ledger,
 * no business rules of any kind.
 *
 * @property string $domain_event_id
 * @property int $recipient_id
 * @property string $notification_type
 * @property string $channel
 * @property Carbon $delivered_at
 */
final class NotificationDeliveryModel extends Model
{
    protected $table = 'notification_deliveries';

    protected $fillable = [
        'domain_event_id',
        'recipient_id',
        'notification_type',
        'channel',
        'delivered_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'delivered_at' => 'datetime',
    ];
}
