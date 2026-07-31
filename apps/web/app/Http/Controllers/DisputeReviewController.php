<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersDisputeCaseResponses;
use Illuminate\Http\JsonResponse;
use RowBuddy\Administration\Contracts\DisputeCaseLookup;

final class DisputeReviewController extends Controller
{
    use RendersDisputeCaseResponses;

    public function __invoke(DisputeCaseLookup $disputes): JsonResponse
    {
        return response()->json(['data' => array_map($this->toResponse(...), $disputes->listAll())]);
    }
}
