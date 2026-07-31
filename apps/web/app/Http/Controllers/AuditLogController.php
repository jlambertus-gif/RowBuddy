<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersAuditLogResponses;
use Illuminate\Http\JsonResponse;
use RowBuddy\Administration\Application\AuditLogService;

final class AuditLogController extends Controller
{
    use RendersAuditLogResponses;

    public function __invoke(AuditLogService $service): JsonResponse
    {
        return response()->json(['data' => array_map($this->toResponse(...), $service->listRecent())]);
    }
}
