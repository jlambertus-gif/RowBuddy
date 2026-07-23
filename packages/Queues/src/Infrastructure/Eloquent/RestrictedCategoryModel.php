<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $code
 * @property string|null $jurisdiction_country
 * @property bool $active
 */
final class RestrictedCategoryModel extends Model
{
    public $incrementing = false;

    protected $table = 'restricted_categories';

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'code',
        'jurisdiction_country',
        'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active' => 'bool',
    ];
}
