<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:190',
            'summary' => 'required|string|min:10',
            'dispute_type' => 'required|in:booking_cancellation,court_quality,payment_issue,no_show_conflict,other',
            'severity' => 'nullable|in:low,medium,high,urgent',
            'booking_id' => 'prohibited',
            'reservation_id' => 'nullable|integer|exists:reservations,id',
            'evidence' => 'nullable|array',
            'amount_disputed' => 'nullable|integer|min:0',
            'reference_type' => 'nullable|string|max:32',
            'reference_id' => 'nullable|integer',
        ];
    }
}
