<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class RecurringSeriesPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'court_id' => 'required|integer|exists:courts,id',
            'frequency' => 'required|in:weekly,biweekly,monthly',
            'weekdays' => 'required_unless:frequency,monthly|array',
            'weekdays.*' => 'integer|min:1|max:7',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'until' => 'nullable|date|after_or_equal:starts_at',
            'occurrences' => 'nullable|integer|min:1|max:104',
        ];
    }
}
