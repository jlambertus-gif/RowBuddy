<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * The `bid_placement_claims` table row (Phase 9, ADR-027 Architecture
 * Refinements §2). Keyed by the composite (bidder_id, idempotency_key)
 * pair at the database level — Eloquent has no native composite-primary-key
 * support, so this model is only ever queried/updated via explicit
 * where() clauses, never find()/save() on a loaded instance.
 *
 * @property int $bidder_id
 * @property string $idempotency_key
 * @property string $auction_id
 * @property string $request_fingerprint
 * @property string|null $outcome
 * @property string|null $bid_id
 * @property string|null $rejection_reason
 */
final class BidPlacementClaimModel extends Model
{
    public $incrementing = false;

    protected $table = 'bid_placement_claims';

    protected $fillable = [
        'bidder_id',
        'idempotency_key',
        'auction_id',
        'request_fingerprint',
        'outcome',
        'bid_id',
        'rejection_reason',
    ];
}
