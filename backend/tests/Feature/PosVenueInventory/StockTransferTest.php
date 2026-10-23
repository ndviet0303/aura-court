<?php

namespace Tests\Feature\PosVenueInventory;

use App\Models\PosProduct;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosVenueInventory\Concerns\SellsAtVenues;
use Tests\TestCase;

/** pos-venue-inventory: an owner moves stock between two venues of the organization in one step. */
class StockTransferTest extends TestCase
{
    use RefreshDatabase;
    use SellsAtVenues;

    private Venue $venueA;

    private Venue $venueB;

    private User $owner;

    private PosProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePos();
        $this->venueA = $this->openVenue();
        $this->venueB = $this->openVenue(['organization_id' => $this->venueA->organization_id]);
        $this->owner = $this->ownerOf($this->venueA->organization);
        $this->product = $this->product($this->venueA);
    }

    public function test_an_owner_moves_stock_with_one_record_and_two_linked_movements(): void
    {
        $this->stockAt($this->venueA, $this->product, 20);
        $this->stockAt($this->venueB, $this->product, 2);

        $transferId = $this->transfer($this->owner, $this->payload(6))->assertCreated()
            ->assertJsonPath('transfer.quantity', 6)->assertJsonPath('transfer.created_by', $this->owner->id)->json('transfer.id');

        $this->assertSame(14, $this->stockOf($this->venueA, $this->product));
        $this->assertSame(8, $this->stockOf($this->venueB, $this->product));
        $this->assertSame('Bù hàng cuối tuần', StockTransfer::query()->sole()->note);
        $movements = StockMovement::query()->where('reference_type', 'stock_transfer')->where('reference_id', $transferId)->orderBy('venue_id')->get();
        $this->assertSame(
            [[$this->venueA->id, 'transfer_out', -6, 14], [$this->venueB->id, 'transfer_in', 6, 8]],
            $movements->map(fn (StockMovement $m) => [$m->venue_id, $m->type, $m->quantity_delta, $m->balance_after])->all(),
        );

        $this->pos($this->staffOf($this->venueB), 'GET', "pos/inventory/transfers?venue_id={$this->venueB->id}", $this->venueA)->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.id', $transferId)->assertJsonPath('0.from_venue_id', $this->venueA->id);
    }

    public function test_a_transfer_beyond_the_available_quantity_changes_nothing(): void
    {
        $this->stockAt($this->venueA, $this->product, 5);
        $this->pendingQrOrder($this->staffOf($this->venueA), $this->venueA, $this->product, 2);

        $this->transfer($this->owner, $this->payload(4))->assertStatus(409)->assertJsonValidationErrors('quantity');

        $this->assertNothingMoved(5);
    }

    public function test_a_venue_of_another_organization_is_refused(): void
    {
        $this->stockAt($this->venueA, $this->product, 10);
        $foreign = $this->openVenue();

        $this->transfer($this->owner, $this->payload(3, ['to_venue_id' => $foreign->id]))->assertForbidden();

        $this->assertNothingMoved(10);
    }

    public function test_invalid_transfers_are_refused_by_field(): void
    {
        $this->stockAt($this->venueA, $this->product, 10);
        $service = $this->product($this->venueA, ['category' => 'services']);
        $foreignProduct = $this->product($this->openVenue());

        $this->transfer($this->owner, $this->payload(3, ['to_venue_id' => $this->venueA->id]))->assertUnprocessable()->assertJsonValidationErrors('to_venue_id');
        $this->transfer($this->owner, $this->payload(3, ['product_id' => $service->id]))->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->transfer($this->owner, $this->payload(3, ['product_id' => $foreignProduct->id]))->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->transfer($this->owner, $this->payload(0))->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertNothingMoved(10);
    }

    public function test_staff_cannot_transfer_even_between_their_venues(): void
    {
        $this->stockAt($this->venueA, $this->product, 10);

        $this->transfer($this->staffOf($this->venueA, $this->venueB), $this->payload(1))->assertForbidden();

        $this->assertNothingMoved(10);
    }

    public function test_a_retried_transfer_moves_stock_once(): void
    {
        $this->stockAt($this->venueA, $this->product, 10);
        $key = (string) Str::uuid();

        $first = $this->transfer($this->owner, $this->payload(3), $key)->assertCreated()->json('transfer.id');
        $this->transfer($this->owner, $this->payload(3), $key)->assertCreated()->assertJsonPath('transfer.id', $first);

        $this->assertSame(7, $this->stockOf($this->venueA, $this->product));
        $this->assertSame(3, $this->stockOf($this->venueB, $this->product));
        $this->assertSame(1, StockTransfer::count());
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function payload(int $quantity, array $override = []): array
    {
        return $override + [
            'product_id' => $this->product->id,
            'from_venue_id' => $this->venueA->id,
            'to_venue_id' => $this->venueB->id,
            'quantity' => $quantity,
            'note' => 'Bù hàng cuối tuần',
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function transfer(User $user, array $payload, ?string $key = null): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/pos/inventory/transfers', $payload, [
            'X-Organization-ID' => (string) $this->venueA->organization_id,
            'Idempotency-Key' => $key ?? (string) Str::uuid(),
        ]);
    }

    private function assertNothingMoved(int $sourceQuantity): void
    {
        $this->assertSame($sourceQuantity, $this->stockOf($this->venueA, $this->product));
        $this->assertSame(0, $this->stockOf($this->venueB, $this->product));
        $this->assertSame(0, StockTransfer::count());
        $this->assertSame(0, StockMovement::count());
    }
}
