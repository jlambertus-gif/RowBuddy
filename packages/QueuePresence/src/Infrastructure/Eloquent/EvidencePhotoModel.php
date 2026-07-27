<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `presence_evidence_photos` table row. Deliberately dumb (no
 * business rules) — metadata stripping and storage already happened
 * before this row is ever written.
 *
 * @property string $id
 * @property string $presence_session_id
 * @property string $storage_reference
 * @property string $mime_type
 * @property int $size_bytes
 * @property Carbon $recorded_at
 */
final class EvidencePhotoModel extends Model
{
    public $incrementing = false;

    protected $table = 'presence_evidence_photos';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'presence_session_id',
        'storage_reference',
        'mime_type',
        'size_bytes',
        'recorded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'recorded_at' => 'datetime',
    ];
}
