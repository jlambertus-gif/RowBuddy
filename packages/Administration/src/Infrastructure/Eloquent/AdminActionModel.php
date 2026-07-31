<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `admin_actions` table row — a pure operational ledger, no business
 * rules of any kind. Append-only: no `updated_at`.
 *
 * @property string $id
 * @property int $admin_id
 * @property string $action_type
 * @property string $target_type
 * @property string $target_id
 * @property string $reason
 * @property mixed $previous_state
 * @property mixed $new_state
 * @property Carbon $created_at
 */
final class AdminActionModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'admin_actions';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'admin_id',
        'action_type',
        'target_type',
        'target_id',
        'reason',
        'previous_state',
        'new_state',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'previous_state' => 'json',
        'new_state' => 'json',
        'created_at' => 'datetime',
    ];
}
