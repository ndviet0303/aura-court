<?php

namespace Tests\Feature\ResaleVenueCredit;

use App\Models\CashShift;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCreditEntry;
use App\Services\Finance\VenueCreditService;
use App\Services\POS\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosVenueInventory\Concerns\SellsAtVenues;
use Tests\TestCase;

/** venue-credit "Counter cash-out of venue credit": a one-time code the cashier pays out once from an open shift. */
class CashOutTest extends TestCase
{
    use RefreshDatabase;
    use SellsAtVenues;

    private const REFUSED = 'Mã đổi tiền không hợp lệ hoặc đã hết hạn.';

    private Venue $venue;

    private User $cashier;

    private CashShift $shift;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePos();
        $this->venue = $this->openVenue();
        $this->cashier = $this->staffOf($this->venue);
        $this->shift = $this->shiftAt($this->cashier, $this->venue);
        $this->customer = User::factory()->create(['name' => 'Khách Đổi Tiền']);
        app(VenueCreditService::class)->issue($this->venue->organization_id, $this->customer->id, 150000, 'entitlement_transfer', 'test-'.Str::uuid());
    }

    public function test_cash_out_at_the_counter_lowers_the_shift_expected_cash(): void
    {
        $code = $this->code(100000);

        $this->lookup($code)->assertOk()->assertJsonPath('customer_name', 'Khách Đổi Tiền')->assertJsonPath('amount', 100000);
        $this->confirm($code)->assertCreated()->assertJsonPath('balance', 50000);

        $this->assertSame(50000, $this->balance());
        $movement = $this->shift->fresh()->cash_movements[0];
        $this->assertSame(['cash_out', 100000], [$movement['type'], $movement['amount']]);
        $this->assertStringStartsWith('venue_credit:', $movement['reason']);
        $closed = app(PosService::class)->closeShift($this->shift, $this->cashier, 400000);
        $this->assertSame(400000, (int) $closed->expected_cash);
    }

    public function test_a_code_pays_out_once(): void
    {
        $code = $this->code(100000);

        $this->confirm($code)->assertCreated();
        $this->confirm($code, $this->staffOf($this->venue))->assertStatus(422)->assertJsonPath('message', self::REFUSED);

        $this->assertSame(1, VenueCreditEntry::query()->withoutGlobalScopes()->where('type', 'cash_out')->count());
        $this->assertCount(1, $this->shift->fresh()->cash_movements);
    }

    public function test_an_expired_code_is_refused_without_changing_anything(): void
    {
        $code = $this->code(100000);
        $this->travel(11)->minutes();

        $this->lookup($code)->assertStatus(422)->assertJsonPath('message', self::REFUSED);
        $this->confirm($code)->assertStatus(422);

        $this->assertUnchanged();
    }

    public function test_a_code_of_another_organization_reveals_nothing(): void
    {
        $code = $this->code(100000);
        $other = $this->openVenue();
        $otherCashier = $this->staffOf($other);
        $otherShift = $this->shiftAt($otherCashier, $other);

        $response = $this->pos($otherCashier, 'POST', "pos/shifts/{$otherShift->id}/venue-credit-cash-outs/lookup", $other, ['code' => $code]);

        $response->assertStatus(422)->assertJsonPath('message', self::REFUSED);
        $this->assertStringNotContainsString('Khách Đổi Tiền', $response->getContent());
        $this->assertStringNotContainsString('100000', $response->getContent());
        $this->pos($otherCashier, 'POST', "pos/shifts/{$otherShift->id}/venue-credit-cash-outs", $other, ['code' => $code])->assertStatus(422);
        $this->assertUnchanged();
    }

    public function test_a_balance_spent_after_the_code_was_made_is_refused(): void
    {
        $code = $this->code(100000);
        app(VenueCreditService::class)->spend($this->venue->organization_id, $this->customer->id, 60000, 'pos_order', 'elsewhere');

        $this->confirm($code)->assertStatus(422);

        $this->assertSame(90000, $this->balance());
        $this->assertSame([], $this->shift->fresh()->cash_movements);
    }

    public function test_a_new_code_replaces_the_live_one_and_cannot_exceed_the_balance(): void
    {
        $first = $this->code(50000);
        $second = $this->code(60000);
        $this->createCode(150001)->assertStatus(422);

        $this->lookup($first)->assertStatus(422);
        $this->lookup($second)->assertOk()->assertJsonPath('amount', 60000);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $second);
    }

    public function test_a_closed_shift_cannot_pay_out_and_lookups_are_capped_per_shift(): void
    {
        foreach (range(1, 10) as $ignored) {
            $this->lookup('WRONG000')->assertStatus(422);
        }
        $this->lookup('WRONG000')->assertStatus(429);

        $code = $this->code(100000);
        $this->shift->update(['status' => 'closed']);
        $this->confirm($code)->assertStatus(409);
        $this->assertSame(150000, $this->balance());
    }

    private function code(int $amount): string
    {
        return (string) $this->createCode($amount)->assertCreated()->assertJsonPath('amount', $amount)->json('code');
    }

    private function createCode(int $amount): TestResponse
    {
        return $this->actingAs($this->customer)->postJson("/api/me/venue-credits/{$this->venue->organization_id}/cash-out-codes", ['amount' => $amount]);
    }

    private function lookup(string $code): TestResponse
    {
        return $this->pos($this->cashier, 'POST', "pos/shifts/{$this->shift->id}/venue-credit-cash-outs/lookup", $this->venue, ['code' => $code]);
    }

    private function confirm(string $code, ?User $cashier = null): TestResponse
    {
        return $this->pos($cashier ?? $this->cashier, 'POST', "pos/shifts/{$this->shift->id}/venue-credit-cash-outs", $this->venue, ['code' => $code]);
    }

    private function balance(): int
    {
        return app(VenueCreditService::class)->balance($this->venue->organization_id, $this->customer->id);
    }

    private function assertUnchanged(): void
    {
        $this->assertSame(150000, $this->balance());
        $this->assertSame([], $this->shift->fresh()->cash_movements);
        $this->assertSame(0, VenueCreditEntry::query()->withoutGlobalScopes()->where('type', 'cash_out')->count());
    }
}
