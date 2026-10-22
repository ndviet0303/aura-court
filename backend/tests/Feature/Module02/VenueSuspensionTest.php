<?php

namespace Tests\Feature\Module02;

use App\Models\AffectedReservationCase;
use App\Models\AuditLog;
use App\Models\Court;
use App\Models\OutboxMessage;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSuspension;
use App\Services\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\Feature\Module02\Concerns\SeedsBookings;
use Tests\TestCase;

/** FR-45, FR-46, BR-42, BR-47 (suspension part); design D13. UC-2.26, UC-2.27. */
class VenueSuspensionTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;
    use SeedsBookings;

    private const REASON = 'Vi phạm quy định an toàn';

    private function suspend(Venue $venue, array $body = [])
    {
        return $this->actingAs($this->platformAdmin())->postJson("/api/admin/venues/{$venue->id}/suspensions", $body + ['reason' => self::REASON]);
    }

    private function partnerUrl(Venue $venue, string $suffix = ''): string
    {
        return "/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}{$suffix}";
    }

    public function test_br_42_suspension_reason_length(): void
    {
        $venue = $this->openVenue();

        $this->suspend($venue, ['reason' => '  '.str_repeat('a', 9).'  '])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->suspend($venue, ['reason' => str_repeat('a', 1001)])->assertStatus(422);
        $this->suspend($venue, ['reason' => str_repeat('a', 10)])->assertCreated();
        $this->assertSame(1, VenueSuspension::count());
    }

    public function test_fr_45_already_suspended_is_409(): void
    {
        $venue = $this->openVenue();
        $this->suspend($venue)->assertCreated();

        $this->suspend($venue)->assertStatus(409);
        $this->assertSame(1, VenueSuspension::count());
    }

    public function test_fr_45_only_platform_admins_suspend(): void
    {
        $venue = $this->openVenue();

        $this->actingAs($this->ownerOf($venue->organization))->postJson("/api/admin/venues/{$venue->id}/suspensions", ['reason' => self::REASON])->assertForbidden();
        $this->assertSame(0, VenueSuspension::count());
    }

    public function test_fr_45_suspension_keeps_statuses_and_bookings(): void
    {
        $court = $this->bookableCourt();
        $venue = $court->venue;
        $paused = $this->activeCourt($venue, ['status' => Court::STATUS_PAUSED]);
        $booking = $this->booking($court, now()->addHour()->startOfHour());
        $owner = $this->ownerOf($venue->organization);
        $staff = $this->staffOf($venue);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->getJson("/api/admin/venues/{$venue->id}/suspensions/preview")->assertOk()
            ->assertJsonPath('data.court_count', 2)
            ->assertJsonPath('data.bookings.0.code', $booking->code)
            ->assertJsonMissingPath('data.bookings.0.customer_phone');
        $this->actingAs($admin)->postJson("/api/admin/venues/{$venue->id}/suspensions", ['reason' => self::REASON])
            ->assertStatus(409)->assertJsonPath('code', 'AFFECTED_BOOKINGS_UNACKNOWLEDGED')
            ->assertJsonMissingPath('affected.bookings.0.customer_phone');
        $this->assertSame(0, VenueSuspension::count());

        $this->actingAs($admin)->postJson("/api/admin/venues/{$venue->id}/suspensions", ['reason' => self::REASON, 'acknowledge_affected' => true])
            ->assertCreated()->assertJsonPath('data.suspended_by.id', $admin->id);

        $this->assertSame(Venue::STATUS_ACTIVE, $venue->fresh()->status);
        $this->assertSame([Court::STATUS_ACTIVE, Court::STATUS_PAUSED], [$court->fresh()->status, $paused->fresh()->status]);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $case = AffectedReservationCase::sole();
        $this->assertSame(['venue_suspension', VenueSuspension::sole()->id, 'pending'], [$case->source_type, $case->source_id, $case->status]);
        $this->assertTrue(AuditLog::query()->where('action', 'venue.suspended')->exists());
        $recipients = OutboxMessage::query()->where('event_type', 'venue.suspended')->get()->map(fn ($m) => $m->payload['user_id'])->all();
        $this->assertSame([$owner->id], $recipients, 'only active owners are told');
        $this->assertNotContains($staff->id, $recipients);
        $this->assertStringContainsString('/suspensions/', OutboxMessage::query()->where('event_type', 'venue.suspended')->sole()->payload['action_url']);
    }

    public function test_br_47_a_suspended_venue_leaves_the_public_court_list(): void
    {
        $court = $this->bookableCourt();
        $this->getJson('/api/courts')->assertOk()->assertJsonFragment(['id' => $court->id]);

        $this->suspend($court->venue)->assertCreated();

        $this->assertNotContains($court->id, $this->getJson('/api/courts')->assertOk()->json('*.id'));
    }

    public function test_br_42_a_quote_taken_before_the_suspension_cannot_become_a_booking(): void
    {
        $court = $this->bookableCourt();
        $customer = User::factory()->create();
        $slot = ['court_id' => $court->id, 'starts_at' => $this->tomorrowAt(10)->toIso8601String(), 'ends_at' => $this->tomorrowAt(11)->toIso8601String()];
        $token = $this->actingAs($customer)->withHeader('Idempotency-Key', 'quote-1')->postJson('/api/price-quotes', $slot)->assertCreated()->json('quote.quote_token');

        $this->suspend($court->venue)->assertCreated();
        // Each real request starts without a tenant; the singleton would otherwise carry the quote request's organization.
        $this->app->forgetInstance(TenantContext::class);

        $this->actingAs($customer)->withHeader('Idempotency-Key', 'book-1')->postJson('/api/reservations', ['quote_token' => $token, 'expected_total' => 100000])->assertStatus(409);
        $this->actingAs($customer)->withHeader('Idempotency-Key', 'book-2')->postJson('/api/reservations', $slot + ['expected_total' => 100000])->assertNotFound();
        $this->assertSame(0, Reservation::query()->where('court_id', $court->id)->count());
    }

    public function test_br_47_owner_during_suspension_edits_but_cannot_open_or_resume(): void
    {
        $court = $this->bookableCourt();
        $venue = $court->venue;
        $owner = $this->ownerOf($venue->organization);
        $staff = $this->staffOf($venue);
        $this->actingAs($staff)->postJson("/api/admin/courts/{$court->id}/pause", ['reason' => 'Sửa lưới sân bị rách', 'kind' => 'maintenance'])->assertOk();
        $this->suspend($venue)->assertCreated();

        $days = [];
        foreach (range(1, 7) as $day) {
            $days[(string) $day] = ['closed' => false, 'intervals' => [['start' => '06:00', 'end' => '22:00']]];
        }
        $this->actingAs($owner)->putJson($this->partnerUrl($venue, '/operating-schedule'), ['timezone' => 'Asia/Ho_Chi_Minh', 'weekly_hours' => $days])->assertOk();

        $this->actingAs($owner)->postJson("/api/admin/courts/{$court->id}/resume")->assertStatus(422)->assertJsonFragment(['code' => 'venue_suspended']);
        $this->actingAs($staff)->postJson("/api/admin/courts/{$court->id}/resume")->assertStatus(422)->assertJsonFragment(['code' => 'venue_suspended']);
        $this->assertSame(Court::STATUS_PAUSED, $court->fresh()->status);
    }

    public function test_fr_46_no_route_lifts_a_suspension_directly(): void
    {
        $venue = $this->openVenue();
        $this->suspend($venue)->assertCreated();
        $suspension = VenueSuspension::sole();
        $owner = $this->ownerOf($venue->organization);

        $this->assertContains($this->actingAs($owner)->deleteJson($this->partnerUrl($venue, "/suspensions/{$suspension->id}"))->status(), [404, 405]);
        $this->assertContains($this->actingAs($this->platformAdmin())->deleteJson("/api/admin/venues/{$venue->id}/suspensions")->status(), [404, 405]);
        $this->assertSame(404, $this->actingAs($this->platformAdmin())->postJson("/api/admin/venues/{$venue->id}/lifecycle", ['status' => 'active'])->status());
        $this->assertNull($suspension->fresh()->lifted_at);
    }

    public function test_fr_46_owner_reads_the_decisions_and_others_are_refused(): void
    {
        $venue = $this->openVenue();
        $this->suspend($venue)->assertCreated();
        $suspension = VenueSuspension::sole();
        $owner = $this->ownerOf($venue->organization);

        $this->actingAs($owner)->getJson($this->partnerUrl($venue, '/suspensions'))->assertOk()
            ->assertJsonPath('data.0.reason', self::REASON)->assertJsonPath('data.0.is_active', true);
        $this->actingAs($owner)->getJson($this->partnerUrl($venue, "/suspensions/{$suspension->id}"))->assertOk()
            ->assertJsonPath('data.remediations', [])->assertJsonPath('data.conclusion', null);

        $this->actingAs($this->staffOf($venue))->getJson($this->partnerUrl($venue, '/suspensions'))->assertForbidden();
        $this->actingAs($this->ownerOf($this->openVenue()->organization))->getJson($this->partnerUrl($venue, '/suspensions'))->assertForbidden();
        $this->actingAs($this->platformAdmin())->getJson($this->partnerUrl($venue, '/suspensions'))->assertForbidden();
    }
}
