<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `dispute_evidence` table row. Never updated or deleted once
 * created (ADR-021 §7) — mirrors `TransferEvidenceModel`'s append-only
 * shape.
 *
 * @property int $id
 * @property string $dispute_id
 * @property string $type
 * @property string $storage_reference
 * @property int $submitted_by
 * @property Carbon $submitted_at
 */
final class DisputeEvidenceModel extends Model
{
    protected $table = 'dispute_evidence';

    protected $fillable = [
        'dispute_id',
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
