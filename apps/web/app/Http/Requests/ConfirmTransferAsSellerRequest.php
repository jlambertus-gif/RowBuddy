<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmTransferAsSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may attempt this; TransferConfirmationService
        // enforces that the requester is actually this transfer's seller.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qr_token' => ['required', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'qr_token' => __('transfers.fields.qr_token'),
            'latitude' => __('transfers.fields.latitude'),
            'longitude' => __('transfers.fields.longitude'),
        ];
    }
}
