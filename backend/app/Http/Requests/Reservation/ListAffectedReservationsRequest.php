<?php

namespace App\Http\Requests\Reservation;

use App\Models\AffectedReservationCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAffectedReservationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'venue_id' => ['nullable', 'integer'],
            'court_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', Rule::in(AffectedReservationCase::SOURCE_TYPES)],
            'status' => ['nullable', Rule::in([...AffectedReservationCase::OPEN_STATUSES, ...AffectedReservationCase::CLOSED_STATUSES])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
