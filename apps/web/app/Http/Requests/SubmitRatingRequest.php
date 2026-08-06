<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use RowBuddy\Ratings\Rating;
use RowBuddy\Ratings\ValueObjects\RatingScore;

final class SubmitRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may attempt this; RatingSubmissionService
        // enforces that the requester is actually a participant on this
        // transfer, rating the other party.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'between:'.RatingScore::MIN.','.RatingScore::MAX],
            'comment' => ['nullable', 'string', 'max:'.Rating::MAX_COMMENT_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'score' => __('ratings.fields.score'),
            'comment' => __('ratings.fields.comment'),
        ];
    }
}
