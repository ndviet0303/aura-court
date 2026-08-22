<?php

namespace App\Services;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\PriceQuote;
use App\Models\PriceRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PriceQuoteService
{
    private const PRECEDENCE = [
        'date_exception' => 4,
        'holiday' => 4,
        'peak' => 3,
        'weekly' => 2,
        'base' => 1,
    ];

    public function create(int $userId, int $organizationId, int $courtId, array $slots): PriceQuote
    {
        $court = Court::query()
            ->whereKey($courtId)
            ->where('is_active', true)
            ->whereHas('venue', fn ($query) => $query->where('organization_id', $organizationId)->where('is_active', true))
            ->with('venue')
            ->firstOrFail();

        $resolvedSlots = $this->resolveSlots($slots);
        $rules = PriceRule::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where(function ($query) use ($court) {
                $query->whereNull('court_id')->orWhere('court_id', $court->id);
            })
            ->where(function ($query) use ($court) {
                $query->whereNull('venue_id')->orWhere('venue_id', $court->venue_id);
            })
            ->get();

        $legacyRules = CourtPriceSlot::query()->where('court_id', $court->id)->where('is_active', true)->get();
        $snapshot = [];
        $appliedRuleIds = [];
        $subtotal = 0;
        $ruleVersion = 1;

        foreach ($resolvedSlots as $time) {
            [$price, $rule] = $this->priceFor($time, $court, $rules, $legacyRules);
            $subtotal += $price;
            $ruleVersion = max($ruleVersion, $rule['version'] ?? 1);
            if (isset($rule['id'])) {
                $appliedRuleIds[] = $rule['id'];
            }

            $snapshot[] = [
                'court_id' => $court->id,
                'starts_at' => $time->copy()->utc()->toIso8601String(),
                'ends_at' => $time->copy()->addHour()->utc()->toIso8601String(),
                'unit_price' => $price,
                'rule' => $rule,
            ];
        }

        return PriceQuote::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'court_id' => $court->id,
            'quote_token' => (string) Str::uuid(),
            'status' => 'open',
            'currency' => 'VND',
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
            'rule_version' => $ruleVersion,
            'slot_snapshot' => $snapshot,
            'applied_rule_ids' => array_values(array_unique($appliedRuleIds)),
            'expires_at' => now()->addMinutes(15),
        ])->load(['court.venue']);
    }

    /** @return array{0:int,1:array<string,mixed>} */
    private function priceFor(Carbon $time, Court $court, Collection $rules, Collection $legacyRules): array
    {
        foreach (array_keys(self::PRECEDENCE) as $type) {
            $matching = $rules
                ->filter(fn (PriceRule $rule): bool => $rule->type === $type && $this->matches($rule, $time))
                ->sortByDesc(fn (PriceRule $rule): array => [$this->scopeRank($rule, $court), (int) $rule->priority, (int) $rule->version, (int) $rule->id]);
            $rule = $matching->first();
            if ($rule) {
                return [(int) $rule->price, [
                    'id' => $rule->id,
                    'type' => $rule->type,
                    'version' => $rule->version,
                    'price' => (int) $rule->price,
                ]];
            }

            if ($type === 'weekly') {
                $legacy = $legacyRules->first(fn (CourtPriceSlot $slot): bool => (int) $slot->weekday === $time->dayOfWeekIso
                    && substr((string) $slot->starts_at, 0, 5) === $time->format('H:i')
                    && substr((string) $slot->ends_at, 0, 5) === $time->copy()->addHour()->format('H:i'));
                if ($legacy) {
                    return [(int) $legacy->price, [
                        'id' => 'legacy:'.$legacy->id,
                        'type' => 'weekly',
                        'version' => 1,
                        'price' => (int) $legacy->price,
                    ]];
                }
            }
        }

        throw ValidationException::withMessages(['slots' => "Slot {$time->format('d/m H:i')} chưa mở bán."]);
    }

    private function matches(PriceRule $rule, Carbon $time): bool
    {
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
            if ($time->format('H:i') < $start || $time->copy()->addHour()->format('H:i') > $end) {
                return false;
            }
        }

        return true;
    }

    private function scopeRank(PriceRule $rule, Court $court): int
    {
        return $rule->court_id === $court->id ? 3 : ($rule->venue_id === $court->venue_id ? 2 : 1);
    }

    /** @return Collection<int,Carbon> */
    private function resolveSlots(array $slots): Collection
    {
        if (count($slots) < 1 || count($slots) > 24) {
            throw ValidationException::withMessages(['slots' => 'Chọn từ 1 đến 24 slot.']);
        }

        $zone = config('app.timezone');
        $local = collect($slots)->map(function (mixed $value) use ($zone): Carbon {
            $value = is_array($value) ? ($value['starts_at'] ?? null) : $value;
            try {
                $time = Carbon::parse((string) $value)->setTimezone($zone);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['slots' => 'Thời gian slot không hợp lệ.']);
            }

            if ($time->minute !== 0 || $time->second !== 0) {
                throw ValidationException::withMessages(['slots' => 'Slot phải bắt đầu đúng giờ.']);
            }
            if ($time->isPast()) {
                throw ValidationException::withMessages(['slots' => 'Không thể báo giá slot trong quá khứ.']);
            }

            return $time;
        })->sort()->values();

        if ($local->unique(fn (Carbon $time): string => $time->toIso8601String())->count() !== $local->count()) {
            throw ValidationException::withMessages(['slots' => 'Không được chọn slot trùng nhau.']);
        }

        return $local;
    }
}
