<?php

namespace App\Domain\Attendance\DTOs;

use App\Http\Requests\Attendance\AdjustAttendanceRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/** FR-108: the corrected operational status of a booking and the check-in/out times that status has. */
readonly class AttendanceAdjustmentData
{
    public function __construct(
        public User $owner,
        public int $organizationId,
        public string $code,
        public string $status,
        public ?CarbonInterface $checkedInAt,
        public ?CarbonInterface $checkedOutAt,
        public string $reason,
    ) {}

    public static function fromRequest(AdjustAttendanceRequest $request, int $organizationId): self
    {
        return new self(
            $request->user(),
            $organizationId,
            (string) $request->validated('code'),
            (string) $request->validated('status'),
            self::wallTime($request->validated('checked_in_at')),
            self::wallTime($request->validated('checked_out_at')),
            (string) $request->validated('reason'),
        );
    }

    /** The attendance columns hold app-timezone wall time to the second, as the commands write them. */
    private static function wallTime(?string $value): ?CarbonInterface
    {
        return $value === null ? null : Carbon::parse($value)->setTimezone(config('app.timezone'))->startOfSecond();
    }
}
