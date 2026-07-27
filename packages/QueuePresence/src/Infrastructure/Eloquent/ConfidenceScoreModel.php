<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `presence_confidence_scores` table row. Deliberately dumb (no
 * business rules) — the scoring itself happens in {@see
 * \RowBuddy\QueuePresence\Scoring\ConfidenceScorer}, never here. Rows are
 * only ever inserted, never updated.
 *
 * @property string $id
 * @property string $presence_session_id
 * @property int $points
 * @property string $tier
 * @property Carbon $computed_at
 */
final class ConfidenceScoreModel extends Model
{
    public $incrementing = false;

    protected $table = 'presence_confidence_scores';

    protected $keyType = 'string';

    /**
     * Eloquent's default date format ('Y-m-d H:i:s') silently truncates
     * microseconds before a value ever reaches the database, regardless
     * of the column's timestamp(6) precision — two recomputations within
     * the same real-world second (a routine occurrence: a GPS ping
     * immediately followed by an evidence upload) would otherwise tie on
     * computed_at and make "the latest score" ambiguous. This override is
     * what actually gives that column's precision meaning.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'id',
        'presence_session_id',
        'points',
        'tier',
        'computed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'computed_at' => 'datetime',
    ];
}
