<?php

namespace Tests\Feature\Booking;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\RecurringOccurrence;
use App\Models\RecurringSeries;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Booking\RecurringSeriesService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

class RecurringSeriesPreviewTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    /** @return array{0:Organization,1:User,2:Court} */
    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Preview Tenant',
            'slug' => 'preview-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'customer', 'status' => 'active']);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Preview Venue',
            'slug' => 'preview-venue-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = $venue->courts()->create(['name' => 'Preview Court', 'is_active' => true]);
        $this->priceCourt($court, 150000);

        return [$organization, $user, $court];
    }

    private function futureMonday(): Carbon
    {
        return Carbon::now(config('app.timezone'))->addWeeks(3)->startOfWeek(Carbon::MONDAY)->setTime(9, 0);
    }

    public function test_weekly_multi_weekday_preview_expands_across_selected_weekdays(): void
    {
        [, $user, $court] = $this->tenant();
        Sanctum::actingAs($user);
        $monday = $this->futureMonday();

        $res = $this->postJson('/api/recurring-series/preview', [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [1, 3, 5],
            'starts_at' => $monday->toIso8601String(),
            'occurrences' => 6,
        ]);

        $res->assertOk()
            ->assertJsonPath('count', 6)
            ->assertJsonPath('currency', 'VND')
            ->assertJsonPath('total_amount', 6 * 150000)
            ->assertJsonPath('occurrences.0.weekday', 1)
            ->assertJsonPath('occurrences.1.weekday', 3)
            ->assertJsonPath('occurrences.2.weekday', 5)
            ->assertJsonPath('occurrences.3.weekday', 1)
            ->assertJsonPath('occurrences.0.price', 150000)
            ->assertJsonPath('occurrences.0.conflict', false);

        // READ-ONLY: preview creates no reservations or series.
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('recurring_series', 0);
    }

    public function test_preview_of_thirty_occurrences_returns_all_not_truncated_to_twelve(): void
    {
        [, $user, $court] = $this->tenant();
        Sanctum::actingAs($user);
        $monday = $this->futureMonday();

        $res = $this->postJson('/api/recurring-series/preview', [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [1, 2, 3, 4, 5],
            'starts_at' => $monday->toIso8601String(),
            'occurrences' => 30,
        ]);

        $res->assertOk()
            ->assertJsonPath('count', 30)
            ->assertJsonCount(30, 'occurrences')
            ->assertJsonPath('truncated_by', 'count');
    }

    public function test_monthly_preview_uses_same_day_of_month_and_ignores_weekdays(): void
    {
        [, $user, $court] = $this->tenant();
        Sanctum::actingAs($user);
        $start = Carbon::now(config('app.timezone'))->addMonth()->startOfMonth()->addDays(14)->setTime(9, 0); // the 15th

        $res = $this->postJson('/api/recurring-series/preview', [
            'court_id' => $court->id,
            'frequency' => 'monthly',
            'starts_at' => $start->toIso8601String(),
            'occurrences' => 3,
        ]);

        $res->assertOk()->assertJsonPath('count', 3)->assertJsonPath('weekdays', []);
        foreach ($res->json('occurrences') as $occurrence) {
            $this->assertSame(15, Carbon::parse($occurrence['starts_at'])->setTimezone(config('app.timezone'))->day);
        }
    }

    public function test_count_vs_until_precedence_takes_whichever_yields_fewer(): void
    {
        [, $user, $court] = $this->tenant();
        Sanctum::actingAs($user);
        $monday = $this->futureMonday();

        // count (3) fewer than the ~11 weeks until would allow -> count wins.
        $countWins = $this->postJson('/api/recurring-series/preview', [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [1],
            'starts_at' => $monday->toIso8601String(),
            'until' => $monday->copy()->addWeeks(10)->toDateString(),
            'occurrences' => 3,
        ]);
        $countWins->assertOk()->assertJsonPath('count', 3)->assertJsonPath('truncated_by', 'count');

        // until (2 weeks -> 2 occurrences) fewer than occurrences(10) -> until wins.
        $untilWins = $this->postJson('/api/recurring-series/preview', [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [1],
            'starts_at' => $monday->toIso8601String(),
            'until' => $monday->copy()->addWeek()->addDay()->toDateString(),
            'occurrences' => 10,
        ]);
        $untilWins->assertOk()->assertJsonPath('count', 2)->assertJsonPath('truncated_by', 'until');
    }

    public function test_preview_flags_overlapping_occurrence_without_creating_a_hold(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $monday = $this->futureMonday();

        // Existing confirmed occurrence at Monday 09:00.
        app(RecurringSeriesService::class)->createAndConfirm($user, $organization->id, [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [1],
            'starts_at' => $monday->toIso8601String(),
            'occurrences' => 1,
        ]);
        $this->assertDatabaseCount('reservations', 1);

        Sanctum::actingAs($user);
        $res = $this->postJson('/api/recurring-series/preview', [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [1],
            'starts_at' => $monday->toIso8601String(),
            'occurrences' => 2,
        ]);

        $res->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('occurrences.0.conflict', true)
            ->assertJsonPath('occurrences.0.conflict_reason', 'court_time_conflict')
            ->assertJsonPath('occurrences.1.conflict', false)
            ->assertJsonPath('occurrences.1.conflict_reason', null);

        // Preview created no additional reservation.
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_biweekly_two_hour_series_persists_exactly_matching_preview(): void
    {
        [, $user, $court] = $this->tenant();
        Sanctum::actingAs($user);
        $monday = $this->futureMonday();
        $payload = [
            'court_id' => $court->id,
            'frequency' => 'biweekly',
            'weekdays' => [1, 3],
            'starts_at' => $monday->toIso8601String(),
            'ends_at' => $monday->copy()->addHours(2)->toIso8601String(),
            'occurrences' => 6,
        ];

        $preview = $this->postJson('/api/recurring-series/preview', $payload);
        $preview->assertOk()->assertJsonPath('count', 6);
        $previewStarts = collect($preview->json('occurrences'))->pluck('starts_at')->all();
        // Two-hour sessions, alternating Mon/Wed, not truncated to twelve nor single weekday.
        $this->assertSame([1, 3, 1, 3, 1, 3], collect($preview->json('occurrences'))->pluck('weekday')->all());
        foreach ($preview->json('occurrences') as $occurrence) {
            $minutes = (int) abs(Carbon::parse($occurrence['ends_at'])->diffInMinutes(Carbon::parse($occurrence['starts_at'])));
            $this->assertSame(120, $minutes);
        }

        $create = $this->withHeader('Idempotency-Key', 'series-'.uniqid())
            ->postJson('/api/recurring-series', $payload);
        $create->assertCreated();

        $series = RecurringSeries::query()->latest('id')->firstOrFail();
        $this->assertSame(6, (int) $series->occurrence_count);
        $persistedStarts = RecurringOccurrence::query()
            ->where('recurring_series_id', $series->id)
            ->orderBy('sequence')
            ->get()
            ->map(fn (RecurringOccurrence $o) => $o->starts_at->utc()->toIso8601String())
            ->all();

        $this->assertSame($previewStarts, $persistedStarts);
        // Each persisted occurrence spans the full two-hour session (not "only one slot").
        foreach (RecurringOccurrence::query()->where('recurring_series_id', $series->id)->get() as $occurrence) {
            $this->assertSame(120, (int) abs($occurrence->ends_at->diffInMinutes($occurrence->starts_at)));
        }
    }

    public function test_create_series_with_weekdays_persists_occurrences_equal_to_preview(): void
    {
        [, $user, $court] = $this->tenant();
        Sanctum::actingAs($user);
        $monday = $this->futureMonday();
        $payload = [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [2, 4],
            'starts_at' => $monday->copy()->addDay()->toIso8601String(), // Tuesday 09:00
            'occurrences' => 8,
        ];

        $preview = $this->postJson('/api/recurring-series/preview', $payload);
        $preview->assertOk()->assertJsonPath('count', 8);
        $previewStarts = collect($preview->json('occurrences'))->pluck('starts_at')->all();

        $this->withHeader('Idempotency-Key', 'series-'.uniqid())
            ->postJson('/api/recurring-series', $payload)
            ->assertCreated();

        $series = RecurringSeries::query()->latest('id')->firstOrFail();
        $persistedStarts = RecurringOccurrence::query()
            ->where('recurring_series_id', $series->id)
            ->orderBy('sequence')
            ->get()
            ->map(fn (RecurringOccurrence $o) => $o->starts_at->utc()->toIso8601String())
            ->all();

        $this->assertCount(8, $persistedStarts);
        $this->assertSame($previewStarts, $persistedStarts);
    }

    public function test_two_hour_session_reserves_every_hourly_slot(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $start = $this->futureMonday()->setTime(10, 0);

        $series = app(RecurringSeriesService::class)->createAndConfirm($user, $organization->id, [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [1],
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $start->copy()->addHours(2)->toIso8601String(),
            'occurrences' => 1,
        ]);

        $occurrence = RecurringOccurrence::query()->where('recurring_series_id', $series->id)->firstOrFail();
        $reservation = Reservation::query()->whereKey($occurrence->reservation_id)->with('slots')->firstOrFail();
        $slots = $reservation->slots->sortBy('starts_at')->values();

        // A two-hour session occupies BOTH hourly slots (10:00-11:00 AND 11:00-12:00), not just the first hour.
        // ReservationSlot times are persisted from the canonical UTC-ISO snapshot, so compare wall-clocks in UTC.
        $this->assertCount(2, $slots);
        $fmt = fn ($c) => $c->format('Y-m-d H:i');
        $this->assertSame($fmt($start->copy()->utc()), $fmt($slots[0]->starts_at));
        $this->assertSame($fmt($start->copy()->addHour()->utc()), $fmt($slots[0]->ends_at));
        $this->assertSame($fmt($start->copy()->addHour()->utc()), $fmt($slots[1]->starts_at));
        $this->assertSame($fmt($start->copy()->addHours(2)->utc()), $fmt($slots[1]->ends_at));
        // Each hourly slot is priced by the court's price table.
        $this->assertSame(150000, (int) $slots[0]->unit_price);
        $this->assertSame(150000, (int) $slots[1]->unit_price);
    }

    public function test_preview_price_equals_persisted_reservation_total_for_two_hour_session(): void
    {
        [$organization, $user, $court] = $this->tenant();
        Sanctum::actingAs($user);
        $start = $this->futureMonday()->setTime(10, 0);
        $payload = [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [1],
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $start->copy()->addHours(2)->toIso8601String(),
            'occurrences' => 1,
        ];

        $preview = $this->postJson('/api/recurring-series/preview', $payload);
        // Two hourly slots at the 150000 base rule are summed per occurrence.
        $preview->assertOk()
            ->assertJsonPath('occurrences.0.price', 2 * 150000)
            ->assertJsonPath('total_amount', 2 * 150000);

        $series = app(RecurringSeriesService::class)->createAndConfirm($user, $organization->id, $payload);
        $occurrence = RecurringOccurrence::query()->where('recurring_series_id', $series->id)->firstOrFail();
        $reservation = Reservation::query()->whereKey($occurrence->reservation_id)->firstOrFail();

        // Preview total == persisted reservation total (D34 preview==persisted invariant preserved).
        $this->assertSame((int) $preview->json('total_amount'), (int) $reservation->total_amount);
        $this->assertSame(2 * 150000, (int) $occurrence->amount);
    }

    public function test_series_ends_at_is_last_session_end_while_ends_at_is_first_session_end(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $start = $this->futureMonday()->setTime(10, 0);

        $series = app(RecurringSeriesService::class)->createAndConfirm($user, $organization->id, [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [1],
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $start->copy()->addHours(2)->toIso8601String(),
            'occurrences' => 3,
        ])->fresh();

        $lastOccurrence = RecurringOccurrence::query()
            ->where('recurring_series_id', $series->id)
            ->orderByDesc('sequence')
            ->firstOrFail();

        // ends_at stays the FIRST session's end (session template); series_ends_at is the horizon.
        $this->assertSame($start->copy()->addHours(2)->utc()->toIso8601String(), $series->ends_at->utc()->toIso8601String());
        $this->assertNotNull($series->series_ends_at);
        $this->assertSame($lastOccurrence->ends_at->utc()->toIso8601String(), $series->series_ends_at->utc()->toIso8601String());
        $this->assertTrue($series->series_ends_at->greaterThan($series->ends_at));
    }

    public function test_competitor_reserving_the_second_hour_now_conflicts(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $start = $this->futureMonday()->setTime(10, 0);

        // Existing confirmed two-hour session occupies 10:00-12:00 (both hours).
        app(RecurringSeriesService::class)->createAndConfirm($user, $organization->id, [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [1],
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $start->copy()->addHours(2)->toIso8601String(),
            'occurrences' => 1,
        ]);

        // A one-hour session starting at the SECOND hour (11:00-12:00) now overlaps the reserved slot.
        Sanctum::actingAs($user);
        $res = $this->postJson('/api/recurring-series/preview', [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [1],
            'starts_at' => $start->copy()->addHour()->toIso8601String(),
            'occurrences' => 1,
        ]);

        $res->assertOk()
            ->assertJsonPath('occurrences.0.conflict', true)
            ->assertJsonPath('occurrences.0.conflict_reason', 'court_time_conflict');
    }

    public function test_rejects_session_that_is_not_a_whole_multiple_of_slot_duration(): void
    {
        [, $user, $court] = $this->tenant();
        Sanctum::actingAs($user);
        $start = $this->futureMonday()->setTime(10, 0);

        // 90-minute session on a 60-minute-slot zone is not a whole multiple -> rejected.
        $this->postJson('/api/recurring-series/preview', [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'weekdays' => [1],
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $start->copy()->addMinutes(90)->toIso8601String(),
            'occurrences' => 1,
        ])->assertStatus(422);
    }
}
