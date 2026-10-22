<?php

namespace Tests\Feature\Module05;

use App\Models\Court;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\Concerns\RecordsOwnerPayments;
use Tests\TestCase;

/** UC-5.10, FR-94, BR-89, BR-106, BR-107: the owner reconciles received transfers against their bookings. */
class OwnerFinanceTransactionsTest extends TestCase
{
    use BuildsVenues;
    use RecordsOwnerPayments;
    use RefreshDatabase;

    private Venue $venue;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-12-05 10:00', 'Asia/Ho_Chi_Minh'));
        $this->venue = $this->openVenue();
        $this->court = $this->activeCourt($this->venue);
    }

    public function test_fr_94_br_107_each_transfer_shows_its_booking_payable_received_and_delta(): void
    {
        $at = Carbon::parse('2026-11-10 09:00', 'Asia/Ho_Chi_Minh');
        $exact = $this->booking($this->court, 200000, $at);
        $short = $this->booking($this->court, 200000, $at);
        $excess = $this->booking($this->court, 200000, $at);
        $this->receipt($this->venue, 200000, $at, $exact);
        $this->receipt($this->venue, 150000, $at->copy()->addMinute(), $short, status: 'exception');
        $this->receipt($this->venue, 250000, $at->copy()->addMinutes(2), $excess, status: 'exception');

        $rows = collect($this->transactions(['from' => '2026-11-01', 'to' => '2026-11-30'])->json('data'))->keyBy('booking.code');

        $this->assertSame([200000, 0, 'allocated'], [$rows[$exact->code]['booking']['payable'], $rows[$exact->code]['delta'], $rows[$exact->code]['status']]);
        $this->assertSame([150000, -50000], [$rows[$short->code]['amount'], $rows[$short->code]['delta']]);
        $this->assertSame([250000, 50000], [$rows[$excess->code]['amount'], $rows[$excess->code]['delta']]);
        $this->assertSame($this->venue->id, $rows[$exact->code]['venue']['id']);
        $this->assertSame($at->copy()->utc()->toISOString(), $rows[$exact->code]['transaction_at']);
    }

    public function test_br_107_a_transfer_is_never_matched_to_a_booking_by_its_amount(): void
    {
        $at = Carbon::parse('2026-11-10 09:00', 'Asia/Ho_Chi_Minh');
        $this->booking($this->court, 200000, $at);
        $this->receipt($this->venue, 200000, $at, status: 'unmatched');

        $row = $this->transactions(['from' => '2026-11-01', 'to' => '2026-11-30'])->json('data.0');

        $this->assertSame('unmatched', $row['status']);
        $this->assertNull($row['booking']);
        $this->assertNull($row['delta']);
    }

    public function test_fr_94_br_106_only_booking_transfers_received_in_the_period_and_venue_are_listed(): void
    {
        $other = $this->openVenue(['organization_id' => $this->venue->organization_id]);
        $otherCourt = $this->activeCourt($other);
        $foreign = $this->activeCourt($this->openVenue());
        $first = Carbon::parse('2026-11-01 00:00', 'Asia/Ho_Chi_Minh');
        $last = Carbon::parse('2026-11-30 23:59:59', 'Asia/Ho_Chi_Minh');
        $in = [
            $this->receipt($this->venue, 100000, $first, $this->booking($this->court, 100000, $first)),
            $this->receipt($this->venue, 100000, $last, $this->booking($this->court, 100000, $last)),
        ];
        $this->receipt($this->venue, 100000, $first->copy()->subSecond(), $this->booking($this->court, 100000, $first));
        $this->receipt($this->venue, 100000, $last->copy()->addSecond(), $this->booking($this->court, 100000, $last));
        $this->receipt($this->venue, 90000, $first, $this->booking($this->court, 90000, $first), purpose: 'pos_order');
        $this->receipt($this->venue, 100000, $first, $this->booking($this->court, 100000, $first), transferType: 'out');
        $sibling = $this->receipt($other, 100000, $first, $this->booking($otherCourt, 100000, $first));
        $this->receipt($foreign->venue, 100000, $first, $this->booking($foreign, 100000, $first));

        $all = collect($this->transactions(['from' => '2026-11-01', 'to' => '2026-11-30'])->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([...$in, $sibling], $all);
        $venueOnly = collect($this->transactions(['from' => '2026-11-01', 'to' => '2026-11-30', 'venue_id' => $this->venue->id])->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame($in, $venueOnly);
    }

    public function test_br_89_only_the_owner_of_the_organization_reads_its_transactions(): void
    {
        $base = "/api/partner/organizations/{$this->venue->organization_id}/finance/transactions?from=2026-11-01&to=2026-11-30";
        $foreign = Venue::factory()->create();

        $this->actingAs($this->staffOf($this->venue))->getJson($base)->assertForbidden();
        $this->actingAs($this->ownerOf($foreign->organization))->getJson($base)->assertForbidden();
        $this->actingAs($this->ownerOf($this->venue->organization))->getJson("{$base}&venue_id={$foreign->id}")->assertForbidden();
        $this->actingAs($this->ownerOf($this->venue->organization))->getJson($base)->assertOk();
    }

    /** @param  array<string, mixed>  $query */
    private function transactions(array $query)
    {
        return $this->actingAs($this->ownerOf($this->venue->organization))
            ->getJson("/api/partner/organizations/{$this->venue->organization_id}/finance/transactions?".http_build_query($query))
            ->assertOk();
    }
}
