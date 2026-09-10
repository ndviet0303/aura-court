<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmSubscriptionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Supports webhook or authenticated client confirmation
    }

    public function rules(): array
    {
        return [
            'reference_code' => ['required', 'string', 'max:64'],
            'provider_transaction_id' => ['required', 'string', 'max:128'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'max:10'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
