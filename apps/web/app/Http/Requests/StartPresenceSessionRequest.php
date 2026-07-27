<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StartPresenceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may start a presence session for
        // themselves; PresenceSessionService rejects the queue if it
        // isn't published, and the persistence-level partial unique index
        // rejects a second active session for the same seller/queue.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'queue_id' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'queue_id' => __('presence.fields.queue_id'),
        ];
    }
}
