<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use RowBuddy\Ratings\Rating;

/**
 * Shared response-shaping for the rating controllers — no business
 * logic, just translating a {@see Rating} or a known domain exception
 * into a plain JSON shape.
 */
trait RendersRatingResponses
{
    /**
     * @return array<string, mixed>
     */
    private function toResponse(Rating $rating): array
    {
        return [
            'id' => $rating->id,
            'transfer_id' => $rating->transferId,
            'score' => $rating->score->value,
            'comment' => $rating->comment,
            'submitted_at' => $rating->submittedAt->format(DATE_ATOM),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => __('ratings.errors.not_found')], 404);
    }

    private function accessDenied(): JsonResponse
    {
        return response()->json(['message' => __('ratings.errors.access_denied')], 403);
    }

    private function accountSuspended(): JsonResponse
    {
        return response()->json(['message' => __('ratings.errors.account_suspended')], 403);
    }

    private function alreadyRated(): JsonResponse
    {
        return response()->json(['message' => __('ratings.errors.already_rated')], 409);
    }

    /**
     * Defense-in-depth only — SubmitRatingRequest's own validation rules
     * already reject an out-of-range score or an over-long comment
     * before the controller runs; these two branches exist because the
     * domain aggregate re-enforces both invariants itself.
     */
    private function invalidScore(): JsonResponse
    {
        return response()->json(['message' => __('ratings.errors.invalid_score')], 422);
    }

    private function commentTooLong(): JsonResponse
    {
        return response()->json(['message' => __('ratings.errors.comment_too_long')], 422);
    }
}
