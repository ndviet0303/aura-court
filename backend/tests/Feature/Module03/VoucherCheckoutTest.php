<?php

namespace Tests\Feature\Module03;

use App\Models\Court;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Models\VoucherUse;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\Concerns\OffersVouchers;
use Tests\TestCase;

/**
 * A voucher at checkout and afterwards: one per booking, rechecked and recorded only when the booking is confirmed,
 * never given back, and tracked by the owner (FR-67, FR-68, BR-64, BR-68..BR-71; UC-3.16, UC-3.17). Checkout takes
 * the wallet path, which confirms at once. 15:30–17:00 on 26/10 costs 190.000; now is Monday 19/10/2026 08:00.
 */
class VoucherCheckoutTest extends TestCase
{
    use BuildsVenues;
    use OffersVouchers;
    use RefreshDatabase;

    private const START = '2026-10-26 15:30';

    private const END = '2026-10-26 17:00';

    private Venue $venue;

    private Court $court;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Asia/Ho_Chi_Minh'));
        $this->venue = $this->openVenue();
        $this->court = $this->activeCourt($this->venue)->load('venue');
        $this->priceCourtWith($this->court, [['06:00', '16:00', 100000], ['16:00', '22:00', 140000]]);
        $this->customer = $this->customer($this->court);
    }

    public function test_fr_67_br_69_uc_3_16_a_confirmed_booking_pays_the_rental_less_the_discount_and_records_one_use(): void
    {
        $program = $this->publicProgram($this->court, ['percent_bp' => 1000, 'max_discount' => 30000, 'max_uses' => 3]);

        $this->checkout($this->customer, $this->court, self::START, self::END, 171000, ['voucher_program_id' => $program->id])->assertCreated();

        $reservation = Reservation::sole();
        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame(171000, (int) $reservation->total_amount);
        $use = VoucherUse::sole();
        $this->assertSame([VoucherUse::STATUS_USED, 19000, (int) $reservation->id], [$use->status, $use->discount, (int) $use->reservation_id]);
        $this->assertNotNull($use->used_at);
    }

    public function test_fr_67_br_69_a_private_code_is_used_once_and_a_second_booking_is_refused_without_a_use(): void
    {
        $this->privateProgram($this->court, ['ABCDEFGHJK'], ['amount' => 30000]);

        $this->checkout($this->customer, $this->court, self::START, self::END, 160000, ['voucher_code' => 'abcdefghjk'])->assertCreated();
        $this->checkout($this->customer($this->court), $this->court, '2026-10-27 10:00', '2026-10-27 11:00', 70000, ['voucher_code' => 'ABCDEFGHJK'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VOUCHER_INVALID')
            ->assertJsonPath('reason', 'used')
            ->assertJsonPath('total', 100000);

        $this->assertSame(1, Reservation::count());
        $this->assertSame(1, VoucherUse::count());
    }

    public function test_fr_67_br_68_br_69_the_last_public_use_goes_to_one_booking_and_the_next_must_review_its_amount(): void
    {
        $program = $this->publicProgram($this->court, ['amount' => 20000, 'max_uses' => 1]);

        $this->checkout($this->customer, $this->court, self::START, self::END, 170000, ['voucher_program_id' => $program->id])->assertCreated();
        $this->checkout($this->customer($this->court), $this->court, '2026-10-27 10:00', '2026-10-27 11:00', 80000, ['voucher_program_id' => $program->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VOUCHER_INVALID')
            ->assertJsonPath('reason', 'exhausted')
            ->assertJsonPath('rental', 100000)
            ->assertJsonPath('total', 100000);

        $this->assertSame(1, Reservation::count());
        $this->assertSame(1, VoucherUse::count());
    }

    public function test_br_64_a_booking_takes_one_voucher_at_most(): void
    {
        $public = $this->publicProgram($this->court);
        $this->privateProgram($this->court, ['ABCDEFGHJK']);

        $this->checkout($this->customer, $this->court, self::START, self::END, 190000, ['voucher_program_id' => $public->id, 'voucher_code' => 'ABCDEFGHJK'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voucher_program_id']);
        $this->assertSame(0, Reservation::count());
    }

    public function test_br_71_a_stopped_program_applies_to_no_new_booking_even_an_unused_code(): void
    {
        $public = $this->publicProgram($this->court, ['stopped_at' => now()]);
        $this->privateProgram($this->court, ['ABCDEFGHJK'], ['stopped_at' => now()]);

        foreach ([['voucher_program_id' => $public->id], ['voucher_code' => 'ABCDEFGHJK']] as $voucher) {
            $this->checkout($this->customer, $this->court, self::START, self::END, 190000, $voucher)
                ->assertUnprocessable()
                ->assertJsonPath('reason', 'stopped');
        }
        $this->assertSame(0, Reservation::count());
    }

    public function test_br_70_br_71_fr_68_uc_3_17_a_used_voucher_stays_used_through_program_changes_and_cancellation(): void
    {
        $program = $this->publicProgram($this->court, ['percent_bp' => 1000, 'max_uses' => 5]);
        $this->checkout($this->customer, $this->court, self::START, self::END, 171000, ['voucher_program_id' => $program->id])->assertCreated();
        $reservation = Reservation::sole();
        $owner = $this->ownerOf($this->venue->organization);

        $this->actingAs($owner)->patchJson("/api/admin/promotion-programs/{$program->id}", ['percent' => 50])->assertOk();
        $this->actingAs($owner)->postJson("/api/admin/promotion-programs/{$program->id}/stop")->assertOk();
        app(ReservationService::class)->release($reservation, 'cancelled');

        $this->assertSame([VoucherUse::STATUS_USED, 19000], [VoucherUse::sole()->status, VoucherUse::sole()->discount]);
        $this->assertSame(171000, (int) $reservation->fresh()->total_amount);
        $this->actingAs($owner)->getJson("/api/admin/promotion-programs/{$program->id}")
            ->assertOk()
            ->assertJsonPath('data.usage', ['held' => 0, 'used' => 1, 'remaining' => 4]);
        $this->actingAs($owner)->getJson("/api/admin/promotion-programs/{$program->id}/usage")
            ->assertOk()
            ->assertJsonPath('data.0.status', 'used')
            ->assertJsonPath('data.0.discount', 19000)
            ->assertJsonPath('data.0.reservation.id', $reservation->id)
            ->assertJsonPath('data.0.reservation.status', 'cancelled')
            ->assertJsonPath('data.0.reservation.court', ['id' => $this->court->id, 'name' => $this->court->code])
            ->assertJsonPath('held', []);
    }

    public function test_fr_68_private_codes_show_which_are_used(): void
    {
        $program = $this->privateProgram($this->court, ['ABCDEFGHJK', 'BCDEFGHJKL'], ['amount' => 30000]);
        $this->checkout($this->customer, $this->court, self::START, self::END, 160000, ['voucher_code' => 'ABCDEFGHJK'])->assertCreated();

        $codes = collect($this->actingAs($this->ownerOf($this->venue->organization))->getJson("/api/admin/promotion-programs/{$program->id}/codes")
            ->assertOk()->json('data.codes'))->pluck('state', 'code')->all();
        $this->assertSame(['ABCDEFGHJK' => 'used', 'BCDEFGHJKL' => 'available'], $codes);
    }
}
