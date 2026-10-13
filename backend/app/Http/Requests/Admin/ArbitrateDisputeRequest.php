<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ArbitrateDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => 'required|string|in:resolve,dismiss,investigate_further',
            'refund_method' => 'nullable|string|in:wallet,payos_bank,owner_direct,partial_wallet,none',
            'refund_amount' => 'nullable|integer|min:0',
            'compensation_type' => 'nullable|string',
            'compensation_value' => 'nullable|string|max:255',
            'penalty_notes' => 'nullable|string|max:1000',
            'penalty_points' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:2000',
            'digital_signature_hash' => 'nullable|string|max:255',
        ];
    }
}
