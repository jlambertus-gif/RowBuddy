<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RowBuddy\Transfers\Transfer;

/**
 * The `transfers` table row. Deliberately dumb (no business rules): the
 * Issued/Confirmed/Expired/Cancelled lifecycle rules live only on the
 * {@see Transfer} aggregate, not here.
 *
 * @property string $id
 * @property string $auction_id
 * @property string $winning_bid_id
 * @property int $seller_id
 * @property int $buyer_id
 * @property string $qr_token_hash
 * @property Carbon $issued_at
 * @property Carbon $expires_at
 * @property string $status
 * @property Carbon|null $seller_confirmed_at
 * @property float|string|null $seller_confirmed_latitude
 * @property float|string|null $seller_confirmed_longitude
 * @property Carbon|null $buyer_confirmed_at
 * @property float|string|null $buyer_confirmed_latitude
 * @property float|string|null $buyer_confirmed_longitude
 * @property Carbon|null $confirmed_at
 */
final class TransferModel extends Model
{
    public $incrementing = false;

    protected $table = 'transfers';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'auction_id',
        'winning_bid_id',
        'seller_id',
        'buyer_id',
        'qr_token_hash',
        'issued_at',
        'expires_at',
        'status',
        'seller_confirmed_at',
        'seller_confirmed_latitude',
        'seller_confirmed_longitude',
        'buyer_confirmed_at',
        'buyer_confirmed_longitude',
        'buyer_confirmed_latitude',
        'confirmed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'seller_confirmed_at' => 'datetime',
        'buyer_confirmed_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];
}
