<?php

namespace App\Http\Requests\BookingTransfer;

use Illuminate\Foundation\Http\FormRequest;

/** FR-141: free text over venue name or address, court name and note. */
class SearchTransferListingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
