<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DiscoverQueuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint: browsing/searching published queues requires
        // no authentication, unlike submission/moderation.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'latitude' => __('queues.fields.latitude'),
            'longitude' => __('queues.fields.longitude'),
            'page' => __('queues.fields.page'),
            'per_page' => __('queues.fields.per_page'),
        ];
    }
}
