<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PlaceBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may attempt to bid; BidService itself
        // rejects seller self-bidding and suspended accounts (ADR-012,
        // ADR-026 §4) — this endpoint adds no further authorization gate
        // on top of that.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_minor_units' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount_minor_units' => __('bids.fields.amount'),
            'currency' => __('bids.fields.currency'),
        ];
    }
}
