<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `buyer_payment_methods` table row. Deliberately dumb (no business
 * rules) — mirrors {@see SellerPayoutAccountModel}'s own posture. One row
 * per buyer, `buyer_id` as its primary key, always overwritten in place.
 *
 * @property int $buyer_id
 * @property string $stripe_customer_id
 * @property string $stripe_payment_method_id
 * @property Carbon $saved_at
 */
final class BuyerPaymentMethodModel extends Model
{
    public $incrementing = false;

    protected $table = 'buyer_payment_methods';

    protected $keyType = 'int';

    protected $primaryKey = 'buyer_id';

    protected $fillable = [
        'buyer_id',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'saved_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'saved_at' => 'datetime',
    ];
}
