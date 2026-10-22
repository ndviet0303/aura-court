<?php

namespace App\Http\Requests\Reservation;

use App\Models\AffectedReservationCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Design D8: every operator command carries the customer's agreement and the contact channel; 422 without them. */
class AffectedBookingCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'customer_agreed' => ['required', 'accepted'],
            'channel' => ['required', Rule::in(AffectedReservationCase::CONTACT_CHANNELS)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];

        return $rules + match ($this->route()?->getActionMethod()) {
            'moveCourt' => ['court_id' => ['required', 'integer']],
            'changeTime' => ['starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at']],
            'cancel' => ['refund_note' => ['nullable', 'string', 'max:1000']],
            default => [],
        };
    }
}
