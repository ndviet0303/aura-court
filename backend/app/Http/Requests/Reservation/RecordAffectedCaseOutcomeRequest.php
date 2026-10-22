<?php

namespace App\Http\Requests\Reservation;

use App\Models\AffectedReservationCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Contacting, unreachable or kept; moved, time_changed and cancelled come only from the design D8 commands. */
class RecordAffectedCaseOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([AffectedReservationCase::STATUS_CONTACTING, AffectedReservationCase::STATUS_UNREACHABLE, AffectedReservationCase::STATUS_KEPT])],
            'channel' => ['required', Rule::in(AffectedReservationCase::CONTACT_CHANNELS)],
            'note' => ['required', 'string', 'max:1000'],
            'refund_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
