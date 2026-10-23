<?php

namespace Tests\Feature\Module07;

use App\Models\BookingAttendance;
use App\Models\Court;
use App\Models\OutboxMessage;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Module07\Concerns\ServesCustomers;
use Tests\TestCase;

/** FR-115..FR-117, BR-147 (UC-7.10–UC-7.12): one review per completed reservation, kept on the venue (D15). */
class ReviewTest extends TestCase
{
    use RefreshDatabase;
    use ServesCustomers;

    private Court $court;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->court = $this->bookableCourt();
        $this->customer = User::factory()->create(['name' => 'Khách đánh giá']);
    }

    public function test_fr_116_br_147_only_a_checked_out_reservation_of_the_caller_is_reviewed(): void
    {
        $confirmed = $this->booking($this->court, $this->at('2026-11-02 18:00'));
        $confirmed->update(['user_id' => $this->customer->id]);
        $noShow = $this->visit($this->court, $this->customer, $this->at('2026-11-02 20:00'), BookingAttendance::STATUS_NO_SHOW);
        $done = $this->visit($this->court, $this->customer, $this->at('2026-11-03 10:00'));
        $this->travelTo($this->at('2026-11-03 12:00'));

        $this->review($this->customer, $confirmed)->assertUnprocessable()->assertJsonPath('code', 'REVIEW_NOT_COMPLETED');
        $this->review($this->customer, $noShow)->assertUnprocessable()->assertJsonPath('code', 'REVIEW_NOT_COMPLETED');
        $this->review(User::factory()->create(), $done)->assertNotFound();
        $this->review($this->customer, $done, ['rating' => 6])->assertUnprocessable()->assertJsonValidationErrors('rating');
        $this->review($this->customer, $done, ['images' => array_fill(0, 6, '/courts/x.jpg')])->assertUnprocessable()->assertJsonValidationErrors('images');

        $this->review($this->customer, $done)->assertCreated()
            ->assertJsonPath('review.venue_id', $this->court->venue_id)
            ->assertJsonPath('review.court.code', $this->court->code)
            ->assertJsonPath('review.reservation_code', $done->code)
            ->assertJsonPath('review.is_mine', true)
            ->assertJsonPath('review.reply', null);
        $this->review($this->customer, $done)->assertConflict()->assertJsonPath('code', 'REVIEW_EXISTS');
    }

    public function test_br_147_the_review_window_ends_seven_days_after_check_out(): void
    {
        $first = $this->visit($this->court, $this->customer, $this->at('2026-11-03 10:00'));
        $late = $this->visit($this->court, $this->customer, $this->at('2026-11-04 10:00'));

        $this->travelTo($this->at('2026-11-10 10:00'));
        $this->review($this->customer, $first)->assertCreated();
        $this->travelTo($this->at('2026-11-11 10:00:01'));
        $this->review($this->customer, $late)->assertUnprocessable()->assertJsonPath('code', 'REVIEW_WINDOW_CLOSED');
    }

    public function test_br_147_the_author_edits_for_seven_days_deletes_any_time_and_never_reviews_again(): void
    {
        $done = $this->visit($this->court, $this->customer, $this->at('2026-11-03 10:00'));
        $this->travelTo($this->at('2026-11-03 11:00'));
        $id = $this->review($this->customer, $done)->json('review.id');

        $this->actingAs(User::factory()->create())->putJson("/api/reviews/{$id}", ['rating' => 1])->assertForbidden()->assertJsonPath('code', 'REVIEW_NOT_AUTHOR');
        $this->actingAs($this->customer)->putJson("/api/reviews/{$id}", ['rating' => 4, 'body' => 'Sân ổn'])->assertOk()->assertJsonPath('review.rating', 4);
        $this->travelTo($this->at('2026-11-10 11:00:01'));
        $this->actingAs($this->customer)->putJson("/api/reviews/{$id}", ['rating' => 5])->assertUnprocessable()->assertJsonPath('code', 'REVIEW_EDIT_CLOSED');

        $this->actingAs($this->customer)->deleteJson("/api/reviews/{$id}")->assertNoContent();
        $this->assertSoftDeleted('reviews', ['id' => $id]);
        $this->review($this->customer, $done)->assertConflict()->assertJsonPath('code', 'REVIEW_EXISTS');
        $this->actingAs($this->customer)->getJson('/api/me/reservations')->assertOk()
            ->assertJsonPath('data.0.review.id', $id)
            ->assertJsonPath('data.0.attendance.status', BookingAttendance::STATUS_CHECKED_OUT);
    }

    public function test_fr_115_d15_reviews_belong_to_the_venue_and_hidden_ones_are_not_shown(): void
    {
        $sibling = $this->activeCourt($this->court->venue);
        $this->travelTo($this->at('2026-11-03 11:00'));
        $this->review($this->customer, $this->visit($this->court, $this->customer, $this->at('2026-11-03 10:00')), ['rating' => 4])->assertCreated();
        $other = User::factory()->create();
        $this->review($other, $this->visit($sibling, $other, $this->at('2026-11-03 10:30')), ['rating' => 2])->assertCreated();
        Review::where('user_id', $other->id)->update(['is_published' => false]);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/venues/{$this->court->venue_id}/reviews")->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('summary.count', 1)
            ->assertJsonPath('summary.average', 4)
            ->assertJsonPath('data.0.author.name', 'Khách đánh giá');
        $this->getJson("/api/courts/{$this->court->id}")->assertOk()->assertJsonPath('reviews_count', 1);
        $this->getJson("/api/courts/{$this->court->id}/reviews")->assertNotFound();
    }

    public function test_fr_117_only_the_venue_owner_replies_never_edits_and_the_reviewer_is_told(): void
    {
        $this->travelTo($this->at('2026-11-03 11:00'));
        $id = $this->review($this->customer, $this->visit($this->court, $this->customer, $this->at('2026-11-03 10:00')))->json('review.id');
        $owner = $this->ownerOf($this->court->venue->organization);
        $strangers = [$this->ownerOf($this->bookableCourt()->venue->organization), $this->staffOf($this->court->venue), $this->customer];

        foreach ($strangers as $user) {
            $this->actingAs($user)->putJson("/api/reviews/{$id}/reply", ['body' => 'Cảm ơn'])->assertForbidden()->assertJsonPath('code', 'REVIEW_REPLY_FORBIDDEN');
        }
        $this->actingAs($owner)->putJson("/api/reviews/{$id}/reply", ['body' => 'Cảm ơn bạn đã ghé sân'])
            ->assertOk()->assertJsonPath('review.reply.body', 'Cảm ơn bạn đã ghé sân');
        $this->actingAs($owner)->putJson("/api/reviews/{$id}/reply", ['body' => 'Hẹn gặp lại'])->assertOk()->assertJsonPath('review.reply.body', 'Hẹn gặp lại');
        $this->actingAs($owner)->putJson("/api/reviews/{$id}", ['rating' => 5])->assertForbidden()->assertJsonPath('code', 'REVIEW_NOT_AUTHOR');
        $this->actingAs($owner)->deleteJson("/api/reviews/{$id}")->assertForbidden();

        $notices = OutboxMessage::where('event_type', 'review.reply')->get();
        $this->assertCount(1, $notices);
        $this->assertSame($this->customer->id, $notices->first()->payload['user_id']);
        $this->assertSame("/courts/{$this->court->id}#reviews", $notices->first()->payload['action_url']);
    }

    /** @param  array<string, mixed>  $body */
    private function review(User $user, Reservation $reservation, array $body = []): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/reviews', $body + ['reservation_code' => $reservation->code, 'rating' => 5, 'body' => 'Sân đẹp']);
    }
}
