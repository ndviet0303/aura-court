<?php

namespace Tests\Feature\ResaleVenueCredit;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Http\Requests\Tournament\SaveEntryRequest;
use App\Models\Court;
use App\Models\Journal;
use App\Models\OwnerPaymentAttempt;
use App\Models\Reservation;
use App\Models\User;
use App\Models\VenueCreditAccount;
use App\Models\VenueCreditEntry;
use App\Services\Finance\VenueCreditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ResaleVenueCredit\Concerns\SellsSlots;
use Tests\TestCase;

/** venue-credit "Venue credit at booking checkout": credit pays part or all of an ordinary booking. */
class BookingCreditTest extends TestCase
{
    use RefreshDatabase;
    use SellsSlots;

    private Court $court;

    private User $customer;

    private int $organizationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->court = $this->resaleCourt();
        $this->customer = $this->customer();
        $this->organizationId = (int) $this->court->venue->organization_id;
    }

    public function test_partial_credit_holds_the_rest_for_the_qr_and_is_consumed_on_payment(): void
    {
        $this->grant(150000);

        $code = $this->checkout($this->customer, $this->court, '2026-11-05 18:00', '2026-11-05 20:00', 200000, ['venue_credit_amount' => 150000])
            ->assertCreated()->assertJsonPath('reservation.status', 'held')->json('reservation.code');
        $this->assertSame(50000, (int) OwnerPaymentAttempt::query()->where('purpose_id', $code)->value('amount'));
        $this->assertCredit(0, 150000);

        $this->transfer($this->court, $this->paymentCode($code), 50000)->assertOk();

        $this->assertSame('confirmed', $this->reservationStatus($code));
        $this->assertCredit(0, 0);
        $this->assertSame(1, VenueCreditEntry::query()->withoutGlobalScopes()->where('type', 'capture')->count());
        $this->assertTrue(Journal::query()->withoutGlobalScopes()->where('event_type', 'venue_credit.capture')->exists());
    }

    public function test_full_credit_confirms_at_once_without_a_qr(): void
    {
        $this->grant(100000);

        $code = $this->checkout($this->customer, $this->court, '2026-11-05 18:00', '2026-11-05 19:00', 100000, ['venue_credit_amount' => 100000])
            ->assertCreated()->assertJsonPath('reservation.status', 'confirmed')->json('reservation.code');

        $this->assertFalse(OwnerPaymentAttempt::query()->where('purpose_id', $code)->exists());
        $this->assertCredit(0, 0);
        $this->assertSame(1, VenueCreditEntry::query()->withoutGlobalScopes()->where('type', 'capture')->count());
    }

    public function test_a_cancelled_or_expired_hold_returns_its_credit_exactly_once(): void
    {
        $this->grant(300000);
        $cancelled = $this->checkout($this->customer, $this->court, '2026-11-05 18:00', '2026-11-05 20:00', 200000, ['venue_credit_amount' => 150000])->assertCreated()->json('reservation.code');
        $expired = $this->checkout($this->customer, $this->court, '2026-11-06 18:00', '2026-11-06 20:00', 200000, ['venue_credit_amount' => 150000])->assertCreated()->json('reservation.code');
        $this->assertCredit(0, 300000);

        $this->resale($this->customer, "reservations/{$cancelled}/cancel-payment", $this->organizationId)->assertOk();
        $this->resale($this->customer, "reservations/{$cancelled}/cancel-payment", $this->organizationId);
        Carbon::setTestNow(now()->addHour());
        app(ExpirePendingBookings::class)->execute();
        app(ExpirePendingBookings::class)->execute();

        $this->assertSame('cancelled', $this->reservationStatus($cancelled));
        $this->assertSame('expired', $this->reservationStatus($expired));
        $this->assertCredit(300000, 0);
        $this->assertSame(2, VenueCreditEntry::query()->withoutGlobalScopes()->where('type', 'release')->count());
    }

    public function test_two_checkouts_cannot_reserve_the_same_credit(): void
    {
        $this->grant(100000);

        $this->checkout($this->customer, $this->court, '2026-11-05 18:00', '2026-11-05 20:00', 200000, ['venue_credit_amount' => 100000])->assertCreated();
        $this->checkout($this->customer, $this->court, '2026-11-06 18:00', '2026-11-06 20:00', 200000, ['venue_credit_amount' => 100000])->assertStatus(422);

        $this->assertCredit(0, 100000);
        $this->assertSame(1, Reservation::query()->withoutGlobalScopes()->where('user_id', $this->customer->id)->count());
    }

    public function test_credit_cannot_exceed_the_amount_payable_or_be_spent_by_staff(): void
    {
        $this->grant(300000);

        $this->checkout($this->customer, $this->court, '2026-11-05 18:00', '2026-11-05 19:00', 100000, ['venue_credit_amount' => 150000])->assertStatus(422);
        $this->checkout($this->ownerOf($this->court->venue->organization), $this->court, '2026-11-05 18:00', '2026-11-05 19:00', 100000, [
            'customer_id' => $this->customer->id,
            'venue_credit_amount' => 100000,
        ])->assertJsonValidationErrors('venue_credit_amount');

        $this->assertCredit(300000, 0);
    }

    public function test_unsupported_booking_types_refuse_credit_without_reserving_it(): void
    {
        $this->grant(300000);
        $seller = $this->customer();
        $listing = $this->listSlot($this->paidSlot($this->court, $seller), $seller, 80000);

        $this->command($this->customer, '/api/recurring-series', ['court_id' => $this->court->id, 'venue_credit_amount' => 1000])
            ->assertJsonValidationErrors('venue_credit_amount');
        $this->hold($listing, $this->customer, ['venue_credit_amount' => 1000])->assertJsonValidationErrors('venue_credit_amount');
        $this->assertSame(['prohibited'], (new SaveEntryRequest)->rules()['venue_credit_amount']);

        $this->assertCredit(300000, 0);
        $this->assertFalse(VenueCreditEntry::query()->withoutGlobalScopes()->where('type', 'reserve')->exists());
    }

    private function grant(int $amount): void
    {
        app(VenueCreditService::class)->issue($this->organizationId, $this->customer->id, $amount, 'entitlement_transfer', 'test-'.$amount);
    }

    private function assertCredit(int $balance, int $reserved): void
    {
        $account = VenueCreditAccount::query()->withoutGlobalScopes()->where('organization_id', $this->organizationId)->where('user_id', $this->customer->id)->firstOrFail();
        $this->assertSame([$balance, $reserved], [$account->balance, $account->reserved]);
    }

    private function reservationStatus(string $code): string
    {
        return (string) Reservation::query()->withoutGlobalScopes()->where('code', $code)->value('status');
    }
}
