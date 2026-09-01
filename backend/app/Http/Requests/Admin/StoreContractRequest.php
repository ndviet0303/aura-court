<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'court_id' => 'required|integer|exists:courts,id',
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'weekday' => 'nullable|string|max:100',
            'time_slot' => 'nullable|string|max:100',
            'frequency' => 'nullable|string|in:weekly,biweekly,monthly',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'monthly_amount' => 'required|integer|min:100000',
            'deposit_amount' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
