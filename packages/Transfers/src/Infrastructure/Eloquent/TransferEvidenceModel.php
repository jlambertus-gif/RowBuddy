<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `transfer_evidence` table row. Never updated or deleted once
 * created — mirrors `BidModel`'s append-only shape.
 *
 * @property int $id
 * @property string $transfer_id
 * @property string $type
 * @property string $storage_reference
 * @property int $submitted_by
 * @property Carbon $submitted_at
 */
final class TransferEvidenceModel extends Model
{
    protected $table = 'transfer_evidence';

    protected $fillable = [
        'transfer_id',
        'type',
        'storage_reference',
        'submitted_by',
        'submitted_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'submitted_at' => 'datetime',
    ];
}
