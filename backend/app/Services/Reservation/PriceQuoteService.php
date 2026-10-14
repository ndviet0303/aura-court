<?php

namespace App\Services\Reservation;

use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\CourtPriceSlot;
use App\Models\PriceQuote;
use App\Models\PriceRule;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Services\Booking\DiscountService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PriceQuoteService
{
    public function __construct(
        private readonly VenueOperatingCalendar $operatingCalendar,
        private readonly DiscountService $discounts,
        private readonly CourtPriceRuleService $priceRules,
    ) {}

    private const PRECEDENCE = [
        'date_exception' => 4,
        'holiday' => 4,
        'peak' => 3,
        'weekly' => 2,
        'base' => 1,
    ];

    public function create(int $userId, int $organizationId, int $courtId, array $slots, ?string $couponCode = null): PriceQuote
    {
        $eligibility = app(VerifyEffectiveBookingEligibility::class);
        $court = $eligibility->assertEligible($courtId, publicFacing: true);
        $court->loadMissing(['sportZone']);

        if ($court->venue?->organization_id) {
            $organizationId = (int) $court->venue->organization_id;
        }

        $duration = (int) ($court->sportZone?->slot_duration ?: 60);
        $timezone = $court->venue?->timezone ?: config('app.timezone');
        $resolvedSlots = $this->resolveSlots($slots, $duration, $timezone);
        foreach ($resolvedSlots as $time) {
            if (! $this->operatingCalendar->isOpen($court, $time, $time->copy()->addMinutes($duration))) {
                throw ValidationException::withMessages(['slots' => 'Khung giờ đã chọn nằm ngoài lịch hoạt động của sân.']);
            }
        }

        // Check for maintenance closures / court blocks
        $firstSlot = $resolvedSlots->first();
        $lastSlot = $resolvedSlots->last();
        $blockStarts = $firstSlot->copy()->utc();
        $blockEnds = $lastSlot->copy()->addMinutes($duration)->utc();

        $overlappingBlock = CourtBlock::query()
            ->where('court_id', $court->id)
            ->where('starts_at', '<', $blockEnds)
            ->where('ends_at', '>', $blockStarts)
            ->first();

        if ($overlappingBlock) {
            abort(409, 'Sân đang bảo trì hoặc tạm đóng trong khoảng thời gian đã chọn.');
        }

        $overlapping = ReservationSlot::query()
            ->where('court_id', $court->id)
            ->where('starts_at', '<', $blockEnds)
            ->where('ends_at', '>', $blockStarts)
            ->whereHas('reservation', fn ($r) => $r->whereIn('status', ['held', 'confirmed']))
            ->exists();

        if ($overlapping) {
            abort(409, 'The selected court-time is no longer available.');
        }

        $snapshot = $this->priceSnapshots($court, $resolvedSlots->all(), $duration);
        $subtotal = (int) collect($snapshot)->sum('unit_price');
        $appliedRuleIds = collect($snapshot)->pluck('rule.id')->filter()->values()->all();
        $ruleVersion = max([1, ...collect($snapshot)->map(fn (array $slot) => (int) ($slot['rule']['version'] ?? 1))->all()]);
        $user = User::findOrFail($userId);
        // preview() lets a code below its minimum spend through with no discount; a quote must refuse it.
        $this->discounts->resolveEligibleCoupon($couponCode, $organizationId, $subtotal, $user, (int) $court->venue_id, (int) $court->id);
        $benefit = $this->discounts->preview($user, $subtotal, $couponCode, $organizationId, (int) $court->venue_id, (int) $court->id);
        $benefitSnapshot = $benefit['membership_snapshot'] ? [
            ...$benefit['membership_snapshot'],
            'membership_discount_amount' => (int) $benefit['membership_discount'],
        ] : [];
        if ($benefit['coupon']) {
            $benefitSnapshot += ['coupon_id' => (int) $benefit['coupon']->id, 'coupon_code' => $benefit['coupon']->code, 'coupon_discount_amount' => (int) $benefit['coupon_discount']];
        }

        return PriceQuote::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'court_id' => $court->id,
            'quote_token' => (string) Str::uuid(),
            'status' => 'open',
            'currency' => 'VND',
            'subtotal' => $subtotal,
            'total_amount' => (int) $benefit['total'],
            'benefit_snapshot' => $benefitSnapshot ?: null,
            'rule_version' => $ruleVersion,
            'slot_snapshot' => $snapshot,
            'applied_rule_ids' => array_values(array_unique($appliedRuleIds)),
            'expires_at' => now()->addMinutes(15),
        ])->load(['court.venue', 'court.sportZone']);
    }

    /**
     * Authoritatively calculates exact slot subtotal for a court without creating any quote, reservation, or hold.
     *
     * @param  array<int, mixed>  $slots
     */
    public function calculateSubtotal(Court $court, array $slots): int
    {
        $court->loadMissing(['venue', 'sportZone']);
        $duration = (int) ($court->sportZone?->slot_duration ?: 60);
        $timezone = $court->venue?->timezone ?: config('app.timezone');

        if (empty($slots)) {
            throw ValidationException::withMessages(['slots' => 'Danh sách giờ chọn không được để trống.']);
        }

        $resolvedSlots = $this->resolveSlots($slots, $duration, $timezone);
        foreach ($resolvedSlots as $time) {
            if (! $this->operatingCalendar->isOpen($court, $time, $time->copy()->addMinutes($duration))) {
                throw ValidationException::withMessages(['slots' => 'Khung giờ đã chọn nằm ngoài lịch hoạt động của sân.']);
            }
        }

        return (int) collect($this->priceSnapshots($court, $resolvedSlots->all(), $duration))->sum('unit_price');
    }

    /** @param array<int, mixed> $times
     * @return array<int, array{court_id:int, starts_at:string, ends_at:string, unit_price:int, rule:array<string,mixed>}|null>
     */
    public function priceSnapshots(Court $court, array $times, ?int $duration = null, bool $skipUnpriced = false): array
    {
        $court->loadMissing(['venue', 'sportZone']);
        $duration ??= (int) ($court->sportZone?->slot_duration ?: 60);
        $timezone = $court->venue?->timezone ?: config('app.timezone');
        ['rules' => $rules, 'slots' => $legacyRules] = $this->priceRules->forCourt($court);

        return collect($times)->map(function ($value) use ($court, $rules, $legacyRules, $duration, $timezone, $skipUnpriced): ?array {
            $time = $value instanceof \DateTimeInterface
                ? Carbon::instance($value)->setTimezone($timezone)
                : Carbon::parse((string) $value, $timezone);
            try {
                [$price, $rule] = $this->priceFor($time, $court, $rules, $legacyRules, $duration);
            } catch (ValidationException $exception) {
                if ($skipUnpriced) {
                    return null;
                }

                throw $exception;
            }

            return [
                'court_id' => (int) $court->id,
                'starts_at' => $time->copy()->utc()->toIso8601String(),
                'ends_at' => $time->copy()->addMinutes($duration)->utc()->toIso8601String(),
                'unit_price' => $price,
                'rule' => $rule,
            ];
        })->values()->all();
    }

    /** @return array{0:int,1:array<string,mixed>} */
    private function priceFor(Carbon $time, Court $court, Collection $rules, Collection $legacyRules, int $duration = 60): array
    {
        foreach (array_keys(self::PRECEDENCE) as $type) {
            $matching = $rules
                ->filter(fn (PriceRule $rule): bool => $rule->type === $type && $this->matches($rule, $time, $duration))
                ->sortByDesc(fn (PriceRule $rule): array => [$this->scopeRank($rule, $court), (int) $rule->priority, (int) $rule->version, (int) $rule->id]);
            $rule = $matching->first();
            if ($rule) {
                return [(int) $rule->price, [
                    'id' => $rule->id,
                    'type' => $rule->type,
                    'version' => $rule->version,
                    'priority' => $rule->priority,
                    'effective_from' => $rule->effective_from?->toDateString(),
                    'effective_until' => $rule->effective_until?->toDateString(),
                    'price' => (int) $rule->price,
                ]];
            }

            if ($type === 'weekly') {
                $legacy = $legacyRules->first(fn (CourtPriceSlot $slot): bool => (int) $slot->weekday === $time->dayOfWeekIso
                    && substr((string) $slot->starts_at, 0, 5) === $time->format('H:i')
                    && substr((string) $slot->ends_at, 0, 5) === $time->copy()->addMinutes($duration)->format('H:i'));
                if ($legacy) {
                    return [(int) $legacy->price, [
                        'id' => 'legacy:'.$legacy->id,
                        'type' => 'weekly',
                        'version' => 1,
                        'priority' => 0,
                        'effective_from' => null,
                        'effective_until' => null,
                        'price' => (int) $legacy->price,
                    ]];
                }
            }
        }
        throw ValidationException::withMessages(['slots' => "Khung giờ {$time->format('d/m H:i')} chưa mở bán."]);
    }

    private function matches(PriceRule $rule, Carbon $time, int $duration = 60): bool
    {
        $date = $time->toDateString();
        if (($rule->effective_from && $date < $rule->effective_from->toDateString())
            || ($rule->effective_until && $date > $rule->effective_until->toDateString())) {
            return false;
        }
        if (in_array($rule->type, ['date_exception', 'holiday'], true)
            && $rule->exception_date?->toDateString() !== $time->toDateString()) {
            return false;
        }
        if ($rule->type === 'weekly' && (int) $rule->weekday !== $time->dayOfWeekIso) {
            return false;
        }
        if (in_array($rule->type, ['weekly', 'peak', 'date_exception', 'holiday'], true)
            && $rule->starts_at !== null
            && $rule->ends_at !== null) {
            $start = substr((string) $rule->starts_at, 0, 5);
            $end = substr((string) $rule->ends_at, 0, 5);
            if ($time->format('H:i') < $start || $time->copy()->addMinutes($duration)->format('H:i') > $end) {
                return false;
            }
        }

        return true;
    }

    private function scopeRank(PriceRule $rule, Court $court): int
    {
        if ($rule->court_id === $court->id) {
            return 4;
        }
        if ($rule->sport_zone_id !== null && $rule->sport_zone_id === $court->sport_zone_id) {
            return 3;
        }
        if ($rule->venue_id === $court->venue_id) {
            return 2;
        }

        return 1;
    }

    /** @return Collection<int,Carbon> */
    private function resolveSlots(array $slots, int $duration = 60, ?string $zone = null): Collection
    {
        if (count($slots) < 1 || count($slots) > 24) {
            throw ValidationException::withMessages(['slots' => 'Chọn từ 1 đến 24 giờ.']);
        }

        $zone ??= config('app.timezone');
        $local = collect($slots)->map(function (mixed $value) use ($zone): Carbon {
            $value = is_array($value) ? ($value['starts_at'] ?? null) : $value;
            try {
                $time = Carbon::parse((string) $value)->setTimezone($zone);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['slots' => 'Giờ bắt đầu không hợp lệ.']);
            }

            if ($time->minute % 30 !== 0 || $time->second !== 0) {
                throw ValidationException::withMessages(['slots' => 'Giờ bắt đầu phải theo mốc 30 phút.']);
            }
            if ($time->isPast()) {
                throw ValidationException::withMessages(['slots' => 'Không thể báo giá giờ đã qua.']);
            }

            return $time;
        })->sort()->values();

        if ($local->unique(fn (Carbon $time): string => $time->toIso8601String())->count() !== $local->count()) {
            throw ValidationException::withMessages(['slots' => 'Không được chọn trùng giờ.']);
        }

        return $local;
    }
}
