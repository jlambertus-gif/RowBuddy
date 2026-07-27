<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `auctions` table row. Deliberately dumb (no business rules): the
 * Open/Closing/Won/Expired lifecycle rules live only on the {@see
 * \RowBuddy\Auctions\Auction} aggregate, not here.
 *
 * @property string $id
 * @property string $queue_id
 * @property int $seller_id
 * @property string $presence_session_id
 * @property int $starting_price_minor_units
 * @property string $starting_price_currency
 * @property Carbon $opened_at
 * @property string $status
 * @property string|null $winning_bid_id
 * @property int|null $winning_amount_minor_units
 * @property string|null $winning_amount_currency
 */
final class AuctionModel extends Model
{
    public $incrementing = false;

    protected $table = 'auctions';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'queue_id',
        'seller_id',
        'presence_session_id',
        'starting_price_minor_units',
        'starting_price_currency',
        'opened_at',
        'status',
        'winning_bid_id',
        'winning_amount_minor_units',
        'winning_amount_currency',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'opened_at' => 'datetime',
    ];
}
