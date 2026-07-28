<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `payment_intents` table row. Deliberately dumb (no business rules):
 * the authorization/decline invariants live only on the {@see
 * \RowBuddy\Payments\PaymentIntent} aggregate, not here. Never updated
 * once created in Phase 4 — both `authorize()` and `declineAuthorization()`
 * produce a fully-decided row; nothing in this phase mutates it afterward.
 *
 * @property string $id
 * @property string $auction_id
 * @property string $winning_bid_id
 * @property int $seller_id
 * @property int $buyer_id
 * @property int $amount_minor_units
 * @property string $amount_currency
 * @property int $fee_amount_minor_units
 * @property string $status
 * @property Carbon $decided_at
 */
final class PaymentIntentModel extends Model
{
    public $incrementing = false;

    protected $table = 'payment_intents';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'auction_id',
        'winning_bid_id',
        'seller_id',
        'buyer_id',
        'amount_minor_units',
        'amount_currency',
        'fee_amount_minor_units',
        'status',
        'decided_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'decided_at' => 'datetime',
    ];
}
