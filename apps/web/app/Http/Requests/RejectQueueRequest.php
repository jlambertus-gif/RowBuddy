<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RejectQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The `queues.moderate` route middleware already gates access;
        // this is not a per-resource ownership check.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('queues.fields.reason'),
        ];
    }
}
