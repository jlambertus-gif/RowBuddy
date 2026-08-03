<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CompleteBuyerPaymentMethodSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may complete their own setup;
        // BuyerPaymentMethodSetupService verifies server-side, against
        // Stripe itself, that the SetupIntent actually belongs to them.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'setup_intent_id' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'setup_intent_id' => __('payments.fields.setup_intent_id'),
        ];
    }
}
