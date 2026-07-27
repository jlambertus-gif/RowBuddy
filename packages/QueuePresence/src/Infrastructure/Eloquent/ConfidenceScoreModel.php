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
