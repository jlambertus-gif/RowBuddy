<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `webhook_events` table row. Deliberately dumb (no business rules)
 * and never updated once created — the idempotency ledger only ever
 * grows.
 *
 * @property string $stripe_event_id
 * @property string $event_type
 * @property Carbon $processed_at
 */
final class WebhookEventModel extends Model
{
    public $incrementing = false;

    protected $table = 'webhook_events';

    protected $primaryKey = 'stripe_event_id';

    protected $keyType = 'string';

    protected $fillable = [
        'stripe_event_id',
        'event_type',
        'processed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
