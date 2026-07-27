<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `presence_gps_pings` table row. Deliberately dumb (no business
 * rules) — `within_geofence` is decided once by the application service
 * before this row is ever written, never recomputed here.
 *
 * @property string $id
 * @property string $presence_session_id
 * @property string $latitude
 * @property string $longitude
 * @property float $accuracy_meters
 * @property bool $within_geofence
 * @property Carbon $recorded_at
 */
final class GpsPingModel extends Model
{
    public $incrementing = false;

    protected $table = 'presence_gps_pings';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'presence_session_id',
        'latitude',
        'longitude',
        'accuracy_meters',
        'within_geofence',
        'recorded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'within_geofence' => 'bool',
        'recorded_at' => 'datetime',
    ];
}
