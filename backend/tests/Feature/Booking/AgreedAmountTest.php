<?php

namespace Tests\Feature\Booking;

use App\Models\OwnerPaymentAttempt;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BooksDirectOwnerCourt;
use Tests\TestCase;

/** BR-80: a booking is never created for more than the amount payable the customer was shown. */
class AgreedAmountTest extends TestCase
{
    use BooksDirectOwnerCourt;
    use RefreshDatabase;

    public function test_price_rose_before_submission_is_refused_with_the_new_amounts_and_creates_nothing(): void
    {
        $fixture = $this->directOwnerCourt();
        $start = now()->addDays(2)->setTime(18, 0);
        $quote = $this->quoteFor($fixture['payer'], $fixture['organization'], $fixture['court'], [$start])
            ->assertCreated()
            ->assertJsonPath('quote.total_amount', 200000)
            ->json('quote');
        $this->publishPriceVersion($fixture['table'], [['00:00', '23:59', 220000]], now());

        $this->submitCheckout($fixture['payer'], $fixture['organization'], ['quote_id' => $quote['id'], 'expected_total' => 200000])
            ->assertConflict()
            ->assertJsonPath('code', 'PRICE_CHANGED')
            ->assertJsonPath('rental', 220000)
            ->assertJsonPath('discount', 0)
            ->assertJsonPath('total', 220000);

        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('reservation_slots', 0);
        $this->assertDatabaseCount('reservation_entitlements', 0);
        $this->assertDatabaseCount('owner_payment_attempts', 0);
    }

    public function test_price_fell_before_submission_books_at_the_lower_amount(): void
    {
        $fixture = $this->directOwnerCourt();
        $start = now()->addDays(2)->setTime(18, 0);
        $quote = $this->quoteFor($fixture['payer'], $fixture['organization'], $fixture['court'], [$start])->assertCreated()->json('quote');
        $this->publishPriceVersion($fixture['table'], [['00:00', '23:59', 180000]], now());

        $code = $this->submitCheckout($fixture['payer'], $fixture['organization'], ['quote_id' => $quote['id'], 'expected_total' => 200000])
            ->assertCreated()
            ->assertJsonPath('reservation.total_amount', 180000)
            ->json('reservation.code');

        $this->assertSame(180000, (int) OwnerPaymentAttempt::where('purpose_id', $code)->firstOrFail()->amount);
        $this->assertSame(180000, (int) Reservation::where('code', $code)->firstOrFail()->slots()->sum('unit_price'));
    }

    public function test_amount_not_sent_is_rejected_on_checkout_and_hold(): void
    {
        $fixture = $this->directOwnerCourt();
        $start = now()->addDays(2)->setTime(18, 0);
        $quote = $this->quoteFor($fixture['payer'], $fixture['organization'], $fixture['court'], [$start])->assertCreated()->json('quote');

        $this->submitCheckout($fixture['payer'], $fixture['organization'], ['quote_id' => $quote['id']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expected_total');
        $this->actingAs($fixture['payer'])->withHeaders([
            'X-Organization-ID' => (string) $fixture['organization']->id,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/reservations', ['quote_id' => $quote['id']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expected_total');

        $this->assertDatabaseCount('reservations', 0);
    }
}
