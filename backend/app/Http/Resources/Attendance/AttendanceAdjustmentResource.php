<?php

namespace App\Http\Resources\Attendance;

use App\Models\BookingAttendanceAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FR-109: one adjustment with the values before and after it, its reason, the owner and when it was made.
 *
 * @mixin BookingAttendanceAdjustment
 */
class AttendanceAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'before' => $this->before,
            'after' => $this->after,
            'adjusted_by' => $this->adjustedBy?->name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
