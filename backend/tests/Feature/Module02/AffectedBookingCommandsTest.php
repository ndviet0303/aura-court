<?php

namespace Tests\Feature\Module02;

use App\Models\AffectedReservationCase;
use App\Models\AuditLog;
use App\Models\Court;
use App\Models\JournalEntry;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Promotion\VoucherUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\Feature\Module02\Concerns\SeedsBookings;
use Tests\TestCase;

/** FR-38, FR-39, BR-39, BR-40, BR-41 (operator handling); design D8. UC-2.16, UC-2.17, UC-2.18, UC-2.19. */
class AffectedBookingCommandsTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;
    use SeedsBookings;

    private const AGREED = ['customer_agreed' => true, 'channel' => 'phone'];

    private function openCase(Reservation $booking): AffectedReservationCase
    {
        return AffectedReservationCase::create([
            'reservation_id' => $booking->id, 'venue_id' => $booking->court->venue_id, 'court_id' => $booking->court_id,
            'source_type' => 'operational_stop', 'source_id' => 1, 'status' => 'pending',
        ]);
    }

    private function command(User $actor, Reservation $booking, string $action, array $body = [])
    {
        return $this->actingAs($actor)->withHeader('X-Organization-ID', (string) $booking->organization_id)
            ->postJson("/api/reservations/{$booking->code}/{$action}", $body);
    }

    private function sibling(Court $court, array $attributes = []): Court
    {
        $other = $this->activeCourt($court->venue, $attributes);
        $this->priceCourt($other);

        return $other;
    }

    public function test_br_39_a_command_needs_the_customer_agreement_and_a_channel(): void
    {
        $court = $this->bookableCourt();
        $booking = $this->booking($court, $this->tomorrowAt(10));
        $this->openCase($booking);

        $this->command($this->ownerOf($court->venue->organization), $booking, 'operator-cancel', ['channel' => 'phone'])
            ->assertStatus(422)->assertJsonValidationErrors('customer_agreed');
        $this->command($this->ownerOf($court->venue->organization), $booking, 'operator-cancel', ['customer_agreed' => true, 'channel' => 'email'])
            ->assertStatus(422)->assertJsonValidationErrors('channel');
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_br_39_a_command_needs_an_open_case(): void
    {
        $court = $this->bookableCourt();
        $booking = $this->booking($court, $this->tomorrowAt(10));

        $this->command($this->ownerOf($court->venue->organization), $booking, 'operator-cancel', self::AGREED)->assertStatus(409);
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_br_39_customers_and_staff_of_other_venues_cannot_handle_cases(): void
    {
        $court = $this->bookableCourt();
        $booking = $this->booking($court, $this->tomorrowAt(10));
        $this->openCase($booking);
        $otherVenue = $this->openVenue(['organization_id' => $court->venue->organization_id]);

        $this->command($booking->user, $booking, 'operator-cancel', self::AGREED)->assertForbidden();
        $this->command($this->staffOf($otherVenue), $booking, 'operator-cancel', self::AGREED)->assertForbidden();
        $this->assertSame('pending', AffectedReservationCase::sole()->status);
    }

    public function test_br_39_move_court_keeps_the_times_and_closes_the_case_without_money(): void
    {
        $court = $this->bookableCourt();
        $target = $this->sibling($court);
        $tennis = $this->sibling($court, ['sport_type' => 'tennis']);
        $booking = $this->booking($court, $this->tomorrowAt(10), 2);
        $this->openCase($booking);
        $staff = $this->staffOf($court->venue);

        $this->command($staff, $booking, 'move-court', self::AGREED + ['court_id' => $tennis->id])->assertStatus(422);
        $this->command($staff, $booking, 'move-court', self::AGREED + ['court_id' => $target->id, 'note' => 'Khách đồng ý'])->assertOk();

        $booking->refresh();
        $this->assertSame($target->id, $booking->court_id);
        $this->assertSame([$target->id], $booking->slots()->pluck('court_id')->unique()->values()->all());
        $this->assertSame([$target->id], $booking->entitlements()->pluck('court_id')->unique()->values()->all());
        $this->assertEquals($this->tomorrowAt(10)->utc(), $booking->slots()->orderBy('starts_at')->first()->starts_at->utc());
        $case = AffectedReservationCase::sole();
        $this->assertSame(['moved', 'phone', $staff->id], [$case->status, $case->contact_channel, $case->handled_by]);
        $this->assertTrue(AuditLog::query()->where('action', 'reservation.affected_moved')->exists());
        $this->assertSame(0, WalletTransaction::count() + JournalEntry::count(), 'D8 moves no money');
    }

    public function test_br_39_an_in_use_booking_moves_mid_slot(): void
    {
        $court = $this->bookableCourt();
        $target = $this->sibling($court);
        $start = $this->tomorrowAt(10);
        $booking = $this->booking($court, $start, 2, metadata: ['operational_status' => 'in_use']);
        $this->openCase($booking);
        $this->travelTo($start->copy()->addMinutes(30));

        $this->command($this->ownerOf($court->venue->organization), $booking, 'move-court', self::AGREED + ['court_id' => $target->id])->assertOk();

        $this->assertSame([$target->id], $booking->slots()->pluck('court_id')->unique()->values()->all());
    }

    public function test_br_39_change_time_keeps_the_duration(): void
    {
        $court = $this->bookableCourt();
        $booking = $this->booking($court, $this->tomorrowAt(10));
        $this->openCase($booking);
        $owner = $this->ownerOf($court->venue->organization);

        $this->command($owner, $booking, 'change-time', self::AGREED + [
            'starts_at' => $this->tomorrowAt(14)->toIso8601String(), 'ends_at' => $this->tomorrowAt(16)->toIso8601String(),
        ])->assertStatus(422);
        $this->command($owner, $booking, 'change-time', self::AGREED + [
            'starts_at' => $this->tomorrowAt(14)->toIso8601String(), 'ends_at' => $this->tomorrowAt(15)->toIso8601String(),
        ])->assertOk();

        $slot = $booking->slots()->sole();
        $this->assertEquals($this->tomorrowAt(14)->utc(), $slot->starts_at->utc());
        $this->assertEquals($this->tomorrowAt(14)->utc(), $booking->entitlements()->sole()->starts_at->utc());
        $this->assertSame('time_changed', AffectedReservationCase::sole()->status);
    }

    public function test_br_39_a_held_voucher_blocks_move_and_change_time(): void
    {
        $court = $this->bookableCourt();
        $target = $this->sibling($court);
        $booking = $this->booking($court, $this->tomorrowAt(10), status: 'held');
        $this->openCase($booking);
        $this->partialMock(VoucherUsageService::class, fn ($mock) => $mock->shouldReceive('holdsVoucher')->andReturnTrue());

        $this->command($this->ownerOf($court->venue->organization), $booking, 'move-court', self::AGREED + ['court_id' => $target->id])->assertStatus(409);
        $this->assertSame($court->id, $booking->fresh()->court_id);
        $this->assertSame('pending', AffectedReservationCase::sole()->status);
    }

    public function test_br_40_cancel_a_held_booking_releases_it_once(): void
    {
        $court = $this->bookableCourt();
        $booking = $this->booking($court, $this->tomorrowAt(10), status: 'held');
        $this->openCase($booking);

        $this->command($this->staffOf($court->venue), $booking, 'operator-cancel', self::AGREED)->assertOk();

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame('operator_affected_booking', data_get($booking->metadata, 'release_reason'));
        $this->assertSame(['cancelled'], $booking->slots()->pluck('status')->unique()->values()->all());
        $this->assertSame(['cancelled'], $booking->entitlements()->pluck('status')->unique()->values()->all());
        $this->assertSame('cancelled', AffectedReservationCase::sole()->status);
    }

    public function test_br_40_cancel_a_paid_booking_records_the_owner_refund_note_without_money(): void
    {
        $court = $this->bookableCourt();
        $booking = $this->booking($court, $this->tomorrowAt(10));
        $this->openCase($booking);

        $this->command($this->staffOf($court->venue), $booking, 'operator-cancel', self::AGREED + ['refund_note' => 'Chuyển khoản lại'])->assertForbidden();
        $this->command($this->ownerOf($court->venue->organization), $booking, 'operator-cancel', self::AGREED + ['refund_note' => 'Chuyển khoản lại'])->assertOk();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('operator_affected_booking', data_get($booking->fresh()->metadata, 'release_reason'));
        $this->assertSame('Chuyển khoản lại', AffectedReservationCase::sole()->refund_note);
        $this->assertSame(0, WalletTransaction::count() + JournalEntry::count());
    }

    public function test_br_39_resuming_the_court_does_not_restore_a_handled_booking(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $booking = $this->booking($court, $this->tomorrowAt(10));
        $this->actingAs($owner)->postJson("/api/admin/courts/{$court->id}/pause", [
            'reason' => 'Sự cố mất điện cần sửa', 'kind' => 'maintenance', 'acknowledge_affected' => true,
        ])->assertOk();
        $this->command($owner, $booking, 'operator-cancel', self::AGREED)->assertOk();

        $this->actingAs($owner)->postJson("/api/admin/courts/{$court->id}/resume")->assertOk();

        $this->assertSame('active', $court->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('cancelled', AffectedReservationCase::sole()->status);
    }

    public function test_br_39_reassign_court_is_retired(): void
    {
        $court = $this->bookableCourt();
        $status = $this->actingAs($this->ownerOf($court->venue->organization))->withHeader('X-Organization-ID', (string) $court->venue->organization_id)
            ->postJson('/api/attendance/reassign-court', [])->status();

        $this->assertContains($status, [404, 405], 'no POST route is left behind');
    }
}
