<?php

namespace Tests\Feature\Module05;

use App\Domain\Package\Actions\SendPackageExpiryReminders;
use App\Domain\Package\Enums\PackageTerm;
use App\Models\OutboxMessage;
use App\Models\Venue;
use App\Models\VenuePackage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** FR-93 (automatic, no use case), BR-105: reminders 7, 3 and 1 day before the end and at the end, in-app and by email. */
class PackageExpiryReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-01 10:00', 'Asia/Ho_Chi_Minh'));
    }

    public function test_fr_93_br_105_each_reminder_point_is_sent_once_with_venue_term_end_and_renewal_guide(): void
    {
        $venue = Venue::factory()->withoutPackage()->create(['name' => 'Sân Cầu Lông Aura']);
        $owner = $this->ownerOf($venue->organization);
        $package = VenuePackage::factory()->create(['venue_id' => $venue->id, 'term_days' => PackageTerm::Days30, 'starts_at' => now()->subDays(20)]);
        $end = $package->ends_at->copy();

        $this->travelTo($end->copy()->subDays(8));
        $this->remind();
        $this->assertSame([], $this->offsets());
        foreach ([7, 3, 1, 0] as $days) {
            $this->travelTo($end->copy()->subDays($days));
            $this->remind();
            $this->remind();
        }

        $this->assertSame([7, 3, 1, 0], $this->offsets());
        $first = $this->reminders()->first();
        $this->assertSame($owner->id, $first->payload['user_id']);
        $this->assertSame(['database', 'mail'], $first->payload['channels']);
        $this->assertSame(['database', 'mail'], $first->payload['delivered_channels']);
        $this->assertStringContainsString('Sân Cầu Lông Aura', $first->payload['body']);
        $this->assertStringContainsString('Gói 30 ngày', $first->payload['body']);
        $this->assertStringContainsString($end->copy()->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y'), $first->payload['body']);
        $this->assertStringContainsString('Gia hạn', $first->payload['body']);
        $this->assertSame('/partner/subscription', $first->payload['action_url']);
    }

    public function test_br_105_a_renewal_replaces_the_pending_reminders_with_the_new_end(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $this->ownerOf($venue->organization);
        $package = VenuePackage::factory()->create(['venue_id' => $venue->id, 'term_days' => PackageTerm::Days30, 'starts_at' => now()->subDays(25)]);
        $oldEnd = $package->ends_at->copy();

        $this->remind(); // 5 days left: the 7-day point
        $package->update(['ends_at' => PackageTerm::Days30->endsAt($oldEnd)]);
        $this->travelTo($oldEnd->copy()->subDays(3));
        $this->remind();
        $this->travelTo($oldEnd);
        $this->remind();

        $this->assertSame([7], $this->offsets(), 'No reminder follows the old end after the renewal.');
        $this->travelTo($package->ends_at->copy()->subDays(7));
        $this->remind();
        $this->assertSame([7, 7], $this->offsets());
    }

    public function test_br_105_a_terminated_package_or_closed_venue_gets_no_reminder(): void
    {
        $terminated = Venue::factory()->withoutPackage()->create();
        $this->ownerOf($terminated->organization);
        VenuePackage::factory()->create(['venue_id' => $terminated->id, 'term_days' => PackageTerm::Days30, 'starts_at' => now()->subDays(25), 'terminated_at' => now()->subDay()]);
        $closed = Venue::factory()->withoutPackage()->create(['status' => Venue::STATUS_CLOSED]);
        $this->ownerOf($closed->organization);
        VenuePackage::factory()->create(['venue_id' => $closed->id, 'term_days' => PackageTerm::Days30, 'starts_at' => now()->subDays(25)]);

        $this->remind();

        $this->assertSame([], $this->offsets());
    }

    private function remind(): void
    {
        app(SendPackageExpiryReminders::class)->execute();
    }

    /** @return Collection<int, OutboxMessage> */
    private function reminders(): Collection
    {
        return OutboxMessage::query()->withoutGlobalScopes()->where('event_type', 'package.reminder')->orderBy('id')->get();
    }

    /** @return list<int> */
    private function offsets(): array
    {
        return $this->reminders()->map(fn (OutboxMessage $m) => $m->payload['days_before_end'])->all();
    }
}
