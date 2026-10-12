<?php

namespace Database\Seeders;

use App\Models\LoyaltyRule;
use App\Models\Organization;
use App\Models\PointLedger;
use App\Models\PosOrder;
use App\Models\Reservation;
use App\Models\TenantCustomer;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Services\Loyalty\LoyaltyPointService;
use App\Services\POS\PosService;
use Carbon\Carbon;
use Database\Seeders\Concerns\KeepsWakingHours;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Membership plans, the loyalty program and its history for the two tenants that sell at the counter.
 *
 * - Loyalty: 10 points per 1,000 VND (earn_points_per_100_vnd = 1) and 1,000 points = 5,000 VND, so the admin
 *   screen and the earn/redeem services agree on 5% back. Points expire after 12 months.
 * - Customers who booked, bought at the counter or joined a plan get a tenant_customers row (the POS customer list).
 * - Memberships go through PosService::assignMembership. Their windows never cover a seeded checkout or POS sale,
 *   because those were priced without a member discount: active plans start after the member's latest activity,
 *   expired ones ended before their first.
 * - The point ledger is replayed in time order with the app's own writers: PosService::awardPoints for every
 *   completed counter sale to a named customer, the nightly 00:30 expiry run, and the checkout redemption of a
 *   booking that spent points (BookingPaymentSeeder owns bookings; skipped when it seeded none).
 */
class MembershipPlanSeeder extends Seeder
{
    use KeepsWakingHours;

    private const EXPIRY_RUN_AT = '00:30';

    private Carbon $now;

    private PosService $pos;

    private LoyaltyPointService $loyalty;

    public function run(): void
    {
        $this->now = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'));
        $this->pos = app(PosService::class);
        $this->loyalty = app(LoyaltyPointService::class);
        // Launched with the POS catalog (PosProductSeeder), before the oldest seeded counter sale.
        $launchedAt = $this->now->copy()->startOfDay()->subMonths(14)->setTime(9, 0);

        foreach ($this->programs() as $slug => $program) {
            $org = Organization::where('slug', $slug)->first();
            if (! $org) {
                continue;
            }
            $plans = $this->seedPlans($org, $program['plans'], $launchedAt);
            $this->seedRules($org, $launchedAt);
            $memberships = $this->planMemberships($org, $plans, $program['members'], $launchedAt);
            $this->seedCustomers($org, $memberships);
            $this->seedMemberships($org, $memberships);
            $this->seedLedger($org);
            $this->verify($org);
        }
    }

    /** @return Collection<string, TenantMembershipPlan> keyed by name */
    private function seedPlans(Organization $org, array $plans, Carbon $launchedAt): Collection
    {
        $seeded = collect();
        foreach ($plans as [$name, $price, $benefits]) {
            // Admin edits update a plan row in place, so a single published v1 per plan is what the app holds.
            $plan = TenantMembershipPlan::firstOrNew(['organization_id' => $org->id, 'name' => $name, 'version' => 1]);
            $plan->forceFill([
                'price' => $price,
                'benefits' => $benefits,
                'status' => 'published',
                'published_at' => $launchedAt,
                'created_at' => $launchedAt,
                'updated_at' => $launchedAt,
            ])->save();
            $seeded[$name] = $plan;
        }

        return $seeded;
    }

    /** The base rule plus campaign multipliers, shaped like EloquentLoyaltyRuleRepository writes them. */
    private function seedRules(Organization $org, Carbon $launchedAt): void
    {
        LoyaltyRule::firstOrNew(['organization_id' => $org->id, 'name' => 'Default Loyalty Config', 'version' => 1])->forceFill([
            'earn_points_per_100_vnd' => 1,
            'redeem_points_per_100_vnd' => 20,
            'status' => 'published',
            'metadata' => [
                'spend_amount_vnd' => 1000,
                'points_earned' => 10,
                'points_redeemed' => 1000,
                'reward_value_vnd' => 5000,
                'expiry_months' => 12,
            ],
            'published_at' => $launchedAt,
            'created_at' => $launchedAt,
            'updated_at' => $launchedAt,
        ])->save();

        // Earning only applies the member multiplier (LoyaltyPointService), so campaign rules stay paused:
        // an active one would promise boosts that the seeded sales never received.
        $campaigns = [
            ['Khung giờ thấp điểm', 2.0, 'Off-peak', 'T2 - T6, 08:00 - 15:00', 'Nhân đôi điểm cho hóa đơn tại quầy trong khung giờ thấp điểm ngày thường.'],
            ['Tháng sinh nhật hội viên', 3.0, 'Birthday', 'Trong tháng sinh nhật của hội viên', 'Tặng x3 điểm cho mọi hóa đơn trong tháng sinh nhật.'],
            ['Giải đấu & sự kiện CLB', 1.5, 'Tournament', 'Thứ 7 & Chủ nhật tuần đầu tháng', 'Hệ số x1.5 cho vận động viên tham gia giải nội bộ.'],
        ];
        foreach ($campaigns as $n => [$name, $multiplier, $tag, $schedule, $description]) {
            $createdAt = $launchedAt->copy()->addMonths(2 + 3 * $n);
            LoyaltyRule::firstOrNew(['organization_id' => $org->id, 'name' => $name, 'version' => 1])->forceFill([
                'earn_points_per_100_vnd' => max(1, (int) round($multiplier)),
                'redeem_points_per_100_vnd' => 100,
                'status' => 'draft',
                'metadata' => [
                    'is_multiplier_rule' => true,
                    'multiplier' => $multiplier,
                    'tag' => $tag,
                    'schedule' => $schedule,
                    'description' => $description,
                    'is_active' => false,
                ],
                'published_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }
    }

    /**
     * Membership windows that avoid every seeded checkout and counter sale of the member at this tenant.
     *
     * @return list<array{user: User, plan: TenantMembershipPlan, months: int, starts_at: Carbon}>
     */
    private function planMemberships(Organization $org, Collection $plans, array $members, Carbon $launchedAt): array
    {
        $planned = [];
        foreach ($members as [$email, $planName, $months, $state]) {
            $user = User::where('email', $email)->first();
            $plan = $plans[$planName] ?? null;
            if (! $user || ! $plan) {
                continue;
            }
            [$first, $last] = $this->activity($org->id, $user->id);

            if ($state === 'active') {
                // Joined at the counter (07:00-22:00) after the last visit, at most three weeks ago, so it runs for months.
                $joinAt = $last ? $last->copy()->addMinutes(30) : $this->now->copy()->subDays(20)->setTime(10, 0);
                if ($joinAt->hour < 7) {
                    $joinAt->setTime(7, 0);
                } elseif ($joinAt->hour >= 22) {
                    $joinAt->addDay()->setTime(7, 0);
                }
                $startsAt = $this->clamp($joinAt, $this->now->copy()->subDays(20), $this->now->copy()->subMinutes(15));
                if ($last && $startsAt->lte($last)) {
                    $this->command?->warn("MembershipPlanSeeder: {$email} was active a moment ago, membership skipped.");

                    continue;
                }
            } else {
                // Lapsed: ended a day before the first visit, and at least four months ago.
                $endsAt = ($first ? $first->copy()->subDay() : $this->now->copy())->min($this->now->copy()->subMonths(4))->copy()->setTime(10, 0);
                $startsAt = $endsAt->copy()->subMonths($months);
                if ($startsAt->lt($launchedAt->copy()->addDay())) {
                    $this->command?->warn("MembershipPlanSeeder: {$email} has no gap before the plan launch for a lapsed membership, skipped.");

                    continue;
                }
            }
            $planned[] = ['user' => $user, 'plan' => $plan, 'months' => $months, 'starts_at' => $startsAt->startOfMinute()];
        }

        return $planned;
    }

    /** Customers who booked, bought at the counter or hold a plan at this tenant, as CustomerController::store adds them. */
    private function seedCustomers(Organization $org, array $memberships): void
    {
        $customerIds = User::whereIn('email', $this->customerEmails())->pluck('id');
        $firstSeen = [];
        foreach ($customerIds as $userId) {
            [$first] = $this->activity($org->id, $userId);
            if ($first) {
                $firstSeen[$userId] = $first;
            }
        }
        foreach ($memberships as $membership) {
            $userId = $membership['user']->id;
            $firstSeen[$userId] = isset($firstSeen[$userId]) ? $firstSeen[$userId]->copy()->min($membership['starts_at'])->copy() : $membership['starts_at']->copy();
        }

        foreach (User::whereIn('id', array_keys($firstSeen))->orderBy('id')->get() as $user) {
            $createdAt = $firstSeen[$user->id]->copy()->subMinutes(10)->min($this->now)->copy();
            $createdAt = $this->awake($createdAt, before: $createdAt);
            $customer = TenantCustomer::firstOrNew(['organization_id' => $org->id, 'user_id' => $user->id]);
            $customer->forceFill([
                'is_guest' => false,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'status' => 'active',
                'created_at' => $customer->exists ? $customer->created_at : $createdAt,
                'updated_at' => $customer->exists ? $customer->updated_at : $createdAt,
            ])->save();
        }
    }

    private function seedMemberships(Organization $org, array $memberships): void
    {
        foreach ($memberships as ['user' => $user, 'plan' => $plan, 'months' => $months, 'starts_at' => $startsAt]) {
            if (TenantMembership::where('organization_id', $org->id)->where('user_id', $user->id)->where('plan_id', $plan->id)->exists()) {
                continue;
            }
            $this->at($startsAt, fn () => $this->pos->assignMembership($org->id, $user->id, $plan, $months));
        }
    }

    /**
     * Replays the tenant's point history in time order: earn on each completed counter sale to a named customer
     * (PosService::completeOrder / confirmQrPayment award on completion; settled service tabs never earn), the
     * checkout redemption, and the 00:30 expiry run on the nights after a lot expired.
     */
    private function seedLedger(Organization $org): void
    {
        $events = PosOrder::query()
            ->where('organization_id', $org->id)
            ->where('status', 'completed')
            ->whereNotNull('customer_id')
            ->where('payment_reference', 'like', 'POS-%')
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get()
            ->map(fn (PosOrder $order): array => ['at' => $order->completed_at->copy(), 'order' => $order])
            ->all();
        $redemption = $this->redemption($org);
        if ($redemption) {
            $events[] = $redemption;
        }
        usort($events, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        $expiryRuns = [];
        foreach ($events as $event) {
            $this->runExpiries($org, $expiryRuns, $event['at']);
            if (isset($event['order'])) {
                $entry = $this->earn($org, $event['order']);
                if ($entry?->expires_at && $entry->expires_at->lte($this->now)) {
                    $runAt = $entry->expires_at->copy()->setTimeFromTimeString(self::EXPIRY_RUN_AT);
                    $expiryRuns[] = $runAt->lt($entry->expires_at) ? $runAt->addDay() : $runAt;
                }
            } else {
                $this->redeem($org, $event);
            }
        }
        $this->runExpiries($org, $expiryRuns, $this->now);
    }

    private function earn(Organization $org, PosOrder $order): ?PointLedger
    {
        $existing = PointLedger::where('organization_id', $org->id)->where('user_id', $order->customer_id)
            ->where('source_type', PosOrder::class)->where('source_id', $order->id)->first();

        return $existing ?? $this->at($order->completed_at, fn () => $this->pos->awardPoints($org->id, $order->customer_id, (int) $order->total_amount, PosOrder::class, $order->id));
    }

    /** Nightly loyalty:expire-points runs due by $until, as the scheduler would have run them. */
    private function runExpiries(Organization $org, array &$runs, Carbon $until): void
    {
        sort($runs);
        while ($runs !== [] && $runs[0]->lte($until)) {
            $runAt = array_shift($runs);
            $this->at($runAt, fn () => $this->loyalty->expireDuePoints($org->id));
        }
    }

    /**
     * The earliest past checkout at this tenant that spent points, if BookingPaymentSeeder seeded one.
     *
     * @return array{at: Carbon, user_id: int, points: int, discount: int, rule_version: int, source_type: string, source_id: int, code_key: string, code: string}|null
     */
    private function redemption(Organization $org): ?array
    {
        $reservation = Reservation::query()
            ->with('legacyMapping')
            ->where('organization_id', $org->id)
            ->whereIn('user_id', User::whereIn('email', $this->customerEmails())->select('id'))
            ->whereRaw("coalesce((metadata->>'loyalty_points_redeemed')::int, 0) > 0")
            ->where('created_at', '<=', $this->now)
            ->where(fn ($q) => $q->whereIn('status', ['confirmed', 'in_use', 'completed'])
                ->orWhereHas('legacyMapping', fn ($m) => $m->whereIn('legacy_status', ['confirmed', 'completed'])))
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
        if (! $reservation) {
            $this->command?->warn("MembershipPlanSeeder: no past checkout at {$org->slug} spent loyalty points, the redemption entry was skipped.");

            return null;
        }

        $metadata = $reservation->metadata ?? [];
        $points = (int) $metadata['loyalty_points_redeemed'];
        // A booking migrated from V1 was debited by the V1 checkout ('booking'); a V2 checkout writes 'reservation'.
        $mapping = $reservation->legacyMapping->first();

        return [
            'at' => $reservation->created_at->copy(),
            'user_id' => (int) $reservation->user_id,
            'points' => $points,
            'discount' => (int) ($metadata['loyalty_discount_amount'] ?? intdiv($points * 5000, 1000)),
            'rule_version' => (int) ($metadata['loyalty_snapshot']['rule_version'] ?? 1),
            'source_type' => $mapping ? 'booking' : 'reservation',
            'source_id' => (int) ($mapping ? $mapping->legacy_booking_id : $reservation->id),
            'code_key' => $mapping ? 'booking_code' : 'reservation_code',
            'code' => (string) ($mapping ? $mapping->legacy_code : $reservation->code),
        ];
    }

    /** The checkout's own ledger debit (OrdinaryCheckoutService), written when the points were there to spend. */
    private function redeem(Organization $org, array $redemption): void
    {
        $exists = PointLedger::where('organization_id', $org->id)->where('user_id', $redemption['user_id'])
            ->where('source_type', $redemption['source_type'])->where('source_id', $redemption['source_id'])->exists();
        if ($exists) {
            return;
        }
        $available = $this->loyalty->availableBalance($org->id, $redemption['user_id'], $redemption['at']);
        $rule = $this->loyalty->getActiveBaseEarnRule($org->id);
        if (! $rule || $available < $redemption['points']) {
            $this->command?->warn("MembershipPlanSeeder: {$redemption['code']} spent {$redemption['points']} points but the customer had {$available} at checkout, redemption entry skipped.");

            return;
        }

        $this->at($redemption['at'], fn () => PointLedger::create([
            'organization_id' => $org->id,
            'user_id' => $redemption['user_id'],
            'loyalty_rule_id' => $rule->id,
            'delta' => -$redemption['points'],
            'balance_after' => (int) PointLedger::where('organization_id', $org->id)->where('user_id', $redemption['user_id'])->sum('delta') - $redemption['points'],
            'source_type' => $redemption['source_type'],
            'source_id' => $redemption['source_id'],
            'metadata' => [
                $redemption['code_key'] => $redemption['code'],
                'rule_version' => $redemption['rule_version'],
                'points_spent' => $redemption['points'],
                'discount_vnd' => $redemption['discount'],
            ],
        ]));
    }

    /** Spendable balance must equal the balance the POS customer list shows, and the ledger must chain. */
    private function verify(Organization $org): void
    {
        $failures = [];
        foreach (PointLedger::where('organization_id', $org->id)->orderBy('id')->get()->groupBy('user_id') as $userId => $entries) {
            $running = 0;
            foreach ($entries as $entry) {
                $running += $entry->delta;
                if ($entry->balance_after !== $running || $entry->created_at->gt($this->now)) {
                    $failures[] = "ledger {$entry->id}: balance_after {$entry->balance_after}, expected {$running}";
                }
            }
            $available = $this->loyalty->availableBalance($org->id, (int) $userId);
            if ($available !== $running) {
                $failures[] = "user {$userId}: spendable {$available} != ledger balance {$running}";
            }
        }
        foreach (TenantMembership::where('organization_id', $org->id)->get() as $membership) {
            [$first] = $this->activity($org->id, $membership->user_id, $membership->starts_at, $membership->ends_at);
            if ($first) {
                $this->command?->warn("MembershipPlanSeeder: membership {$membership->id} covers a checkout or sale priced without the member discount ({$first->toDateTimeString()}).");
            }
        }
        if ($failures !== []) {
            throw new RuntimeException("MembershipPlanSeeder self-check failed for {$org->slug}:\n".implode("\n", $failures));
        }
    }

    /**
     * First and last checkout or counter sale of the customer at the tenant (optionally within a window), up to now.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function activity(int $orgId, int $userId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $times = [];
        foreach ([
            Reservation::where('organization_id', $orgId)->where('user_id', $userId),
            PosOrder::where('organization_id', $orgId)->where('customer_id', $userId),
        ] as $query) {
            $query->where('created_at', '<=', $this->now)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
            foreach ([(clone $query)->min('created_at'), (clone $query)->max('created_at')] as $time) {
                if ($time !== null) {
                    $times[] = Carbon::parse($time, $this->now->getTimezone());
                }
            }
        }
        if ($times === []) {
            return [null, null];
        }
        usort($times, fn (Carbon $a, Carbon $b): int => $a <=> $b);

        return [$times[0], end($times)];
    }

    /** Runs $callback with the clock at $at so services and Eloquent timestamps record that moment. */
    private function at(Carbon $at, callable $callback): mixed
    {
        $previous = Carbon::getTestNow();
        Carbon::setTestNow($at);
        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);
        }
    }

    /** A copy of $time moved inside [$min, $max] (Carbon's min/max return one of the operands, not a copy). */
    private function clamp(Carbon $time, Carbon $min, Carbon $max): Carbon
    {
        return $time->copy()->max($min)->copy()->min($max)->copy();
    }

    private function customerEmails(): array
    {
        return array_merge(['user@example.com'], array_map(fn (int $n): string => sprintf('khach%02d@demo.auracourt.vn', $n), range(1, 24)));
    }

    private function programs(): array
    {
        $khach = fn (int $n): string => sprintf('khach%02d@demo.auracourt.vn', $n);
        $plan = fn (string $code, string $description, int $discount, float $multiplier, int $advanceDays, bool $popular, string $cycle, array $perks): array => [
            'code' => $code,
            'description' => $description,
            'discount_percent' => $discount,
            'point_multiplier' => $multiplier,
            'advance_booking_days' => $advanceDays,
            'free_weekend_hours' => 0,
            'is_popular' => $popular,
            'billing_cycle' => $cycle,
            'perks' => $perks,
        ];

        return [
            'aura-badminton-club' => [
                'plans' => [
                    ['Hội viên Bạc', 299000, $plan('BAC', 'Cho người chơi phong trào tập đều trong tuần.', 10, 1.2, 7, false, 'monthly', [
                        'Giảm 10% tiền sân khi đặt online',
                        'Giảm 10% đồ uống, dụng cụ tại quầy',
                        'Tích điểm x1.2 khi mua hàng tại quầy',
                    ])],
                    ['Hội viên Vàng', 699000, $plan('VANG', 'Gói được chọn nhiều nhất cho nhóm chơi cố định giờ vàng.', 20, 1.8, 14, true, 'monthly', [
                        'Giảm 20% tiền sân khi đặt online',
                        'Giảm 20% đồ uống, dụng cụ tại quầy',
                        'Tích điểm x1.8 khi mua hàng tại quầy',
                    ])],
                    ['Hội viên Kim Cương', 1799000, $plan('KIMCUONG', 'Cho VĐV và đội nhóm tập luyện cường độ cao mỗi ngày.', 30, 2.5, 30, false, 'monthly', [
                        'Giảm 30% tiền sân khi đặt online',
                        'Giảm 30% đồ uống, dụng cụ tại quầy',
                        'Tích điểm x2.5 khi mua hàng tại quầy',
                    ])],
                ],
                // [email, plan, months, active|lapsed]
                'members' => [
                    ['user@example.com', 'Hội viên Vàng', 3, 'active'],
                    [$khach(3), 'Hội viên Bạc', 3, 'active'],
                    [$khach(7), 'Hội viên Kim Cương', 6, 'active'],
                    [$khach(5), 'Hội viên Bạc', 1, 'lapsed'],
                    [$khach(11), 'Hội viên Vàng', 3, 'lapsed'],
                ],
            ],
            'hoang-gia-sports' => [
                'plans' => [
                    ['Thành viên Hoàng Gia', 249000, $plan('HG-MEMBER', 'Thẻ thành viên cho khách chơi pickleball, tennis hằng tuần.', 10, 1.2, 7, false, 'monthly', [
                        'Giảm 10% tiền sân khi đặt online',
                        'Giảm 10% nước uống, bóng tại quầy',
                        'Tích điểm x1.2 khi mua hàng tại quầy',
                    ])],
                    ['Hoàng Gia VIP', 1590000, $plan('HG-VIP', 'Gói quý cho đội nhóm giữ lịch cố định tại Phú Mỹ Hưng.', 15, 1.5, 14, true, 'quarterly', [
                        'Giảm 15% tiền sân khi đặt online',
                        'Giảm 15% nước uống, bóng tại quầy',
                        'Tích điểm x1.5 khi mua hàng tại quầy',
                    ])],
                ],
                'members' => [
                    [$khach(10), 'Thành viên Hoàng Gia', 3, 'active'],
                ],
            ],
        ];
    }
}
