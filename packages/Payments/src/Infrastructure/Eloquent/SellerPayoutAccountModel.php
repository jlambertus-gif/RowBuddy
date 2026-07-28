<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `seller_payout_accounts` table row. Deliberately dumb (no business
 * rules) and never updated once created in the MVP — a seller's linked
 * Stripe account is not re-pointed to a different one.
 *
 * @property int $seller_id
 * @property string $stripe_account_id
 * @property Carbon $linked_at
 */
final class SellerPayoutAccountModel extends Model
{
    public $incrementing = false;

    protected $table = 'seller_payout_accounts';

    protected $primaryKey = 'seller_id';

    protected $keyType = 'int';

    protected $fillable = [
        'seller_id',
        'stripe_account_id',
        'linked_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'linked_at' => 'datetime',
    ];
}
