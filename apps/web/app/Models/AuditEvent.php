<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The `audit_events` table row. Append-only by convention: nothing in
 * this codebase ever updates or deletes a row — only
 * App\Listeners\RecordAuditEvent inserts one, in response to any domain
 * event implementing AuditableAction, from any module.
 *
 * @property string $id
 * @property string $event_name
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed> $payload
 * @property Carbon $occurred_at
 */
final class AuditEvent extends Model
{
    public $incrementing = false;

    protected $table = 'audit_events';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'event_name',
        'subject_type',
        'subject_id',
        'payload',
        'occurred_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];
}
