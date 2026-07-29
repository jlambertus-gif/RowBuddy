<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RowBuddy\Disputes\Dispute;

/**
 * The `disputes` table row. Deliberately dumb (no business rules): the
 * Opened/Resolved lifecycle rules and the outcome/refund-amount
 * consistency invariants live only on the {@see Dispute}
 * aggregate, not here.
 *
 * @property string $id
 * @property string $transfer_id
 * @property string $auction_id
 * @property int $buyer_id
 * @property int $seller_id
 * @property string $reason
 * @property Carbon $opened_at
 * @property string $status
 * @property string|null $resolution_outcome
 * @property int|null $refund_amount_minor_units
 * @property string|null $refund_amount_currency
 * @property int|null $resolved_by
 * @property string|null $resolution_notes
 * @property bool $evidence_found_fraudulent
 * @property Carbon|null $resolved_at
 */
final class DisputeModel extends Model
{
    public $incrementing = false;

    protected $table = 'disputes';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'transfer_id',
        'auction_id',
        'buyer_id',
        'seller_id',
        'reason',
        'opened_at',
        'status',
        'resolution_outcome',
        'refund_amount_minor_units',
        'refund_amount_currency',
        'resolved_by',
        'resolution_notes',
        'evidence_found_fraudulent',
        'resolved_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'opened_at' => 'datetime',
        'evidence_found_fraudulent' => 'boolean',
        'resolved_at' => 'datetime',
    ];
}
