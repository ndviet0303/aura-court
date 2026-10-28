<?php

namespace App\Http\Resources\Attendance;

use App\Domain\Attendance\Rules\UsageTime;
use App\Models\BookingAttendance;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FR-102: what an operator needs to serve a booking (venue, court, booked time, holder, amount) and its recorded
 * check-in, check-out or no-show with who recorded it, plus the FR-104 flag of a booking in use. Staff notes and
 * payment receiver details are not included.
 *
 * @mixin Reservation
 */
class OperationalReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attendance = $this->attendance;
        $at = fn (string $column) => $attendance?->{$column}?->toIso8601String();

        return [
            'id' => $this->id,
            'code' => $this->code,
            // D5: check-in, check-out, no-show and adjustments look a booking up by this code.
            'check_in_code' => $this->check_in_code,
            'status' => $this->status,
            // D3: the attendance row, not reservations.status, holds the operational state.
            'operational_status' => $attendance ? BookingAttendance::OPERATIONAL_STATUS[$attendance->status] : $this->status,
            // FR-104, BR-121: a booking not checked out stays in use past its end; its booked time never stretches.
            'usage_flag' => $attendance?->status === BookingAttendance::STATUS_CHECKED_IN && $this->ends_at !== null
                ? UsageTime::flag($this->ends_at, now())
                : null,
            'venue' => ['id' => $this->court?->venue?->id, 'name' => $this->court?->venue?->name],
            'court' => ['id' => $this->court_id, 'code' => $this->court?->code, 'name' => $this->court?->name],
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'customer_name' => $this->holder['name'] ?? $this->user?->name,
            'customer_phone' => $this->holder['phone'] ?? $this->user?->phone,
            'total_amount' => (int) $this->total_amount,
            'currency' => $this->currency,
            'attendance' => $attendance ? [
                'status' => $attendance->status,
                'checked_in_at' => $at('checked_in_at'),
                'checked_in_by' => $attendance->checkedInBy?->name,
                'checked_out_at' => $at('checked_out_at'),
                'checked_out_by' => $attendance->checkedOutBy?->name,
                'no_show_at' => $at('no_show_at'),
                'no_show_by' => $attendance->noShowBy?->name,
            ] : null,
        ];
    }
}
