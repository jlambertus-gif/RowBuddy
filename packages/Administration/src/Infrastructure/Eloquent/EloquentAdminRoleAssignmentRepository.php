<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Infrastructure\Eloquent;

use RowBuddy\Administration\Contracts\AdminRoleAssignmentRepository;
use RowBuddy\Administration\ValueObjects\AdminRole;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

final class EloquentAdminRoleAssignmentRepository implements AdminRoleAssignmentRepository
{
    public function __construct(private readonly ClockInterface $clock) {}

    public function findRoleForUser(string $userId): ?AdminRole
    {
        /** @var AdminRoleAssignmentModel|null $model */
        $model = AdminRoleAssignmentModel::query()->find($userId);

        if ($model === null) {
            return null;
        }

        return AdminRole::from($model->role);
    }

    public function assignRole(string $userId, AdminRole $role, ?string $assignedBy): void
    {
        AdminRoleAssignmentModel::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'role' => $role->value,
                'assigned_by' => $assignedBy,
                'assigned_at' => $this->clock->now(),
            ],
        );
    }
}
