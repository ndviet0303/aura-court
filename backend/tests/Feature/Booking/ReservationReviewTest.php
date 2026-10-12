<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_caller_reviews_own_reservation_in_another_organization(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $customer = $fixture['org1']['owner'];
        $reservation = $this->reservation($customer, $fixture['org2']['court'], 'completed', now()->subDays(2), 'RSV-REVIEW-OTHER-ORG');

        $this->actingAs($customer)->postJson('/api/v1/reviews', ['reservation_code' => $reservation->code, 'rating' => 4, 'body' => 'Sân đẹp'])
            ->assertCreated()
            ->assertJsonPath('reservation_id', $reservation->id)
            ->assertJsonPath('court_id', $fixture['org2']['court']->id)
            ->assertJsonPath('rating', 4)
            ->assertJsonPath('user.name', $customer->name);

        $this->assertDatabaseHas('reviews', ['reservation_id' => $reservation->id, 'booking_id' => null, 'user_id' => $customer->id, 'body' => 'Sân đẹp']);
    }

    public function test_booking_code_is_prohibited(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $customer = $fixture['org1']['owner'];
        $reservation = $this->reservation($customer, $fixture['org1']['court1'], 'confirmed', now()->subDays(2), 'RSV-REVIEW-PROHIBITED');

        $this->actingAs($customer)->postJson('/api/v1/reviews', ['reservation_code' => $reservation->code, 'booking_code' => 'BK-OLD', 'rating' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_code']);
        $this->actingAs($customer)->postJson('/api/v1/reviews', ['booking_code' => 'BK-OLD', 'rating' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_code', 'reservation_code']);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_another_users_reservation_is_not_found(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $reservation = $this->reservation($fixture['org1']['owner'], $fixture['org1']['court1'], 'confirmed', now()->subDays(2), 'RSV-REVIEW-NOT-MINE');

        $this->actingAs($fixture['org2']['owner'])->postJson('/api/v1/reviews', ['reservation_code' => $reservation->code, 'rating' => 5])
            ->assertNotFound();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_requires_finished_play_and_a_reviewable_status_once(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $customer = $fixture['org1']['owner'];
        $court = $fixture['org1']['court1'];
        $upcoming = $this->reservation($customer, $court, 'confirmed', now()->addDay(), 'RSV-REVIEW-UPCOMING');
        $held = $this->reservation($customer, $court, 'held', now()->subDays(3), 'RSV-REVIEW-HELD');
        $done = $this->reservation($customer, $court, 'in_use', now()->subDays(2), 'RSV-REVIEW-DONE');

        $this->actingAs($customer)->postJson('/api/v1/reviews', ['reservation_code' => $upcoming->code, 'rating' => 5])
            ->assertUnprocessable()->assertJsonPath('message', 'Chỉ có thể đánh giá sau khi đã chơi xong.');
        $this->actingAs($customer)->postJson('/api/v1/reviews', ['reservation_code' => $held->code, 'rating' => 5])
            ->assertUnprocessable()->assertJsonPath('message', 'Chỉ có thể đánh giá booking đã hoàn tất.');
        $this->actingAs($customer)->postJson('/api/v1/reviews', ['reservation_code' => $done->code, 'rating' => 5])->assertCreated();
        $this->actingAs($customer)->postJson('/api/v1/reviews', ['reservation_code' => $done->code, 'rating' => 3])->assertConflict();

        $this->assertSame(1, Review::query()->count());
    }

    public function test_legacy_mirror_is_reviewable_by_its_mapped_booking_status(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $customer = $fixture['org1']['owner'];
        $court = $fixture['org1']['court1'];
        $noShow = $this->mirror($customer, $court, 'no_show', 'BK-REVIEW-NOSHOW', now()->subDays(3));
        $cancelled = $this->mirror($customer, $court, 'cancelled', 'BK-REVIEW-CANCEL', now()->subDays(2));
        $this->assertSame('legacy', $noShow->status);

        $this->actingAs($customer)->postJson('/api/v1/reviews', ['reservation_code' => $cancelled->code, 'rating' => 5])
            ->assertUnprocessable();
        $this->actingAs($customer)->postJson('/api/v1/reviews', ['reservation_code' => $noShow->code, 'rating' => 2])
            ->assertCreated();

        $bookingId = Booking::query()->where('code', 'BK-REVIEW-NOSHOW')->value('id');
        $this->assertDatabaseHas('reviews', ['reservation_id' => $noShow->id, 'booking_id' => $bookingId, 'rating' => 2]);
    }

    private function reservation(User $user, Court $court, string $status, Carbon $start, string $code): Reservation
    {
        $organizationId = $court->venue->organization_id;
        $reservation = Reservation::create([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'code' => $code,
            'status' => $status,
            'currency' => 'VND',
            'total_amount' => 100000,
            'payment_model' => 'legacy_wallet_v1',
        ]);
        $reservation->slots()->create([
            'organization_id' => $organizationId,
            'court_id' => $court->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'unit_price' => 100000,
            'status' => $status === 'held' ? 'held' : 'confirmed',
        ]);

        return $reservation;
    }

    private function mirror(User $user, Court $court, string $status, string $code, Carbon $start): Reservation
    {
        $booking = Booking::create(['code' => $code, 'user_id' => $user->id, 'court_id' => $court->id, 'total_amount' => 100000, 'status' => $status]);
        $booking->slots()->create(['court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'unit_price' => 100000, 'status' => 'confirmed']);

        return app(LegacyBookingBackfillService::class)->backfillBooking($booking)->reservation()->withoutGlobalScope('tenant_isolation')->firstOrFail();
    }
}
