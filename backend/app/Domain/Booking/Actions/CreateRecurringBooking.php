<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\DTOs\CreateBookingData;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRecurringBooking
{
    public function __construct(
        private readonly CreateBooking $createBookingAction,
    ) {}

    public function execute(int $userId, int $courtId, array $startsAt, int $weeks): array
    {
        $user = User::find($userId);
        if ($user && ! $user->hasVerifiedEmail()) {
            abort(403, 'Vui lòng xác thực email của bạn trước khi thực hiện đặt sân. Yêu cầu gửi lại email xác thực tại /api/v1/auth/email/verification-notification.');
        }
        if ($weeks < 2 || $weeks > 12) {
            throw ValidationException::withMessages(['weeks' => 'Số tuần phải từ 2 đến 12.']);
        }
        $base = collect($startsAt)->map(fn (string $value) => Carbon::parse($value))->sort()->values();
        if ($base->isEmpty()) {
            throw ValidationException::withMessages(['slots' => 'Cần ít nhất một slot.']);
        }
        $series = collect();

        DB::transaction(function () use ($userId, $courtId, $base, $weeks, &$series) {
            for ($week = 0; $week < $weeks; $week++) {
                $slots = $base->map(fn (Carbon $time) => $time->copy()->addWeeks($week)->toIso8601String())->all();
                $series->push($this->createBookingAction->execute(
                    new CreateBookingData($userId, $courtId, $slots)
                ));
            }
        });

        return $series->all();
    }
}
