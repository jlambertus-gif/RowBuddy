<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $jurisdiction_country
 * @property string|null $category
 * @property bool $permitted
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
final class JurisdictionRuleModel extends Model
{
    public $incrementing = false;

    protected $table = 'jurisdiction_rules';

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'jurisdiction_country',
        'category',
        'permitted',
        'effective_from',
        'effective_to',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'permitted' => 'bool',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];
}
