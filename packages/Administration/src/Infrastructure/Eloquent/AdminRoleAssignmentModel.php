<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RowBuddy\Administration\ValueObjects\AdminRole;

/**
 * The `admin_role_assignments` table row — a user's current role
 * assignment. Deliberately dumb: the closed-role-set invariant lives on
 * {@see AdminRole}, not here.
 *
 * @property int $user_id
 * @property string $role
 * @property int|null $assigned_by
 * @property Carbon $assigned_at
 */
final class AdminRoleAssignmentModel extends Model
{
    public $incrementing = false;

    protected $table = 'admin_role_assignments';

    protected $primaryKey = 'user_id';

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'role',
        'assigned_by',
        'assigned_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'assigned_at' => 'datetime',
    ];
}
