<?php

namespace App\Domain\Attendance\DTOs;

use App\Models\Reservation;

/** BR-130: a repeated scan returns the recorded result with $duplicate true and never overwrites its time. */
readonly class AttendanceOutcome
{
    public function __construct(
        public Reservation $reservation,
        public bool $duplicate,
    ) {}
}
