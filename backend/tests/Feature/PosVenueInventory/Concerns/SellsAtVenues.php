<?php

namespace Tests\Feature\PosVenueInventory\Concerns;

use App\Models\CashShift;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueStock;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsVenues;

/**
 * POS calls as the admin app sends them, for an organization with the POS capability on.
 *
 *     $this->enablePos();
 *     $shift = $this->shiftAt($cashier, $venue);
 *     $this->pos($cashier, 'POST', 'pos/orders', $venue, $this->cashOrder($product))->assertCreated();
 */
trait SellsAtVenues
{
    use BuildsVenues;

    protected function enablePos(): void
    {
        config(['features.flags.tenant_pos_and_wallet' => true, 'features.pilot_organizations' => ['*']]);
    }

    /** @param  array<string, mixed>  $data */
    protected function pos(User $user, string $method, string $uri, Venue $venue, array $data = []): TestResponse
    {
        return $this->actingAs($user)->json($method, "/api/{$uri}", $data, [
            'X-Organization-ID' => (string) $venue->organization_id,
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
    }

    /**
     * A tracked drink of the venue's organization; pass ['category' => 'services'] for an untracked one.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function product(Venue $venue, array $metadata = []): PosProduct
    {
        return PosProduct::create([
            'organization_id' => $venue->organization_id,
            'sku' => 'SKU'.Str::upper(Str::random(6)),
            'name' => 'Nước suối '.Str::random(4),
            'price' => 10000,
            'cost' => 6000,
            'is_active' => true,
            'metadata' => $metadata + ['category' => 'drinks'],
        ]);
    }

    protected function stockAt(Venue $venue, PosProduct $product, int $quantity): void
    {
        VenueStock::query()->updateOrCreate(
            ['venue_id' => $venue->id, 'product_id' => $product->id],
            ['organization_id' => $venue->organization_id, 'quantity' => $quantity],
        );
    }

    protected function stockOf(Venue $venue, PosProduct $product): int
    {
        return (int) VenueStock::query()->where('venue_id', $venue->id)->where('product_id', $product->id)->value('quantity');
    }

    /** A QR order of the venue still awaiting its bank receipt, which holds its units there. */
    protected function pendingQrOrder(User $cashier, Venue $venue, PosProduct $product, int $quantity): PosOrder
    {
        $order = PosOrder::create([
            'organization_id' => $venue->organization_id,
            'cashier_id' => $cashier->id,
            'venue_id' => $venue->id,
            'status' => 'pending_payment',
            'payment_method' => 'qr',
            'subtotal' => $product->price * $quantity,
            'total_amount' => $product->price * $quantity,
            'payment_reference' => 'AC'.Str::upper(Str::random(18)),
        ]);
        $order->items()->create([
            'organization_id' => $venue->organization_id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'line_total' => $product->price * $quantity,
        ]);

        return $order;
    }

    /** An open shift; a null venue is a shift recorded before venues were required. */
    protected function shiftAt(User $cashier, Venue $venue, bool $withoutVenue = false, int $openingCash = 500000): CashShift
    {
        return CashShift::create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $withoutVenue ? null : $venue->id,
            'cashier_id' => $cashier->id,
            'opening_cash' => $openingCash,
            'opened_at' => now(),
            'status' => 'open',
            'cash_movements' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed> a cash order of one line
     */
    protected function cashOrder(PosProduct $product, int $quantity = 1, array $extra = []): array
    {
        return $extra + ['items' => [['product_id' => $product->id, 'quantity' => $quantity]], 'payment_method' => 'cash'];
    }
}
