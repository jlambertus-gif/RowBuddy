<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FileDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may attempt this; DisputeFilingService
        // enforces that the requester is actually this transfer's buyer.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('disputes.filing.fields.reason'),
        ];
    }
}
