<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use RowBuddy\Administration\Application\AccountSuspensionService;

/**
 * The `account_standings` table row — the materialized current state
 * only. The suspend/reinstate invariants live on
 * {@see AccountSuspensionService},
 * not here.
 *
 * @property int $user_id
 * @property string $state
 */
final class AccountStandingModel extends Model
{
    public $incrementing = false;

    protected $table = 'account_standings';

    protected $primaryKey = 'user_id';

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'state',
    ];
}
