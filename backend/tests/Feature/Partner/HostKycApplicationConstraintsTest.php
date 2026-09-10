<?php

namespace Tests\Feature\Partner;

use App\Models\HostKyc;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostKycApplicationConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_persist_lifecycle_contact_and_snapshot_fields(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-TEST-001',
            'user_id' => $user->id,
            'current_user_id' => $user->id,
            'business_type' => 'individual',
            'business_name' => 'Badminton Arena 1',
            'legal_name' => 'Nguyen Van A',
            'tax_code' => '0123456789',
            'representative_name' => 'Nguyen Van A',
            'id_card_number' => '001234567890',
            'bank_name' => 'Vietcombank',
            'bank_code' => 'VCB',
            'bank_account_number' => '123456789',
            'bank_account_holder' => 'NGUYEN VAN A',
            'status' => 'draft',
            'version' => 1,
            'contact_name' => 'Nguyen Van A',
            'contact_phone' => '0901234567',
            'contact_email' => 'contact@arena.vn',
            'address_line' => '123 Le Loi',
            'ward' => 'Ben Nghe',
            'district' => 'Quan 1',
            'city' => 'Ho Chi Minh',
            'postal_code' => '700000',
            'sports' => ['badminton', 'pickleball'],
            'submission_snapshot' => ['declaration_signed' => true],
            'submitted_version' => 1,
            'reapplication_policy' => ['allowed' => true],
            'investigation_status' => 'clean',
        ]);

        $this->assertDatabaseHas('host_kycs', [
            'id' => $kyc->id,
            'kyc_code' => 'KYC-TEST-001',
            'version' => 1,
            'current_user_id' => $user->id,
            'contact_name' => 'Nguyen Van A',
            'contact_phone' => '0901234567',
            'city' => 'Ho Chi Minh',
            'investigation_status' => 'clean',
        ]);

        $fresh = $kyc->fresh();
        $this->assertEquals(['badminton', 'pickleball'], $fresh->sports);
        $this->assertEquals(['declaration_signed' => true], $fresh->submission_snapshot);
        $this->assertTrue($fresh->isDraft());
        $this->assertTrue($fresh->isCurrent());
    }

    public function test_unique_current_user_id_slot_prevents_duplicate_active_applications(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        // First application occupies the active slot
        HostKyc::create([
            'kyc_code' => 'KYC-ACTIVE-001',
            'user_id' => $user->id,
            'current_user_id' => $user->id,
            'business_type' => 'individual',
            'business_name' => 'Badminton Arena A',
            'legal_name' => 'Nguyen Van A',
            'tax_code' => '0123456789',
            'representative_name' => 'Nguyen Van A',
            'id_card_number' => '001234567890',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '111111',
            'bank_account_holder' => 'NGUYEN VAN A',
            'status' => 'draft',
            'version' => 1,
        ]);

        // Concurrent attempt to occupy the current_user_id slot for the same user must throw QueryException
        $this->expectException(QueryException::class);

        HostKyc::create([
            'kyc_code' => 'KYC-ACTIVE-002',
            'user_id' => $user->id,
            'current_user_id' => $user->id,
            'business_type' => 'individual',
            'business_name' => 'Badminton Arena A Duplicate',
            'legal_name' => 'Nguyen Van A',
            'tax_code' => '0123456789',
            'representative_name' => 'Nguyen Van A',
            'id_card_number' => '001234567890',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '111111',
            'bank_account_holder' => 'NGUYEN VAN A',
            'status' => 'draft',
            'version' => 1,
        ]);
    }

    public function test_clearing_current_user_id_allows_reapplication_link(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        // First application is rejected and releases the current slot
        $initialKyc = HostKyc::create([
            'kyc_code' => 'KYC-REJECTED-001',
            'user_id' => $user->id,
            'current_user_id' => null, // released
            'business_type' => 'individual',
            'business_name' => 'Badminton Arena Old',
            'legal_name' => 'Nguyen Van A',
            'tax_code' => '0123456789',
            'representative_name' => 'Nguyen Van A',
            'id_card_number' => '001234567890',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '111111',
            'bank_account_holder' => 'NGUYEN VAN A',
            'status' => 'rejected',
            'version' => 1,
            'reapplication_policy' => ['allowed' => true, 'cooloff_days' => 0],
        ]);

        // New application links to initialKyc via parent_kyc_id and occupies current_user_id
        $newKyc = HostKyc::create([
            'kyc_code' => 'KYC-REAPP-002',
            'user_id' => $user->id,
            'current_user_id' => $user->id,
            'parent_kyc_id' => $initialKyc->id,
            'business_type' => 'individual',
            'business_name' => 'Badminton Arena New',
            'legal_name' => 'Nguyen Van A',
            'tax_code' => '0123456789',
            'representative_name' => 'Nguyen Van A',
            'id_card_number' => '001234567890',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '111111',
            'bank_account_holder' => 'NGUYEN VAN A',
            'status' => 'draft',
            'version' => 1,
        ]);

        $this->assertDatabaseHas('host_kycs', [
            'id' => $newKyc->id,
            'parent_kyc_id' => $initialKyc->id,
            'current_user_id' => $user->id,
        ]);

        $this->assertEquals($initialKyc->id, $newKyc->parentKyc->id);
        $this->assertCount(1, $initialKyc->childKycs);
    }
}
