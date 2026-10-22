<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\VoucherUseRepositoryInterface;
use App\Models\VoucherUse;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EloquentVoucherUseRepository implements VoucherUseRepositoryInterface
{
    private const ACTIVE = [VoucherUse::STATUS_HELD, VoucherUse::STATUS_USED];

    public function forReservation(int $reservationId): ?VoucherUse
    {
        return VoucherUse::query()->with('voucherCode')->where('reservation_id', $reservationId)->first();
    }

    public function countActive(int $programId, ?int $exceptReservationId): int
    {
        return VoucherUse::query()
            ->where('program_id', $programId)
            ->whereIn('status', self::ACTIVE)
            ->when($exceptReservationId !== null, fn ($query) => $query->where('reservation_id', '!=', $exceptReservationId))
            ->count();
    }

    public function activeForCode(int $codeId): ?VoucherUse
    {
        return VoucherUse::query()->where('voucher_code_id', $codeId)->whereIn('status', self::ACTIVE)->first();
    }

    public function create(array $attributes): ?VoucherUse
    {
        try {
            return DB::transaction(fn (): VoucherUse => VoucherUse::create($attributes));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    public function markUsed(VoucherUse $use, CarbonInterface $usedAt): bool
    {
        return VoucherUse::query()->whereKey($use->id)->where('status', VoucherUse::STATUS_HELD)
            ->update(['status' => VoucherUse::STATUS_USED, 'used_at' => $this->appClock($usedAt)]) > 0;
    }

    public function releaseHeld(int $reservationId, CarbonInterface $releasedAt): bool
    {
        return VoucherUse::query()->where('reservation_id', $reservationId)->where('status', VoucherUse::STATUS_HELD)
            ->update(['status' => VoucherUse::STATUS_RELEASED, 'released_at' => $this->appClock($releasedAt)]) > 0;
    }

    /** A query update skips the AppTimezoneDateTime cast, so the time is put on the application clock here. */
    private function appClock(CarbonInterface $time): string
    {
        return CarbonImmutable::instance($time)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
