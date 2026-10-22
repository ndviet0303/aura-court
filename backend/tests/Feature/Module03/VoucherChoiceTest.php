<?php

namespace Tests\Feature\Module03;

use App\Models\Court;
use App\Models\Venue;
use App\Models\VoucherUse;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsVenues;
use Tests\Concerns\OffersVouchers;
use Tests\TestCase;

/**
 * What a booking form offers before checkout: the applicable public vouchers with the default first, a private code
 * check, and the discount formula (FR-64..FR-66, BR-61..BR-63, BR-65..BR-67; UC-3.14, UC-3.15). The court costs
 * 100.000 ₫/h until 16:00 and 140.000 ₫/h after; now is Monday 19/10/2026 08:00.
 */
class VoucherChoiceTest extends TestCase
{
    use BuildsVenues;
    use OffersVouchers;
    use RefreshDatabase;

    private Venue $venue;

    private Court $court;

    private Court $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Asia/Ho_Chi_Minh'));
        $this->venue = $this->openVenue();
        [$this->court, $this->other] = [$this->activeCourt($this->venue), $this->activeCourt($this->venue)];
        $this->priceCourtWith([$this->court, $this->other], [['06:00', '16:00', 100000], ['16:00', '22:00', 140000]]);
    }

    public function test_fr_64_br_65_uc_3_14_applicable_public_vouchers_come_largest_discount_first_then_earlier_end_then_earlier_creation(): void
    {
        $percent = $this->publicProgram($this->court, ['percent_bp' => 1000, 'max_discount' => 30000]);
        $earlier = $this->publicProgram($this->court, ['amount' => 19000, 'ends_at' => now()->addDays(3)]);
        $largest = $this->publicProgram($this->court, ['amount' => 25000]);
        $later = $this->publicProgram($this->court, ['amount' => 19000, 'ends_at' => now()->addDays(3)]);

        // 15:30–17:00 costs 190.000: 10% is 19.000, the same as the fixed ones, so ties fall to end, then creation.
        $this->vouchers('2026-10-26 15:30', '2026-10-26 17:00')
            ->assertOk()
            ->assertJsonPath('rental', 190000)
            ->assertJsonPath('default_program_id', $largest->id)
            ->assertJsonPath('data.*.id', [$largest->id, $earlier->id, $later->id, $percent->id])
            ->assertJsonPath('data.0.discount', 25000)
            ->assertJsonPath('data.0.total', 165000)
            ->assertJsonPath('data.3.discount', 19000);
    }

    public function test_br_61_br_62_br_63_only_running_public_vouchers_that_fit_the_booking_are_listed(): void
    {
        $listed = $this->publicProgram($this->court);
        $this->publicProgram($this->court, ['stopped_at' => now()]);
        $this->publicProgram($this->court, ['starts_at' => now()->addHour()]);
        $this->publicProgram($this->court, ['starts_at' => now()->subWeek(), 'ends_at' => now()]);
        $this->publicProgram($this->other);
        $this->publicProgram($this->court, ['min_rental' => 200000]);
        $this->privateProgram($this->court, ['AAAAAAAAAA']);

        $this->vouchers('2026-10-26 15:30', '2026-10-26 17:00')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$listed->id]);
        $this->vouchers('2026-10-26 15:30', '2026-10-26 17:00', $this->other)
            ->assertJsonCount(1, 'data');
    }

    public function test_br_60_br_63_an_empty_minimum_is_no_condition_and_a_set_one_compares_the_rental_before_discount(): void
    {
        $none = $this->publicProgram($this->court, ['amount' => 10000]);
        $exact = $this->publicProgram($this->court, ['amount' => 10000, 'min_rental' => 190000]);
        $this->publicProgram($this->court, ['amount' => 10000, 'min_rental' => 190001]);

        $this->vouchers('2026-10-26 15:30', '2026-10-26 17:00')->assertJsonPath('data.*.id', [$none->id, $exact->id]);
    }

    public function test_br_63_the_window_is_judged_at_confirmation_not_at_play_time(): void
    {
        // Ends 12:00 on 25/10; the booking plays on 26/10, after the end.
        $this->travelTo(CarbonImmutable::parse('2026-10-25 11:59', 'Asia/Ho_Chi_Minh'));
        $program = $this->publicProgram($this->court, ['ends_at' => CarbonImmutable::parse('2026-10-25 12:00', 'Asia/Ho_Chi_Minh')]);

        $this->vouchers('2026-10-26 10:00', '2026-10-26 11:00')->assertJsonPath('data.*.id', [$program->id]);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00', 'Asia/Ho_Chi_Minh'));
        $this->vouchers('2026-10-26 10:00', '2026-10-26 11:00')->assertJsonPath('data', []);
    }

    public function test_br_66_fr_66_discount_formula_matches_the_report_examples(): void
    {
        // 300.000 at 20% capped at 50.000: the cap wins.
        $capped = $this->publicProgram($this->court, ['percent_bp' => 2000, 'max_discount' => 50000]);
        $this->assertAmounts($this->vouchers('2026-10-26 06:00', '2026-10-26 09:00'), $capped->id, 300000, 50000, 250000);
        $capped->update(['stopped_at' => now()]);

        // 190.000 at 10% capped at 30.000: the percentage is under the cap.
        $underCap = $this->publicProgram($this->court, ['percent_bp' => 1000, 'max_discount' => 30000]);
        $this->assertAmounts($this->vouchers('2026-10-26 15:30', '2026-10-26 17:00'), $underCap->id, 190000, 19000, 171000);
        $underCap->update(['stopped_at' => now()]);

        // 40.000 with a fixed 50.000: the whole rental, never a negative total.
        $fixed = $this->publicProgram($this->court, ['amount' => 50000]);
        $this->assertAmounts($this->vouchers('2026-10-26 10:00', '2026-10-26 10:24'), $fixed->id, 40000, 40000, 0);
        $fixed->update(['stopped_at' => now()]);

        // 250.000 against a 300.000 minimum: not applicable.
        $this->publicProgram($this->court, ['amount' => 30000, 'min_rental' => 300000]);
        $this->vouchers('2026-10-26 06:00', '2026-10-26 08:30')->assertJsonPath('rental', 250000)->assertJsonPath('data', []);

        // An uncapped 100% is bounded by the rental alone.
        $all = $this->publicProgram($this->other, ['percent_bp' => 10000]);
        $this->assertAmounts($this->vouchers('2026-10-26 10:00', '2026-10-26 11:00', $this->other), $all->id, 100000, 100000, 0);
    }

    public function test_br_67_the_discount_drops_its_fraction(): void
    {
        $program = $this->publicProgram($this->court, ['percent_bp' => 1250]);

        // 7 minutes at 100.000/h = 11.666 (floored); 12,5% of it = 1.458,25 → 1.458.
        $this->assertAmounts($this->vouchers('2026-10-26 10:00', '2026-10-26 10:07'), $program->id, 11666, 1458, 10208);
    }

    public function test_fr_65_br_62_uc_3_15_a_private_code_is_checked_in_any_case_and_holds_nothing(): void
    {
        $program = $this->privateProgram($this->court, ['ABCDEFGHJK'], ['amount' => 30000]);

        $this->check(' abcdefghjk ', '2026-10-26 15:30', '2026-10-26 17:00')
            ->assertOk()
            ->assertJsonPath('data.id', $program->id)
            ->assertJsonPath('data.code', 'ABCDEFGHJK')
            ->assertJsonPath('data.type', 'private')
            ->assertJsonPath('data.rental', 190000)
            ->assertJsonPath('data.discount', 30000)
            ->assertJsonPath('data.total', 160000);

        $this->assertSame(0, VoucherUse::count());
    }

    public function test_fr_65_an_invalid_code_says_why_with_the_amounts_without_it(): void
    {
        $this->privateProgram($this->court, ['MINIMUMXXX'], ['amount' => 30000, 'min_rental' => 300000]);
        $this->privateProgram($this->court, ['EXPIREDXXX'], ['starts_at' => now()->subWeek(), 'ends_at' => now()->subMinute()]);
        $this->privateProgram($this->court, ['STOPPEDXXX'], ['stopped_at' => now()]);
        $this->privateProgram($this->other, ['OTHERCOURT']);
        $this->privateProgram($this->activeCourt($this->openVenue()), ['OTHERVENUE']);
        $this->publicProgram($this->court);

        foreach ([
            'MINIMUMXXX' => 'minimum_not_met',
            'EXPIREDXXX' => 'expired',
            'STOPPEDXXX' => 'stopped',
            'OTHERCOURT' => 'court_not_covered',
            'OTHERVENUE' => 'not_found',
            'NOSUCHCODE' => 'not_found',
        ] as $code => $reason) {
            $this->check($code, '2026-10-26 15:30', '2026-10-26 17:00')
                ->assertUnprocessable()
                ->assertJsonPath('code', 'VOUCHER_INVALID')
                ->assertJsonPath('reason', $reason)
                ->assertJsonPath('rental', 190000)
                ->assertJsonPath('total', 190000);
        }
    }

    private function assertAmounts(TestResponse $response, int $programId, int $rental, int $discount, int $total): void
    {
        $response->assertOk()
            ->assertJsonPath('rental', $rental)
            ->assertJsonPath('data.0.id', $programId)
            ->assertJsonPath('data.0.rental', $rental)
            ->assertJsonPath('data.0.discount', $discount)
            ->assertJsonPath('data.0.total', $total);
    }

    private function vouchers(string $start, string $end, ?Court $court = null): TestResponse
    {
        return $this->getJson("/api/venues/{$this->venue->id}/vouchers?".http_build_query($this->interval($court ?? $this->court, $start, $end)));
    }

    private function check(string $code, string $start, string $end): TestResponse
    {
        return $this->getJson("/api/venues/{$this->venue->id}/vouchers/check?".http_build_query([...$this->interval($this->court, $start, $end), 'code' => $code]));
    }

    /** @return array{court_id: int, starts_at: string, ends_at: string} */
    private function interval(Court $court, string $start, string $end): array
    {
        return ['court_id' => $court->id, 'starts_at' => $start.':00+07:00', 'ends_at' => $end.':00+07:00'];
    }
}
