<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POS stock per venue (pos-venue-inventory D1, D5, D6).
 * Each organization's stock and every old movement go to its receiving venue: the active venue with the lowest id,
 * else the lowest id. A venue's stock thus stays the sum of its movements; the owner transfers stock out later.
 */
return new class extends Migration
{
    private const RECEIVING = "SELECT DISTINCT ON (organization_id) organization_id, id AS venue_id FROM venues ORDER BY organization_id, (status = 'active') DESC, id";

    public function up(): void
    {
        $withoutVenue = DB::table('organizations')
            ->whereNotExists(fn ($venues) => $venues->from('venues')->whereColumn('venues.organization_id', 'organizations.id'))
            ->where(fn ($stock) => $stock
                ->whereExists(fn ($products) => $products->from('pos_products')->whereColumn('pos_products.organization_id', 'organizations.id')->where('stock_quantity', '>', 0))
                ->orWhereExists(fn ($movements) => $movements->from('stock_movements')->whereColumn('stock_movements.organization_id', 'organizations.id')))
            ->orderBy('id')
            ->pluck('id');
        if ($withoutVenue->isNotEmpty()) {
            throw new RuntimeException('POS stock has no venue to move to: organizations '.$withoutVenue->implode(', ').' hold stock or stock movements but have no venue.');
        }

        Schema::create('venue_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained('pos_products')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
            $table->unique(['venue_id', 'product_id']);
            $table->index(['organization_id', 'product_id']);
            $table->index('product_id');
        });
        DB::statement('ALTER TABLE venue_stocks ADD CONSTRAINT venue_stocks_quantity_check CHECK (quantity >= 0)');

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained('pos_products')->restrictOnDelete();
            $table->foreignId('from_venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('to_venue_id')->constrained('venues')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['organization_id', 'created_at']);
            $table->index('product_id');
            $table->index('from_venue_id');
            $table->index('to_venue_id');
            $table->index('created_by');
        });
        DB::statement('ALTER TABLE stock_transfers ADD CONSTRAINT stock_transfers_venues_check CHECK (from_venue_id <> to_venue_id)');
        DB::statement('ALTER TABLE stock_transfers ADD CONSTRAINT stock_transfers_quantity_check CHECK (quantity > 0)');

        $now = now()->toDateTimeString();
        DB::statement('WITH receiving AS ('.self::RECEIVING.')
            INSERT INTO venue_stocks (organization_id, venue_id, product_id, quantity, created_at, updated_at)
            SELECT p.organization_id, r.venue_id, p.id, p.stock_quantity, ?, ? FROM pos_products p JOIN receiving r ON r.organization_id = p.organization_id
            WHERE p.stock_quantity > 0', [$now, $now]);

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('venue_id')->nullable();
        });
        DB::statement('WITH receiving AS ('.self::RECEIVING.")
            UPDATE stock_movements m SET venue_id = r.venue_id,
                metadata = ((CASE WHEN jsonb_typeof(m.metadata::jsonb) = 'object' THEN m.metadata::jsonb ELSE '{}'::jsonb END) || '{\"venue_backfill\": true}'::jsonb)::json
            FROM receiving r WHERE r.organization_id = m.organization_id");
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN venue_id SET NOT NULL');
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('venue_id')->references('id')->on('venues')->restrictOnDelete();
            $table->index(['venue_id', 'product_id', 'created_at']);
        });

        Schema::table('pos_products', function (Blueprint $table) {
            $table->dropColumn('stock_quantity');
        });
    }

    /** Stock returns to its product as the sum over venues; transfer records are lost. */
    public function down(): void
    {
        Schema::table('pos_products', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->default(0);
        });
        DB::statement('UPDATE pos_products p SET stock_quantity = s.total FROM (SELECT product_id, SUM(quantity) AS total FROM venue_stocks GROUP BY product_id) s WHERE s.product_id = p.id');

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('venue_id');
        });
        Schema::drop('stock_transfers');
        Schema::drop('venue_stocks');
    }
};
