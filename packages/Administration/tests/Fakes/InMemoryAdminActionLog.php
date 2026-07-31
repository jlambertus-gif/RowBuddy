<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Tests\Fakes;

use RowBuddy\Administration\Contracts\AdminActionLog;
use RowBuddy\Administration\ValueObjects\AdministrativeActionType;

final class InMemoryAdminActionLog implements AdminActionLog
{
    /**
     * @var list<array{
     *     type: AdministrativeActionType,
     *     adminId: string,
     *     targetType: string,
     *     targetId: string,
     *     reason: string,
     *     previousState: mixed,
     *     newState: mixed,
     * }>
     */
    public array $recorded = [];

    public function record(
        AdministrativeActionType $type,
        string $adminId,
        string $targetType,
        string $targetId,
        string $reason,
        mixed $previousState,
        mixed $newState,
    ): void {
        $this->recorded[] = [
            'type' => $type,
            'adminId' => $adminId,
            'targetType' => $targetType,
            'targetId' => $targetId,
            'reason' => $reason,
            'previousState' => $previousState,
            'newState' => $newState,
        ];
    }
}
