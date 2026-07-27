<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UploadEvidencePhotoRequest extends FormRequest
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
            // v1 evidence capture is camera-first photo only (approved
            // Phase 2 scope) — JPEG only, no PNG/video, max 8 MiB.
            'photo' => ['required', 'file', 'image', 'mimes:jpeg', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'photo' => __('presence.fields.photo'),
        ];
    }
}
