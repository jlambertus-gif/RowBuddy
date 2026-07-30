<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RowBuddy\Ratings\Rating;
use RowBuddy\Ratings\ValueObjects\RatingScore;

/**
 * The `ratings` table row. Deliberately dumb (no business rules): the
 * score-range and comment-normalization invariants live only on the
 * {@see Rating} aggregate and its
 * {@see RatingScore} value object, not
 * here.
 *
 * @property string $id
 * @property string $transfer_id
 * @property int $rater_id
 * @property int $ratee_id
 * @property int $score
 * @property string|null $comment
 * @property Carbon $submitted_at
 */
final class RatingModel extends Model
{
    public $incrementing = false;

    protected $table = 'ratings';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'transfer_id',
        'rater_id',
        'ratee_id',
        'score',
        'comment',
        'submitted_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'score' => 'integer',
        'submitted_at' => 'datetime',
    ];
}
