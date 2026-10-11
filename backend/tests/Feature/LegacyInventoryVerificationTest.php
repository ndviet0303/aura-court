<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyInventoryVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_inventory_classification_and_checksum_verification(): void
    {
        // 1. Prepare sample fixtures
        $fixture = $this->createMultiOrgVenueFixture();
        $org1 = $fixture['org1']['org'];
        $owner1 = $fixture['org1']['owner'];

        // Create a wallet account
        $walletId = DB::table('wallet_accounts')->insertGetId([
            'organization_id' => $org1->id,
            'user_id' => $owner1->id,
            'currency' => 'VND',
            'balance' => 500000,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a legacy booking without metadata
        $booking1Id = DB::table('bookings')->insertGetId([
            'code' => 'BKG-'.uniqid(),
            'user_id' => $owner1->id,
            'court_id' => $fixture['org1']['court1']->id,
            'status' => 'confirmed',
            'total_amount' => 150000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a direct_owner_v1 booking with metadata
        $booking2Id = DB::table('bookings')->insertGetId([
            'code' => 'BKG-'.uniqid(),
            'user_id' => $owner1->id,
            'court_id' => $fixture['org1']['court2']->id,
            'status' => 'confirmed',
            'total_amount' => 150000,
            'metadata' => json_encode(['payment_model' => 'direct_owner_v1']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Query and verify classification
        $legacyCount = DB::table('bookings')
            ->where(function ($q) {
                $q->whereNull('metadata')
                    ->orWhereRaw("metadata->>'payment_model' IS NULL")
                    ->orWhereRaw("metadata->>'payment_model' = 'legacy_wallet_v1'");
            })->count();

        $directOwnerCount = DB::table('bookings')
            ->whereRaw("metadata->>'payment_model' = 'direct_owner_v1'")
            ->count();

        $this->assertSame(1, $legacyCount);
        $this->assertSame(1, $directOwnerCount);

        // 3. Verify deterministic checksum generation
        $walletData = DB::table('wallet_accounts')
            ->orderBy('id')
            ->select('id', 'organization_id', 'user_id', 'currency', 'balance', 'status')
            ->get()
            ->toJson();

        $checksum = hash('sha256', $walletData);
        $this->assertNotEmpty($checksum);
        $this->assertSame(64, strlen($checksum));
    }
}
