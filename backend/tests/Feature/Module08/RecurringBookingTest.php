<?php

namespace Tests\Feature\Module08;

use App\Models\Court;
use App\Models\PromotionProgram;
use App\Models\Reservation;
use App\Models\User;
use App\Models\VenueBookingPolicy;
use App\Models\VoucherCode;
use App\Models\VoucherUse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BooksCourts;
use Tests\TestCase;

/** FR-136..FR-138, BR-168..BR-175: each occurrence is checked and booked as one ordinary booking. */
class RecurringBookingTest extends TestCase
{
    use BooksCourts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 09:00:00');
    }

    public function test_fr_137_br_169_br_172_each_occurrence_is_checked_on_its_own_and_nothing_is_held(): void
    {
        $court = $this->payableCourt();
        $this->checkout($this->customer(), $court, '2026-11-10 18:00', '2026-11-10 19:00', 100000)->assertCreated();

        $rows = $this->check($this->customer(), $court, [
            ['2026-11-03 18:00', '2026-11-03 20:00'],
            ['2026-11-05 19:00', '2026-11-05 21:00'],
            ['2026-11-04 18:15', '2026-11-04 19:00'],
            ['2026-11-06 21:30', '2026-11-06 23:00'],
            ['2026-11-10 18:00', '2026-11-10 19:00'],
        ])->assertOk()->json('data');

        // BR-170: rent per occurrence, from its own day and time.
        $this->assertSame([true, true], [$rows[0]['ok'], $rows[1]['ok']]);
        $this->assertSame(200000, $rows[0]['rental']);
        $this->assertSame(200000, $rows[1]['rental']);
        // BR-168, BR-169: off-grid, outside service hours and an occupied time are refused with a reason, not moved.
        foreach ([2, 3, 4] as $index) {
            $this->assertFalse($rows[$index]['ok']);
            $this->assertNotEmpty($rows[$index]['refusal']['message']);
        }
        $this->assertSame('2026-11-10 18:00', $rows[4]['starts_at']);
        $this->assertSame(1, Reservation::count());
        $this->assertSame(0, VoucherUse::count());
    }

    public function test_fr_135_br_169_check_applies_the_venue_booking_rules_the_checkout_applies(): void
    {
        $court = $this->payableCourt();
        VenueBookingPolicy::create([
            'venue_id' => $court->venue_id, 'version' => 2, 'effective_at' => now()->subDay(),
            'settings' => ['hold_ttl_minutes' => 30, 'booking' => ['max_days_ahead' => 30, 'max_duration_minutes' => 120]],
        ]);

        $rows = $this->check($this->customer(), $court, [
            ['2026-11-02 08:00', '2026-11-02 09:00'],
            ['2026-11-03 18:00', '2026-11-03 20:00'],
            ['2026-11-04 18:00', '2026-11-04 21:00'],
            ['2026-12-15 18:00', '2026-12-15 19:00'],
        ])->assertOk()->json('data');

        $this->assertSame('Không thể đặt giờ đã qua.', $rows[0]['refusal']['message']);
        $this->assertTrue($rows[1]['ok']);
        $this->assertSame('Tổng thời lượng đặt sân vượt quá mức tối đa cho phép của sân.', $rows[2]['refusal']['message']);
        $this->assertSame('Khung giờ đã chọn vượt quá thời hạn đặt trước cho phép của sân.', $rows[3]['refusal']['message']);
    }

    public function test_fr_138_br_174_each_occurrence_becomes_its_own_booking_and_payment(): void
    {
        $court = $this->payableCourt();
        $user = $this->customer();
        $this->checkout($this->customer(), $court, '2026-11-10 18:00', '2026-11-10 19:00', 100000)->assertCreated();

        $rows = $this->submit($user, $court, [
            ['2026-11-03 18:00', '2026-11-03 20:00', 200000],
            ['2026-11-05 18:00', '2026-11-05 19:00', 100000],
            ['2026-11-10 18:00', '2026-11-10 19:00', 100000],
        ])->assertCreated()->json('data');

        $this->assertTrue($rows[0]['ok']);
        $this->assertTrue($rows[1]['ok']);
        $this->assertFalse($rows[2]['ok'], 'An occupied occurrence is refused; the others are still booked.');
        [$first, $second] = [$rows[0]['reservation'], $rows[1]['reservation']];
        $this->assertNotSame($first['code'], $second['code']);
        $this->assertSame(['held', 'held'], [$first['status'], $second['status']]);
        $this->assertSame(200000, $first['total_amount']);
        // Each booking has the ordinary ten-minute payment deadline (BR-79), not a series deadline.
        $this->assertSame(now()->addMinutes(10)->getTimestamp(), Carbon::parse($first['hold_expires_at'])->getTimestamp());
        $this->assertSame(2, Reservation::where('user_id', $user->id)->count());

        // Paying one booking confirms only that booking; cancelling another leaves it alone.
        $this->transfer($court, $this->paymentCode($first['code']), 200000)->assertSuccessful();
        $this->command($user, "/api/reservations/{$second['code']}/cancel-payment")->assertOk();
        $this->assertSame('confirmed', Reservation::where('code', $first['code'])->value('status'));
        $this->assertSame('cancelled', Reservation::where('code', $second['code'])->value('status'));
    }

    public function test_br_171_br_173_an_amount_above_what_was_shown_is_not_booked(): void
    {
        $court = $this->payableCourt();

        $rows = $this->submit($this->customer(), $court, [['2026-11-03 18:00', '2026-11-03 19:00', 50000]])->assertCreated()->json('data');

        $this->assertFalse($rows[0]['ok']);
        $this->assertSame('PRICE_CHANGED', $rows[0]['refusal']['code']);
        $this->assertSame(100000, $rows[0]['refusal']['total']);
        $this->assertSame(0, Reservation::count());
    }

    public function test_br_170_voucher_applies_per_booking_within_its_use_limit_and_zero_payable_confirms(): void
    {
        $court = $this->payableCourt();
        $program = $this->publicVoucher($court, now()->addMonth(), ['max_uses' => 1]);
        $user = $this->customer();

        $rows = $this->check($user, $court, [['2026-11-03 18:00', '2026-11-03 19:00'], ['2026-11-05 18:00', '2026-11-05 19:00']])->json('data');
        $this->assertSame([$program->id], array_column($rows[0]['vouchers'], 'id'));
        $this->assertSame(80000, $rows[0]['vouchers'][0]['total']);

        $rows = $this->submit($user, $court, [
            ['2026-11-03 18:00', '2026-11-03 19:00', 80000, $program->id],
            ['2026-11-05 18:00', '2026-11-05 19:00', 80000, $program->id],
        ])->json('data');
        $this->assertSame(80000, $rows[0]['reservation']['total_amount']);
        $this->assertSame('VOUCHER_INVALID', $rows[1]['refusal']['code'], 'The one use is held by the first booking.');

        $free = $this->publicVoucher($court, now()->addMonth(), ['name' => 'Miễn phí', 'amount' => 100000]);
        $rows = $this->submit($user, $court, [['2026-11-06 18:00', '2026-11-06 19:00', 0, $free->id]])->json('data');
        $this->assertSame('confirmed', $rows[0]['reservation']['status']);
    }

    public function test_uc_8_6_e3_a_single_use_code_on_several_occurrences_is_a_conflict_at_check(): void
    {
        $court = $this->payableCourt();
        $program = $this->publicVoucher($court, now()->addMonth(), ['type' => PromotionProgram::TYPE_PRIVATE, 'code_count' => 1, 'max_uses' => null]);
        VoucherCode::create(['program_id' => $program->id, 'code' => 'RIENG1']);
        $check = fn (array $codes) => $this->actingAs($this->customer())->postJson('/api/recurring-bookings/check', [
            'court_id' => $court->id,
            'occurrences' => array_map(fn (int $day, ?string $code): array => [
                'starts_at' => "2026-11-0{$day} 18:00", 'ends_at' => "2026-11-0{$day} 19:00", 'voucher_code' => $code,
            ], array_keys($codes), $codes),
        ])->assertOk()->json('data');

        $rows = $check([3 => 'RIENG1', 4 => null, 5 => ' rieng1 ']);

        foreach ([0, 2] as $index) {
            $this->assertNull($rows[$index]['code_voucher'], 'The code is not counted as usable twice.');
            $this->assertSame('VOUCHER_CODE_SHARED', $rows[$index]['code_refusal']['code']);
        }
        $this->assertNull($rows[1]['code_refusal']);
        $this->assertSame(80000, $check([3 => 'RIENG1'])[0]['code_voucher']['total'], 'On one occurrence the code applies.');
        // A refused occurrence cannot be booked, so the code it names is no conflict.
        $this->checkout($this->customer(), $court, '2026-11-04 18:00', '2026-11-04 19:00', 100000)->assertCreated();
        $rows = $check([3 => 'RIENG1', 4 => 'RIENG1']);
        $this->assertFalse($rows[1]['ok']);
        $this->assertSame(80000, $rows[0]['code_voucher']['total']);
        $this->assertSame(0, VoucherUse::count());
    }

    public function test_br_167_br_172_account_and_list_are_checked_before_anything_is_booked(): void
    {
        $court = $this->payableCourt();

        $this->submit($this->customer(['phone' => null]), $court, [['2026-11-03 18:00', '2026-11-03 19:00', 100000]])
            ->assertStatus(422)->assertJsonPath('code', 'PHONE_REQUIRED');
        $this->submit($this->customer(), $court, [])->assertStatus(422)->assertJsonValidationErrors('occurrences');
        $this->check($this->customer(), $court, array_fill(0, 101, ['2026-11-03 18:00', '2026-11-03 19:00']))
            ->assertStatus(422)->assertJsonValidationErrors('occurrences');
        // D8: one code per request, so a request cannot try many codes at once.
        $this->actingAs($this->customer())->postJson('/api/recurring-bookings/check', [
            'court_id' => $court->id,
            'occurrences' => [
                ['starts_at' => '2026-11-03 18:00', 'ends_at' => '2026-11-03 19:00', 'voucher_code' => 'AAA'],
                ['starts_at' => '2026-11-05 18:00', 'ends_at' => '2026-11-05 19:00', 'voucher_code' => 'BBB'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('occurrences');
        $this->assertSame(0, Reservation::count());
    }

    public function test_d2_retirement_releases_unpaid_future_occurrences_and_keeps_paid_ones(): void
    {
        $migration = require database_path('migrations/2026_10_27_000001_retire_recurring_series.php');
        $migration->down();
        $court = $this->payableCourt();
        $user = $this->customer();
        [$paid, $unpaid] = array_map(fn (array $o) => $this->submit($user, $court, [$o])->json('data.0.reservation.code'), [
            ['2026-11-03 18:00', '2026-11-03 19:00', 100000],
            ['2026-12-03 18:00', '2026-12-03 19:00', 100000],
        ]);
        Reservation::query()->whereIn('code', [$paid, $unpaid])->update(['status' => 'confirmed', 'hold_expires_at' => null]);
        $series = DB::table('recurring_series')->insertGetId([
            'organization_id' => $court->venue->organization_id, 'user_id' => $user->id, 'court_id' => $court->id,
            'frequency' => 'monthly', 'starts_at' => '2026-11-03 18:00', 'ends_at' => '2026-11-03 19:00',
            'occurrence_count' => 2, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([$paid, $unpaid] as $i => $code) {
            $reservation = Reservation::query()->where('code', $code)->firstOrFail();
            DB::table('recurring_occurrences')->insert([
                'organization_id' => $reservation->organization_id, 'recurring_series_id' => $series, 'reservation_id' => $reservation->id,
                'sequence' => $i + 1, 'starts_at' => $reservation->slots()->value('starts_at'), 'ends_at' => $reservation->slots()->value('ends_at'),
                'amount' => 100000, 'status' => 'confirmed', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('monthly_invoices')->insert([
            'organization_id' => $court->venue->organization_id, 'recurring_series_id' => $series, 'period_start' => '2026-11-01',
            'period_end' => '2026-11-30', 'due_at' => '2026-10-25', 'amount' => 100000, 'status' => 'paid', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertSame('confirmed', Reservation::query()->where('code', $paid)->value('status'));
        $this->assertSame('cancelled', Reservation::query()->where('code', $unpaid)->value('status'));
        $this->assertFalse(Schema::hasTable('recurring_series'));
    }

    public function test_d2_series_contracts_and_monthly_invoices_are_gone(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->getJson('/api/recurring-series')->assertNotFound();
        $this->actingAs($user)->getJson('/api/monthly-invoices/1')->assertNotFound();
        $this->command($user, '/api/recurring-series/preview')->assertNotFound();
    }

    /** @param  list<array{0: string, 1: string}>  $occurrences */
    private function check(User $user, Court $court, array $occurrences): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/recurring-bookings/check', [
            'court_id' => $court->id,
            'occurrences' => array_map(fn (array $o): array => ['starts_at' => $o[0], 'ends_at' => $o[1]], $occurrences),
        ]);
    }

    /** @param  list<array{0: string, 1: string, 2: int, 3?: int}>  $occurrences */
    private function submit(User $user, Court $court, array $occurrences): TestResponse
    {
        return $this->command($user, '/api/recurring-bookings', [
            'court_id' => $court->id,
            'occurrences' => array_map(fn (array $o): array => array_filter([
                'starts_at' => $o[0],
                'ends_at' => $o[1],
                'expected_total' => $o[2],
                'voucher_program_id' => $o[3] ?? null,
            ], fn ($value): bool => $value !== null), $occurrences),
        ]);
    }
}
