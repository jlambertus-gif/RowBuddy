<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersRatingResponses;
use App\Http\Requests\SubmitRatingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RowBuddy\Ratings\Application\RatingSubmissionService;
use RowBuddy\Ratings\Exceptions\InvalidRatingScore;
use RowBuddy\Ratings\Exceptions\RaterAccountSuspended;
use RowBuddy\Ratings\Exceptions\RatingAlreadyExistsForTransferAndRater;
use RowBuddy\Ratings\Exceptions\RatingCommentTooLong;
use RowBuddy\Ratings\Exceptions\RatingSubmissionNotAuthorized;
use RowBuddy\Ratings\Exceptions\TransferNotEligibleForRating;

/**
 * Mobile Sprint 4 (ADR-028 §3). This is the first HTTP surface either
 * RatingSubmissionService or the Rating aggregate has ever had, on any
 * client — there is no existing web route to mirror, unlike every
 * controller Sprints 1-3 added. requestingUserId (the rater) is derived
 * exclusively from the authenticated user; rateeId is always derived by
 * the domain service itself from the transfer's own participants, never
 * accepted from request input.
 */
final class SubmitRatingController extends Controller
{
    use RendersRatingResponses;

    public function __invoke(SubmitRatingRequest $request, RatingSubmissionService $service, string $transferId): JsonResponse
    {
        $raterId = (string) $request->user()->id;

        try {
            $rating = $service->submit(
                (string) Str::uuid(),
                $transferId,
                $raterId,
                $request->integer('score'),
                $request->string('comment')->toString() ?: null,
            );
        } catch (RaterAccountSuspended) {
            return $this->accountSuspended();
        } catch (TransferNotEligibleForRating) {
            return $this->notFound();
        } catch (RatingSubmissionNotAuthorized) {
            return $this->accessDenied();
        } catch (RatingAlreadyExistsForTransferAndRater) {
            return $this->alreadyRated();
        } catch (InvalidRatingScore) {
            return $this->invalidScore();
        } catch (RatingCommentTooLong) {
            return $this->commentTooLong();
        }

        return response()->json(['data' => $this->toResponse($rating)], 201);
    }
}
