<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use RowBuddy\Queues\Queue;

/**
 * The `queues` table row. Deliberately dumb (no business rules): the
 * status-lifecycle rules from ADR-005 live only on the {@see Queue}
 * aggregate, not here.
 *
 * @property string $id
 * @property string $category
 * @property string $jurisdiction_country
 * @property string $center_latitude
 * @property string $center_longitude
 * @property string $radius_meters
 * @property string $authorship
 * @property string|null $organizer_reference
 * @property string $status
 */
final class QueueModel extends Model
{
    public $incrementing = false;

    protected $table = 'queues';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'category',
        'jurisdiction_country',
        'center_latitude',
        'center_longitude',
        'radius_meters',
        'authorship',
        'organizer_reference',
        'status',
    ];
}
