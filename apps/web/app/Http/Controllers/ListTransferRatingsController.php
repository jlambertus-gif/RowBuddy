<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersRatingResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RowBuddy\Ratings\Application\RatingRevealEvaluator;
use RowBuddy\Ratings\Contracts\RatingRepository;
use RowBuddy\Ratings\Contracts\TransferParticipantLookup;

/**
 * Mobile Sprint 4 (ADR-028 §3). The first HTTP surface for
 * RatingRevealEvaluator. Double-blind by construction (ADR-024 §5): the
 * requester's own rating is always shown, but the counterpart's rating
 * is included only once {@see RatingRevealEvaluator::isRevealed()} says
 * so — never gated on the requester having submitted their own rating
 * first, since reveal is keyed on the *counterpart's* submission, not
 * the viewer's. requestingUserId is derived exclusively from the
 * authenticated user; only a participant on this transfer may call
 * this at all.
 */
final class ListTransferRatingsController extends Controller
{
    use RendersRatingResponses;

    public function __invoke(
        Request $request,
        TransferParticipantLookup $transferParticipants,
        RatingRepository $ratings,
        RatingRevealEvaluator $revealEvaluator,
        string $transferId,
    ): JsonResponse {
        $snapshot = $transferParticipants->findByTransferId($transferId);

        if ($snapshot === null) {
            return $this->notFound();
        }

        $requestingUserId = (string) $request->user()->id;

        if ($requestingUserId !== $snapshot->buyerId && $requestingUserId !== $snapshot->sellerId) {
            return $this->accessDenied();
        }

        $mine = null;
        $counterpart = null;
        $counterpartSubmitted = false;

        foreach ($ratings->findByTransferId($transferId) as $rating) {
            if ($rating->raterId === $requestingUserId) {
                $mine = $rating;

                continue;
            }

            $counterpartSubmitted = true;

            if ($revealEvaluator->isRevealed($rating)) {
                $counterpart = $rating;
            }
        }

        return response()->json(['data' => [
            'mine' => $mine !== null ? $this->toResponse($mine) : null,
            'counterpart' => $counterpart !== null ? $this->toResponse($counterpart) : null,
            'counterpart_submitted' => $counterpartSubmitted,
        ]]);
    }
}
