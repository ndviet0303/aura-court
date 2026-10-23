<?php

namespace Tests\Feature\PosVenueInventory;

use App\Models\Organization;
use App\Models\Venue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/** Stock cutover preserves quantities (D6); each test starts from organization-wide stock by running down() first. */
class StockCutoverMigrationTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    private Migration $cutover;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cutover = require database_path('migrations/2026_10_23_000001_create_venue_stocks_and_stock_transfers.php');
        $this->cutover->down();
    }

    public function test_stock_and_history_move_to_the_lowest_active_venue(): void
    {
        $inactive = $this->openVenue(['status' => Venue::STATUS_INACTIVE]);
        $receiving = $this->openVenue(['organization_id' => $inactive->organization_id]);
        $other = $this->openVenue(['organization_id' => $inactive->organization_id]);
        $product = $this->legacyProduct($inactive->organization_id, 36);
        $this->legacyMovement($inactive->organization_id, $product, 40, ['note' => 'Nhập đầu kỳ']);
        $this->legacyMovement($inactive->organization_id, $product, -4, null);

        $this->cutover->up();

        $this->assertSame([$receiving->id => 36], DB::table('venue_stocks')->where('product_id', $product)->pluck('quantity', 'venue_id')->all());
        $this->assertSame(0, DB::table('venue_stocks')->whereIn('venue_id', [$inactive->id, $other->id])->count());
        $movements = DB::table('stock_movements')->where('product_id', $product)->orderBy('id')->get();
        $this->assertSame([$receiving->id], $movements->pluck('venue_id')->unique()->values()->all());
        $this->assertSame(36, (int) $movements->sum('quantity_delta'));
        $this->assertSame(['note' => 'Nhập đầu kỳ', 'venue_backfill' => true], json_decode($movements[0]->metadata, true));
        $this->assertSame(['venue_backfill' => true], json_decode($movements[1]->metadata, true));
        $this->assertFalse(Schema::hasColumn('pos_products', 'stock_quantity'));
    }

    public function test_an_organization_without_active_venues_uses_its_lowest_venue(): void
    {
        $first = $this->openVenue(['status' => Venue::STATUS_CLOSED]);
        $this->openVenue(['organization_id' => $first->organization_id, 'status' => Venue::STATUS_INACTIVE]);
        $product = $this->legacyProduct($first->organization_id, 7);

        $this->cutover->up();

        $this->assertSame([$first->id => 7], DB::table('venue_stocks')->where('product_id', $product)->pluck('quantity', 'venue_id')->all());
    }

    public function test_stock_without_a_venue_stops_the_cutover_unchanged(): void
    {
        $this->legacyProduct($this->openVenue()->organization_id, 3);
        $lonely = Organization::create(['name' => 'Không cơ sở', 'slug' => 'org-lonely', 'status' => 'active', 'partner_status' => 'approved']);
        $product = $this->legacyProduct($lonely->id, 5);

        try {
            $this->cutover->up();
            $this->fail('The cutover ran for an organization without venues.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("organizations {$lonely->id} hold", $exception->getMessage());
        }

        $this->assertFalse(Schema::hasTable('venue_stocks'));
        $this->assertFalse(Schema::hasColumn('stock_movements', 'venue_id'));
        $this->assertSame(5, (int) DB::table('pos_products')->where('id', $product)->value('stock_quantity'));
    }

    public function test_rollback_returns_each_product_its_total(): void
    {
        $receiving = $this->openVenue();
        $other = $this->openVenue(['organization_id' => $receiving->organization_id]);
        $product = $this->legacyProduct($receiving->organization_id, 36);
        $this->cutover->up();
        DB::table('venue_stocks')->where('venue_id', $receiving->id)->update(['quantity' => 30]);
        DB::table('venue_stocks')->insert(['organization_id' => $receiving->organization_id, 'venue_id' => $other->id, 'product_id' => $product, 'quantity' => 6]);

        $this->cutover->down();

        $this->assertSame(36, (int) DB::table('pos_products')->where('id', $product)->value('stock_quantity'));
        $this->assertFalse(Schema::hasTable('venue_stocks'));
        $this->assertFalse(Schema::hasColumn('stock_movements', 'venue_id'));
    }

    private function legacyProduct(int $organizationId, int $stock): int
    {
        return DB::table('pos_products')->insertGetId([
            'organization_id' => $organizationId,
            'sku' => 'SKU-'.$organizationId.'-'.$stock,
            'name' => 'Nước suối',
            'price' => 10000,
            'stock_quantity' => $stock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>|null  $metadata */
    private function legacyMovement(int $organizationId, int $productId, int $delta, ?array $metadata): void
    {
        DB::table('stock_movements')->insert([
            'organization_id' => $organizationId,
            'product_id' => $productId,
            'type' => $delta > 0 ? 'in' : 'sale',
            'quantity_delta' => $delta,
            'metadata' => $metadata === null ? null : json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
