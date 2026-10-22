<?php

namespace App\Domain\Attendance\DTOs;

use App\Http\Requests\Attendance\AttendanceCommandRequest;
use App\Models\User;

/** D8: check-in, check-out and no-show by a scanned or typed booking code; scan and manual entry differ only on screen. */
readonly class AttendanceCommandData
{
    public function __construct(
        public User $actor,
        public int $organizationId,
        public string $code,
    ) {}

    public static function fromRequest(AttendanceCommandRequest $request, int $organizationId): self
    {
        return new self($request->user(), $organizationId, (string) $request->validated('code'));
    }
}
