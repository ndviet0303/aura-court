<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\VoucherUseRepositoryInterface;
use App\Models\VoucherUse;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EloquentVoucherUseRepository implements VoucherUseRepositoryInterface
{
    private const ACTIVE = [VoucherUse::STATUS_HELD, VoucherUse::STATUS_USED];

    public function forReservation(int $reservationId): ?VoucherUse
    {
        return VoucherUse::query()->with('voucherCode')->where('reservation_id', $reservationId)->first();
    }

    public function activeDiscounts(array $reservationIds): array
    {
        return VoucherUse::query()->whereIn('reservation_id', $reservationIds)->whereIn('status', self::ACTIVE)
            ->pluck('discount', 'reservation_id')->map(fn ($discount): int => (int) $discount)->all();
    }

    public function countActive(int $programId, ?int $exceptReservationId): int
    {
        return VoucherUse::query()
            ->where('program_id', $programId)
            ->whereIn('status', self::ACTIVE)
            ->when($exceptReservationId !== null, fn ($query) => $query->where('reservation_id', '!=', $exceptReservationId))
            ->count();
    }

    public function paginateUsed(int $programId, int $perPage): LengthAwarePaginator
    {
        return $this->withBooking($programId, VoucherUse::STATUS_USED)->orderByDesc('used_at')->orderByDesc('id')->paginate($perPage);
    }

    public function held(int $programId): Collection
    {
        return $this->withBooking($programId, VoucherUse::STATUS_HELD)->orderBy('held_at')->orderBy('id')->get();
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

    /** @return Builder<VoucherUse> */
    private function withBooking(int $programId, string $status): Builder
    {
        return VoucherUse::query()
            ->with(['voucherCode:id,code', 'reservation.user:id,name', 'reservation.court:id,name', 'reservation.slots'])
            ->where('program_id', $programId)
            ->where('status', $status);
    }

    /** A query update skips the AppTimezoneDateTime cast, so the time is put on the application clock here. */
    private function appClock(CarbonInterface $time): string
    {
        return CarbonImmutable::instance($time)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
