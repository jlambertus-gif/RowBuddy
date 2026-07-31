<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Infrastructure\Eloquent;

use Illuminate\Support\Str;
use RowBuddy\Administration\Contracts\AdminActionLog;
use RowBuddy\Administration\ValueObjects\AdministrativeActionType;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

final class EloquentAdminActionLog implements AdminActionLog
{
    public function __construct(private readonly ClockInterface $clock) {}

    public function record(
        AdministrativeActionType $type,
        string $adminId,
        string $targetType,
        string $targetId,
        string $reason,
        mixed $previousState,
        mixed $newState,
    ): void {
        AdminActionModel::query()->create([
            'id' => (string) Str::uuid(),
            'admin_id' => $adminId,
            'action_type' => $type->value,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $reason,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'created_at' => $this->clock->now(),
        ]);
    }
}
