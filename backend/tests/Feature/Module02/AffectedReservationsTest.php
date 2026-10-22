<?php

namespace Tests\Feature\Module02;

use App\Models\AffectedReservationCase;
use App\Models\OperationalStop;
use App\Models\VenueScheduleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\Feature\Module02\Concerns\SeedsBookings;
use Tests\TestCase;

/** FR-28, FR-32, FR-36, FR-37, FR-40, FR-41, BR-38, BR-39 (list part); design D7. UC-2.5, UC-2.10, UC-2.14, UC-2.15, UC-2.20, UC-2.21, UC-2.22. */
class AffectedReservationsTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;
    use SeedsBookings;

    private function pause(int $courtId, array $data, string $suffix = ''): array
    {
        return ['url' => "/api/admin/courts/{$courtId}/pause{$suffix}", 'body' => $data + ['reason' => 'Sự cố mất điện cần sửa', 'kind' => 'maintenance']];
    }

    public function test_br_38_overlap_boundaries_and_statuses(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $endsAtStart = $this->booking($court, $this->tomorrowAt(8), 2);              // 08:00–10:00 ends at the stop start
        $overlapping = $this->booking($court, $this->tomorrowAt(10), 2, 'held');      // held before its deadline
        $later = $this->booking($court, $this->tomorrowAt(15));                       // after the expected end: still blocked
        $checkedIn = $this->booking($court, $this->tomorrowAt(17), metadata: ['operational_status' => 'in_use']);
        $cancelled = $this->booking($court, $this->tomorrowAt(12), status: 'cancelled');
        $expired = $this->booking($court, $this->tomorrowAt(13), status: 'held');
        $expired->forceFill(['hold_expires_at' => now()->subMinute()])->save();
        $completed = $this->booking($court, $this->tomorrowAt(19), metadata: ['operational_status' => 'completed']);

        $request = $this->pause($court->id, ['starts_at' => $this->tomorrowAt(10)->toIso8601String(), 'expected_end_at' => $this->tomorrowAt(11)->toIso8601String()], '/preview');
        $codes = collect($this->actingAs($owner)->postJson($request['url'], $request['body'])->assertOk()->json('data.bookings'))->pluck('code')->all();

        sort($codes);
        $expected = [$overlapping->code, $later->code, $checkedIn->code];
        sort($expected);
        $this->assertSame($expected, $codes, 'expected_end_at never bounds the window; cancelled, expired, completed and adjacent bookings are left out');
        $this->assertNotContains($endsAtStart->code, $codes);
        $this->assertNotContains($cancelled->code, $codes);
        $this->assertNotContains($expired->code, $codes);
        $this->assertNotContains($completed->code, $codes);
        $this->assertSame(0, OperationalStop::count(), 'a preview saves nothing');
    }

    public function test_fr_36_save_without_acknowledgement_is_409_with_the_list_and_saves_nothing(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $booking = $this->booking($court, now()->addHour()->startOfHour());

        $request = $this->pause($court->id, []);
        $this->actingAs($owner)->postJson($request['url'], $request['body'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'AFFECTED_BOOKINGS_UNACKNOWLEDGED')
            ->assertJsonPath('affected.bookings.0.code', $booking->code)
            ->assertJsonPath('affected.bookings.0.customer_phone', $booking->user->phone);
        $this->assertSame(0, OperationalStop::count());
        $this->assertSame('active', $court->fresh()->status);
    }

    public function test_br_39_incident_stop_saves_with_acknowledgement_and_keeps_the_booking(): void
    {
        $court = $this->bookableCourt();
        $staff = $this->staffOf($court->venue);
        $booking = $this->booking($court, now()->addHour()->startOfHour());

        $request = $this->pause($court->id, ['acknowledge_affected' => true]);
        $this->actingAs($staff)->postJson($request['url'], $request['body'])->assertOk();

        $this->assertSame('paused', $court->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status, 'the booking is never cancelled automatically');
        $case = AffectedReservationCase::sole();
        $this->assertSame([$booking->id, 'pending', 'operational_stop', $court->id], [$case->reservation_id, $case->status, $case->source_type, $case->court_id]);
    }

    public function test_fr_40_venue_pause_checks_every_court_and_keeps_court_statuses(): void
    {
        $court = $this->bookableCourt();
        $other = $this->activeCourt($court->venue);
        $this->priceCourt($other);
        $owner = $this->ownerOf($court->venue->organization);
        $a = $this->booking($court, $this->tomorrowAt(10));
        $b = $this->booking($other, $this->tomorrowAt(11));
        $url = "/api/partner/organizations/{$court->venue->organization_id}/venues/{$court->venue_id}";

        $preview = $this->actingAs($owner)->postJson("{$url}/pause/preview", ['reason' => 'Tạm nghỉ sửa mái che'])->assertOk();
        $this->assertEqualsCanonicalizing([$a->code, $b->code], collect($preview->json('data.bookings'))->pluck('code')->all());

        $this->actingAs($owner)->postJson("{$url}/pause", ['reason' => 'Tạm nghỉ sửa mái che', 'acknowledge_affected' => true])->assertOk();
        $this->assertSame('paused', $court->venue->fresh()->status);
        $this->assertSame(['active', 'active'], [$court->fresh()->status, $other->fresh()->status]);
        $this->assertSame(2, AffectedReservationCase::count());
    }

    public function test_fr_36_court_stop_checks_only_that_court(): void
    {
        $court = $this->bookableCourt();
        $other = $this->activeCourt($court->venue);
        $owner = $this->ownerOf($court->venue->organization);
        $this->booking($other, $this->tomorrowAt(10));

        $request = $this->pause($court->id, [], '/preview');
        $this->actingAs($owner)->postJson($request['url'], $request['body'])->assertOk()->assertJsonCount(0, 'data.bookings');
    }

    public function test_fr_41_close_a_venue_with_outstanding_bookings(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $staff = $this->staffOf($court->venue);
        $booking = $this->booking($court, $this->tomorrowAt(10));
        $url = "/api/partner/organizations/{$court->venue->organization_id}/venues/{$court->venue_id}";

        $this->actingAs($owner)->postJson("{$url}/close", ['reason' => 'Ngừng kinh doanh vĩnh viễn'])->assertStatus(409);
        $this->actingAs($owner)->postJson("{$url}/close/preview", ['reason' => 'Ngừng kinh doanh vĩnh viễn'])
            ->assertOk()->assertJsonPath('data.bookings.0.code', $booking->code);
        $this->actingAs($owner)->postJson("{$url}/close", ['reason' => 'Ngừng kinh doanh vĩnh viễn', 'acknowledge_affected' => true])->assertOk();

        $this->assertSame('closed', $court->venue->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->actingAs($staff)->getJson("/api/admin/affected-reservations?venue_id={$court->venue_id}")
            ->assertOk()->assertJsonPath('data.0.reservation.code', $booking->code);
    }

    public function test_fr_28_shorter_operating_hours_need_acknowledgement_and_open_one_case_once(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $booking = $this->booking($court, $this->tomorrowAt(20));
        $url = "/api/partner/organizations/{$court->venue->organization_id}/venues/{$court->venue_id}/operating-schedule";
        $days = [];
        foreach (range(1, 7) as $day) {
            $days[(string) $day] = ['closed' => false, 'intervals' => [['start' => '06:00', 'end' => '18:00']]];
        }
        $body = ['timezone' => 'Asia/Ho_Chi_Minh', 'weekly_hours' => $days];

        $this->actingAs($owner)->postJson("{$url}/preview", $body)->assertOk()->assertJsonPath('data.bookings.0.code', $booking->code);
        $this->actingAs($owner)->putJson($url, $body)->assertStatus(409)->assertJsonPath('affected.bookings.0.code', $booking->code);
        $this->actingAs($owner)->putJson($url, $body + ['acknowledge_affected' => true])->assertOk();
        $this->actingAs($owner)->putJson($url, $body + ['acknowledge_affected' => true])->assertOk();

        $case = AffectedReservationCase::sole();
        $this->assertSame(['venue_schedule', $booking->id], [$case->source_type, $case->reservation_id]);
    }

    public function test_fr_32_court_schedule_change_opens_a_court_schedule_case(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $booking = $this->booking($court, $this->tomorrowAt(7));
        $days = [];
        foreach (range(1, 7) as $day) {
            $days[(string) $day] = ['closed' => false, 'intervals' => [['start' => '08:00', 'end' => '22:00']]];
        }

        $this->actingAs($owner)->putJson("/api/partner/organizations/{$court->venue->organization_id}/venues/{$court->venue_id}/operating-schedule", [
            'court_id' => $court->id, 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_hours' => $days, 'acknowledge_affected' => true,
        ])->assertOk();

        $this->assertSame(['court_schedule', $booking->id], [AffectedReservationCase::sole()->source_type, AffectedReservationCase::sole()->reservation_id]);
    }

    public function test_fr_28_deleted_special_hours_exception(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $date = $this->tomorrowAt(0)->toDateString();
        $url = "/api/partner/organizations/{$court->venue->organization_id}/venues/{$court->venue_id}/schedule-exceptions";
        $this->actingAs($owner)->putJson($url, [
            'exception_date' => $date, 'name' => 'Mở muộn', 'type' => 'special_hours', 'timezone' => 'Asia/Ho_Chi_Minh',
            'intervals' => [['start' => '06:00', 'end' => '23:00']],
        ])->assertCreated();
        $booking = $this->booking($court, $this->tomorrowAt(22));
        $exception = VenueScheduleException::sole();

        $this->actingAs($owner)->postJson("{$url}/{$exception->id}/delete-preview")->assertOk()->assertJsonPath('data.bookings.0.code', $booking->code);
        $this->assertNotNull($exception->fresh(), 'the preview deletes nothing');
        $this->actingAs($owner)->deleteJson("{$url}/{$exception->id}")->assertStatus(409);
        $this->assertNotNull($exception->fresh());
        $this->actingAs($owner)->deleteJson("{$url}/{$exception->id}", ['acknowledge_affected' => true])->assertOk();

        $this->assertNull($exception->fresh());
        $case = AffectedReservationCase::sole();
        $this->assertSame(['schedule_exception', $exception->id], [$case->source_type, (int) $case->source_id]);
        $this->assertSame($date, $case->source_starts_at->copy()->setTimezone('Asia/Ho_Chi_Minh')->toDateString());
    }

    public function test_fr_37_case_list_columns_scope_and_filters(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $otherVenue = $this->openVenue(['organization_id' => $court->venue->organization_id]);
        $otherStaff = $this->staffOf($otherVenue);
        $booking = $this->booking($court, now()->addHour()->startOfHour(), status: 'held');
        $request = $this->pause($court->id, ['acknowledge_affected' => true]);
        $this->actingAs($owner)->postJson($request['url'], $request['body'])->assertOk();

        $row = $this->actingAs($owner)->getJson('/api/admin/affected-reservations')->assertOk()->json('data.0');
        $this->assertSame($booking->code, $row['reservation']['code']);
        $this->assertSame($booking->user->phone, $row['reservation']['customer_phone']);
        $this->assertSame($court->code, $row['reservation']['court_code']);
        $this->assertSame('awaiting_payment', $row['reservation']['payment_status']);
        $this->assertSame('operational_stop', $row['source']['type']);
        $this->assertNotNull($row['source']['starts_at']);
        $this->assertSame('pending', $row['status']);

        $this->actingAs($otherStaff)->getJson("/api/admin/affected-reservations?venue_id={$court->venue_id}")->assertForbidden();
        $this->actingAs($otherStaff)->getJson('/api/admin/affected-reservations')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($owner)->getJson('/api/admin/affected-reservations?status=kept')->assertOk()->assertJsonCount(0, 'data');

        $booking->forceFill(['hold_expires_at' => now()->subMinute()])->save();
        $this->actingAs($owner)->getJson('/api/admin/affected-reservations')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_br_40_unreachable_outcome_and_refund_note_by_staff(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $staff = $this->staffOf($court->venue);
        $booking = $this->booking($court, now()->addHour()->startOfHour());
        $request = $this->pause($court->id, ['acknowledge_affected' => true]);
        $this->actingAs($owner)->postJson($request['url'], $request['body'])->assertOk();
        $case = AffectedReservationCase::sole();
        $url = "/api/admin/affected-reservations/{$case->id}";

        $this->actingAs($staff)->patchJson($url, ['status' => 'unreachable', 'channel' => 'phone', 'note' => 'Gọi 2 lần không nghe'])
            ->assertOk()->assertJsonPath('data.status', 'unreachable')->assertJsonPath('data.channel', 'phone');
        $this->assertSame($staff->id, $case->fresh()->handled_by);
        $this->assertSame('confirmed', $booking->fresh()->status, 'an outcome changes nothing on the booking');

        $this->actingAs($staff)->patchJson($url, ['status' => 'kept', 'channel' => 'zalo', 'note' => 'Khách đồng ý', 'refund_note' => 'Đã hoàn 100k'])->assertForbidden();
        $this->actingAs($owner)->patchJson($url, ['status' => 'moved', 'channel' => 'zalo', 'note' => 'x'])->assertUnprocessable();
        $this->actingAs($owner)->patchJson($url, ['status' => 'kept', 'channel' => 'zalo', 'note' => 'Khách vẫn đến', 'refund_note' => 'Đã chuyển khoản hoàn 50k ngoài nền tảng'])
            ->assertOk()->assertJsonPath('data.refund_note', 'Đã chuyển khoản hoàn 50k ngoài nền tảng');
        $this->actingAs($owner)->patchJson($url, ['status' => 'contacting', 'channel' => 'zalo', 'note' => 'lại'])->assertStatus(409);
    }

    public function test_br_39_no_show_is_409_while_a_case_is_open(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $booking = $this->booking($court, now()->subHours(2)->startOfHour());
        AffectedReservationCase::create([
            'reservation_id' => $booking->id, 'venue_id' => $court->venue_id, 'court_id' => $court->id,
            'source_type' => 'operational_stop', 'source_id' => 1, 'status' => 'unreachable',
        ]);

        $this->actingAs($owner)->withHeader('X-Organization-ID', (string) $court->venue->organization_id)
            ->postJson('/api/attendance/no-show', ['code' => $booking->code])->assertStatus(409);
    }
}
