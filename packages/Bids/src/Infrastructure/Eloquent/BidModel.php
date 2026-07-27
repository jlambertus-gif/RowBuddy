<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `bids` table row. Deliberately dumb (no business rules) and,
 * unlike every other model in this codebase so far, never updated once
 * created — the append-only shape CLAUDE.md's "all accepted bids are
 * immutable" rule requires.
 *
 * @property string $id
 * @property string $auction_id
 * @property int $bidder_id
 * @property int $amount_minor_units
 * @property string $amount_currency
 * @property Carbon $placed_at
 */
final class BidModel extends Model
{
    public $incrementing = false;

    protected $table = 'bids';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'auction_id',
        'bidder_id',
        'amount_minor_units',
        'amount_currency',
        'placed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'placed_at' => 'datetime',
    ];
}
