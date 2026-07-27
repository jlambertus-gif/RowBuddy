<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecordGpsPingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may attempt this; PresenceSessionService
        // enforces that the session actually belongs to them.
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
            'accuracy_meters' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'latitude' => __('presence.fields.latitude'),
            'longitude' => __('presence.fields.longitude'),
            'accuracy_meters' => __('presence.fields.accuracy_meters'),
        ];
    }
}
