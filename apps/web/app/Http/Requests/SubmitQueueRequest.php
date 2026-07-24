<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may submit a queue for approval (ADR-005);
        // the restricted-category/jurisdiction gate is enforced downstream
        // by QueueSubmissionService, not here.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:255'],
            'jurisdiction_country' => ['required', 'string', 'size:2'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'numeric', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category' => __('queues.fields.category'),
            'jurisdiction_country' => __('queues.fields.jurisdiction_country'),
            'latitude' => __('queues.fields.latitude'),
            'longitude' => __('queues.fields.longitude'),
            'radius_meters' => __('queues.fields.radius_meters'),
        ];
    }
}
