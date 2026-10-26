<?php

namespace Tests\Feature\ResaleVenueCredit;

use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCreditAccount;
use App\Models\VenueCreditEntry;
use App\Services\Finance\VenueCreditService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsVenues;
use Tests\Concerns\RecordsOwnerPayments;
use Tests\TestCase;

/** resale-venue-credit D2: one writer, one entry per source and type, never below zero, scoped per organization. */
class VenueCreditServiceTest extends TestCase
{
    use BuildsVenues;
    use RecordsOwnerPayments;
    use RefreshDatabase;

    private VenueCreditService $credits;

    private Venue $venue;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->credits = app(VenueCreditService::class);
        $this->venue = $this->openVenue();
        $this->customer = User::factory()->create();
    }

    public function test_reserve_beyond_the_balance_is_refused_and_a_second_hold_cannot_reuse_it(): void
    {
        $this->issue(80000);
        $first = $this->heldBooking(80000);
        $second = $this->heldBooking(80000);

        $this->credits->reserve($first, 80000);
        $this->assertRefused(fn () => $this->credits->reserve($second, 1), 422);

        $this->assertSame(['balance' => 0, 'reserved' => 80000], $this->account());
    }

    public function test_the_database_refuses_a_negative_balance(): void
    {
        $this->issue(10000);

        $this->expectException(QueryException::class);
        DB::table('venue_credit_accounts')->update(['balance' => -1]);
    }

    public function test_the_same_source_is_recorded_once(): void
    {
        $this->issue(50000, 'transfer-1');
        $this->issue(50000, 'transfer-1');
        $booking = $this->heldBooking(30000);
        $this->credits->reserve($booking, 30000);
        $this->credits->reserve($booking, 30000);
        $this->credits->capture($booking);
        $this->credits->capture($booking);

        $this->assertSame(['balance' => 20000, 'reserved' => 0], $this->account());
        $this->assertSame(3, VenueCreditEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_a_released_hold_cannot_be_captured_and_a_captured_one_cannot_be_released(): void
    {
        $this->issue(60000);
        $released = $this->heldBooking(20000);
        $captured = $this->heldBooking(20000);
        $this->credits->reserve($released, 20000);
        $this->credits->reserve($captured, 20000);

        $this->credits->release($released);
        $this->assertNull($this->credits->capture($released));
        $this->credits->capture($captured);
        $this->assertNull($this->credits->release($captured));

        $this->assertSame(['balance' => 40000, 'reserved' => 0], $this->account());
    }

    public function test_credit_cannot_be_spent_at_another_organization(): void
    {
        $this->issue(50000);
        $other = $this->openVenue();

        $this->assertRefused(fn () => $this->credits->spend($other->organization_id, $this->customer->id, 10000, 'pos_order', 1), 422);
        $this->assertSame(50000, $this->credits->balance($this->venue->organization_id, $this->customer->id));
        $this->assertSame(0, $this->credits->balance($other->organization_id, $this->customer->id));
    }

    public function test_credit_never_expires(): void
    {
        $this->issue(40000);
        $this->travel(400)->days();

        $this->credits->spend($this->venue->organization_id, $this->customer->id, 40000, 'pos_order', 7);

        $this->assertSame(['balance' => 0, 'reserved' => 0], $this->account());
    }

    public function test_entries_are_append_only(): void
    {
        $entry = $this->issue(10000);

        $this->assertRefused(fn () => $entry->update(['balance_delta' => 99999]), null);
        $this->assertRefused(fn () => $entry->delete(), null);
    }

    public function test_no_route_tops_up_or_moves_credit_between_customers(): void
    {
        $writes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'venue-credit') && array_intersect($route->methods(), ['POST', 'PUT', 'PATCH']))
            ->map(fn ($route) => $route->uri())->values()->all();

        $this->assertEqualsCanonicalizing([
            'api/me/venue-credits/{organization}/cash-out-codes',
            'api/pos/shifts/{shift}/venue-credit-cash-outs/lookup',
            'api/pos/shifts/{shift}/venue-credit-cash-outs',
        ], $writes);
    }

    private function issue(int $amount, string $source = 'transfer'): VenueCreditEntry
    {
        return $this->credits->issue($this->venue->organization_id, $this->customer->id, $amount, 'entitlement_transfer', $source.'-'.$amount.($source === 'transfer' ? uniqid() : ''));
    }

    private function heldBooking(int $total): Reservation
    {
        return $this->booking($this->activeCourt($this->venue), $total, status: 'held', attributes: ['user_id' => $this->customer->id]);
    }

    /** @return array{balance: int, reserved: int} */
    private function account(): array
    {
        $account = VenueCreditAccount::query()->withoutGlobalScopes()->where('user_id', $this->customer->id)->firstOrFail();

        return ['balance' => $account->balance, 'reserved' => $account->reserved];
    }

    private function assertRefused(callable $action, ?int $status): void
    {
        try {
            $action();
            $this->fail('Expected the credit move to be refused.');
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        } catch (\LogicException) {
            $this->assertNull($status);
        }
    }
}
