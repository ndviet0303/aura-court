<?php

namespace App\Console\Commands;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Models\Court;
use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\PosProduct;
use App\Models\PriceTable;
use App\Models\ReceivingConnection;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Models\VenueOperatingSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class E2ESeedFixturesCommand extends Command
{
    protected $signature = 'e2e:seed-fixtures
        {--run-id= : Unique run ID}
        {--credentials-json= : Optional JSON encoded user credentials}';

    protected $description = 'Seed non-demo multi-organization E2E fixtures satisfying real business rules';

    public function handle(): int
    {
        $runId = trim((string) $this->option('run-id'));
        if ($runId === '' || ! preg_match('/^[a-zA-Z0-9_-]+$/', $runId)) {
            $this->error('CRITICAL: --run-id is mandatory and must match /^[a-zA-Z0-9_-]+$/.');

            return self::FAILURE;
        }

        // Strict target safety check before any mutation
        $env = app()->environment();
        if ($env !== 'testing') {
            $this->error("CRITICAL: e2e:seed-fixtures is restricted to APP_ENV=testing, got '{$env}'.");

            return self::FAILURE;
        }

        $expectedDb = "booking_e2e_{$runId}_test";
        $dbConfig = DB::connection()->getConfig();
        $dbHost = (string) ($dbConfig['host'] ?? '');
        $dbName = (string) ($dbConfig['database'] ?? '');

        $allowedHosts = ['127.0.0.1', 'localhost', '::1'];
        if (! in_array($dbHost, $allowedHosts, true)) {
            $this->error("CRITICAL: Database host '{$dbHost}' is not local loopback.");

            return self::FAILURE;
        }

        if ($dbName === 'booking' || $dbName === 'booking_test') {
            $this->error("CRITICAL: Refusing to seed fixtures into protected database '{$dbName}'.");

            return self::FAILURE;
        }

        if ($dbName !== $expectedDb) {
            $this->error("CRITICAL: Configured database '{$dbName}' does not match expected run database '{$expectedDb}'.");

            return self::FAILURE;
        }

        // Live server verification
        try {
            $row = DB::connection()->selectOne('SELECT current_database() as db, inet_server_addr() as server_ip');
            if ($row?->db !== $expectedDb) {
                $this->error("CRITICAL: Connected database '{$row?->db}' does not match '{$expectedDb}'.");

                return self::FAILURE;
            }
            if ($row?->server_ip !== null && ! in_array($row->server_ip, ['127.0.0.1', '::1'], true)) {
                $this->error("CRITICAL: Connected server IP '{$row->server_ip}' is remote.");

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('CRITICAL: Database connectivity probe failed: '.$e->getMessage());

            return self::FAILURE;
        }

        // @phpstan-ignore larastan.console.undefinedOption (the only subclass, e2e:seed-production-fixtures, overrides handle(), so it never reaches this read)
        $credentialsJson = (string) $this->option('credentials-json');
        $customCredentials = [];
        if ($credentialsJson !== '') {
            $customCredentials = json_decode($credentialsJson, true) ?: [];
        }

        return $this->seedFixtures($runId, $customCredentials);
    }

    protected function seedFixtures(string $runId, array $customCredentials = [], ?string $password = null, array $organizationIds = []): int
    {
        $defaultPassword = $password ?? 'Password123!@#'.$runId;

        return DB::transaction(function () use ($runId, $customCredentials, $defaultPassword, $organizationIds) {
            // -------------------------------------------------------------
            // 1. Users & Actors
            // -------------------------------------------------------------
            // Platform Admin
            $adminEmail = $customCredentials['admin']['email'] ?? "admin_{$runId}@auracourt.test";
            $adminPassword = $customCredentials['admin']['password'] ?? $defaultPassword;
            $admin = User::updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => "Platform Admin {$runId}",
                    'password' => Hash::make($adminPassword),
                    'role' => 'admin',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'phone' => '0901000001',
                    'phone_verified_at' => now(),
                    'onboarded' => true,
                    'password_set' => true,
                ]
            );

            // Active Customer
            $customerEmail = $customCredentials['customer']['email'] ?? "customer_{$runId}@auracourt.test";
            $customerPassword = $customCredentials['customer']['password'] ?? $defaultPassword;
            $customer = User::updateOrCreate(
                ['email' => $customerEmail],
                [
                    'name' => "Customer {$runId}",
                    'password' => Hash::make($customerPassword),
                    'role' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'phone' => '0901000002',
                    'phone_verified_at' => now(),
                    'onboarded' => true,
                    'password_set' => true,
                ]
            );

            // Active Customer 2 (for multi-context contention / dispute / matchmaking tests)
            $customer2Email = $customCredentials['customer2']['email'] ?? "customer2_{$runId}@auracourt.test";
            $customer2Password = $customCredentials['customer2']['password'] ?? $defaultPassword;
            $customer2 = User::updateOrCreate(
                ['email' => $customer2Email],
                [
                    'name' => "Customer 2 {$runId}",
                    'password' => Hash::make($customer2Password),
                    'role' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'phone' => '0901000010',
                    'phone_verified_at' => now(),
                    'onboarded' => true,
                    'password_set' => true,
                ]
            );
            // Unverified Customer
            $unverifiedEmail = $customCredentials['unverifiedCustomer']['email'] ?? "unverified_{$runId}@auracourt.test";
            $unverifiedPassword = $customCredentials['unverifiedCustomer']['password'] ?? $defaultPassword;
            $unverifiedCustomer = User::updateOrCreate(
                ['email' => $unverifiedEmail],
                [
                    'name' => "Unverified Customer {$runId}",
                    'password' => Hash::make($unverifiedPassword),
                    'role' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => null,
                    'phone' => '0901000003',
                    'phone_verified_at' => null,
                    'onboarded' => false,
                    'password_set' => true,
                ]
            );

            // Restricted Customer (suspended)
            $restrictedEmail = $customCredentials['restrictedCustomer']['email'] ?? "restricted_{$runId}@auracourt.test";
            $restrictedPassword = $customCredentials['restrictedCustomer']['password'] ?? $defaultPassword;
            $restrictedCustomer = User::updateOrCreate(
                ['email' => $restrictedEmail],
                [
                    'name' => "Restricted Customer {$runId}",
                    'password' => Hash::make($restrictedPassword),
                    'role' => 'customer',
                    'status' => 'suspended',
                    'email_verified_at' => now(),
                    'phone' => '0901000004',
                    'phone_verified_at' => now(),
                    'onboarded' => true,
                    'password_set' => true,
                ]
            );

            foreach ($organizationIds as $organizationId) {
                $existing = Organization::findOrFail($organizationId);
                $previousRun = $existing->settings['e2e_run_id'];
                $emails = array_map(fn ($actor) => "{$actor}_{$previousRun}@auracourt.test", [
                    'admin', 'customer', 'customer2', 'unverified', 'restricted', 'owner', 'manager', 'cashier', 'staff', 'foreign_owner',
                ]);
                User::whereIn('email', $emails)->update(['status' => 'suspended']);
                OrganizationMember::where('organization_id', $organizationId)->update(['status' => 'inactive']);
                PlatformSubscription::where('organization_id', $organizationId)->update(['status' => 'expired', 'ends_at' => now()]);
                Venue::where('organization_id', $organizationId)->update(['status' => Venue::STATUS_CLOSED]);
                PosProduct::where('organization_id', $organizationId)->update(['is_active' => false]);
            }
            $createOrganization = function (array $attributes, int $index) use ($organizationIds) {
                if (! isset($organizationIds[$index])) {
                    return Organization::create($attributes);
                }
                $organization = Organization::findOrFail($organizationIds[$index]);
                $organization->update($attributes);

                return $organization;
            };

            // Tenant 1 Owner
            $ownerEmail = $customCredentials['owner']['email'] ?? "owner_{$runId}@auracourt.test";
            $ownerPassword = $customCredentials['owner']['password'] ?? $defaultPassword;
            $owner1 = User::updateOrCreate(
                ['email' => $ownerEmail],
                [
                    'name' => "Owner Org 1 {$runId}",
                    'password' => Hash::make($ownerPassword),
                    'role' => 'owner',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'phone' => '0901000005',
                    'phone_verified_at' => now(),
                    'onboarded' => true,
                    'password_set' => true,
                ]
            );

            // Tenant 1 Manager
            $managerEmail = $customCredentials['manager']['email'] ?? "manager_{$runId}@auracourt.test";
            $managerPassword = $customCredentials['manager']['password'] ?? $defaultPassword;
            $manager1 = User::updateOrCreate(
                ['email' => $managerEmail],
                [
                    'name' => "Manager Org 1 {$runId}",
                    'password' => Hash::make($managerPassword),
                    'role' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'phone' => '0901000006',
                    'phone_verified_at' => now(),
                    'onboarded' => true,
                    'password_set' => true,
                ]
            );

            // Tenant 1 Cashier
            $cashierEmail = $customCredentials['cashier']['email'] ?? "cashier_{$runId}@auracourt.test";
            $cashierPassword = $customCredentials['cashier']['password'] ?? $defaultPassword;
            $cashier1 = User::updateOrCreate(
                ['email' => $cashierEmail],
                [
                    'name' => "Cashier Org 1 {$runId}",
                    'password' => Hash::make($cashierPassword),
                    'role' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'phone' => '0901000007',
                    'phone_verified_at' => now(),
                    'onboarded' => true,
                    'password_set' => true,
                ]
            );

            // Tenant 1 Staff
            $staffEmail = $customCredentials['staff']['email'] ?? "staff_{$runId}@auracourt.test";
            $staffPassword = $customCredentials['staff']['password'] ?? $defaultPassword;
            $staff1 = User::updateOrCreate(
                ['email' => $staffEmail],
                [
                    'name' => "Staff Org 1 {$runId}",
                    'password' => Hash::make($staffPassword),
                    'role' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'phone' => '0901000008',
                    'phone_verified_at' => now(),
                    'onboarded' => true,
                    'password_set' => true,
                ]
            );

            // Tenant 2 Foreign Owner
            $foreignOwnerEmail = $customCredentials['foreignOwner']['email'] ?? "foreign_owner_{$runId}@auracourt.test";
            $foreignOwnerPassword = $customCredentials['foreignOwner']['password'] ?? $defaultPassword;
            $owner2 = User::updateOrCreate(
                ['email' => $foreignOwnerEmail],
                [
                    'name' => "Foreign Owner Org 2 {$runId}",
                    'password' => Hash::make($foreignOwnerPassword),
                    'role' => 'owner',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'phone' => '0901000009',
                    'phone_verified_at' => now(),
                    'onboarded' => true,
                    'password_set' => true,
                ]
            );

            // Explicitly set guarded verification timestamps
            foreach ([$admin, $customer, $customer2, $restrictedCustomer, $owner1, $manager1, $cashier1, $staff1, $owner2] as $verifiedUser) {
                $verifiedUser->forceFill(['email_verified_at' => now(), 'phone_verified_at' => now()])->save();
            }
            $unverifiedCustomer->forceFill(['email_verified_at' => null, 'phone_verified_at' => null])->save();

            // -------------------------------------------------------------
            // 2. Platform Plan
            // -------------------------------------------------------------
            $plan = PlatformPlan::firstOrCreate(
                ['code' => "plan-e2e-standard-{$runId}"],
                [
                    'name' => "Standard E2E Plan {$runId}",
                    'version' => 1,
                    'price' => 500000,
                    'currency' => 'VND',
                    'billing_cycle' => 'monthly',
                    'max_venues' => 1000,
                    'max_courts' => 10000,
                    'max_staff' => 20,
                    'trial_enabled' => true,
                    'trial_days' => 14,
                    'terms_version' => 'v1.0',
                    'terms_text' => 'Điều khoản sử dụng gói dịch vụ nền tảng Aura Court.',
                    'status' => 'published',
                    'is_active' => true,
                    'published_at' => now()->subDays(10),
                ]
            );

            // -------------------------------------------------------------
            // 3. Organization 1 (Primary Tenant with 2 sibling venues)
            // -------------------------------------------------------------
            $org1 = $createOrganization([
                'name' => "Aura Club Primary {$runId}",
                'slug' => "aura-primary-{$runId}",
                'status' => 'active',
                'partner_status' => 'approved',
                'partner_status_changed_at' => now(),
                'settings' => [
                    'e2e_run_id' => $runId,
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'support_email' => "support_{$runId}@auracourt.test",
                    'phone' => '0901000005',
                ],
            ], 0);

            // Org 1 Memberships
            OrganizationMember::create([
                'organization_id' => $org1->id,
                'user_id' => $owner1->id,
                'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
                'status' => 'active',
            ]);
            $managerMember = OrganizationMember::create([
                'organization_id' => $org1->id,
                'user_id' => $manager1->id,
                'role' => OrganizationMember::ROLE_STAFF,
                'status' => 'active',
            ]);
            $cashierMember = OrganizationMember::create([
                'organization_id' => $org1->id,
                'user_id' => $cashier1->id,
                'role' => OrganizationMember::ROLE_STAFF,
                'status' => 'active',
            ]);
            $staffMember = OrganizationMember::create([
                'organization_id' => $org1->id,
                'user_id' => $staff1->id,
                'role' => OrganizationMember::ROLE_STAFF,
                'status' => 'active',
            ]);
            OrganizationMember::create([
                'organization_id' => $org1->id,
                'user_id' => $customer->id,
                'role' => OrganizationMember::ROLE_CUSTOMER,
                'status' => 'active',
            ]);
            OrganizationMember::create([
                'organization_id' => $org1->id,
                'user_id' => $customer2->id,
                'role' => OrganizationMember::ROLE_CUSTOMER,
                'status' => 'active',
            ]);
            // Org 1 KYC approval
            HostKyc::create([
                'kyc_code' => "KYC-ORG1-{$runId}",
                'organization_id' => $org1->id,
                'user_id' => $owner1->id,
                'business_type' => 'company',
                'business_name' => "Aura Court Org 1 {$runId} Ltd",
                'legal_name' => "Aura Court Org 1 {$runId} Ltd",
                'tax_code' => "0300{$runId}",
                'tax_status' => 'verified',
                'representative_name' => $owner1->name,
                'id_card_number' => '079090000001',
                'bank_name' => 'MBBank',
                'bank_code' => 'MBBank',
                'bank_account_number' => '0901000005',
                'bank_account_holder' => 'AURA COURT ORG 1 LTD',
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'submitted_at' => now()->subDay(),
                'version' => 1,
            ]);
            // Org 1 Subscription
            PlatformSubscription::create([
                'organization_id' => $org1->id,
                'plan_id' => $plan->id,
                'plan_version' => 1,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'auto_renew' => true,
                'starts_at' => now()->subDays(5),
                'ends_at' => now()->addMonths(1),
                'price_snapshot' => 500000,
                'currency_snapshot' => 'VND',
            ]);

            // Venue 1-A (Primary Venue of Org 1)
            $venue1A = Venue::create([
                'organization_id' => $org1->id,
                'name' => "Aura Venue 1-A {$runId}",
                'slug' => "venue-1a-{$runId}",
                'address' => '123 Nguyen Trai, District 1',
                'city' => 'Ho Chi Minh',
                'district' => 'District 1',
                'phone' => '0901000005',
                'email' => "venue1a_{$runId}@auracourt.test",
                'status' => Venue::STATUS_ACTIVE,
                'published_at' => now(),
            ]);
            // BR-20: staff operate only assigned venues, so every org-1 staff fixture works at venue 1-A.
            array_map(fn (OrganizationMember $member) => $member->update(['permissions' => ['venue_id' => $venue1A->id, 'venue_ids' => [$venue1A->id]]]), [$managerMember, $cashierMember, $staffMember]);

            // Receiving Connection for Venue 1-A
            $rc1 = ReceivingConnection::create([
                'organization_id' => $org1->id,
                'venue_id' => $venue1A->id,
                'owner_user_id' => $owner1->id,
                'provider' => 'sepay',
                'routing_id' => (string) Str::uuid(),
                'bank_code' => 'MBBank',
                'account_name_ciphertext' => Crypt::encryptString("AURA COURT 1A {$runId}"),
                'account_number_ciphertext' => Crypt::encryptString('0987654321'),
                'account_last_four' => '4321',
                'status' => ReceivingConnection::STATUS_VERIFIED,
                'webhook_secret_ciphertext' => Crypt::encryptString("whsec_e2e_1a_{$runId}"),
                'credential_version' => 1,
                'owner_attested_at' => now()->subDay(),
                'verified_at' => now(),
                'effective_from' => now()->subDay(),
            ]);
            $venue1A->update(['current_receiving_connection_id' => $rc1->id]);

            // Scope manager, cashier, staff of Org 1 to Venue 1-A (enables sibling venue denial on Venue 1-B)
            foreach ([$manager1, $cashier1, $staff1] as $memberUser) {
                OrganizationMember::where('organization_id', $org1->id)
                    ->where('user_id', $memberUser->id)
                    ->first()
                    ?->update([
                        'permissions' => [
                            'venue_id' => $venue1A->id,
                            'venue_ids' => [$venue1A->id],
                        ],
                    ]);
            }
            $weeklyHours = [];
            for ($d = 1; $d <= 7; $d++) {
                $weeklyHours[(string) $d] = [
                    'closed' => false,
                    'intervals' => [['start' => '06:00', 'end' => '23:00']],
                ];
            }
            VenueOperatingSchedule::create([
                'venue_id' => $venue1A->id,
                'scope_key' => 'venue',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'version' => 1,
                'weekly_hours' => $weeklyHours,
                'created_by' => $owner1->id,
            ]);

            // Real Booking Policy for Venue 1-A
            $bookingPolicySettings = [
                'booking' => [
                    'hold_ttl_minutes' => 15,
                    'max_days_ahead' => 30,
                    'min_duration_minutes' => 60,
                    'max_duration_minutes' => 240,
                ],
                'arrival' => [
                    'early_minutes' => 15,
                    'late_grace_minutes' => 15,
                    'no_show_after_minutes' => 30,
                ],
                'recurring' => [
                    'payment_policy' => 'per_period',
                    'conflict_policy' => 'all_or_nothing',
                    'overdue_release_hours' => 24,
                    'guarantee_confirm' => false,
                ],
                'transfer' => [
                    'cutoff_hours' => 24,
                    'max_consideration_percent' => 100,
                    'free_transfer_allowed' => true,
                ],
            ];

            VenueBookingPolicy::create([
                'venue_id' => $venue1A->id,
                'version' => 1,
                'settings' => $bookingPolicySettings,
                'created_by' => $owner1->id,
                'effective_at' => now()->subDay(),
            ]);

            // Courts for Venue 1-A
            // A code fits 50 characters and is unique per venue; each run has its own database, so no run id.
            $court1A1 = Court::create([
                'venue_id' => $venue1A->id,
                'code' => 'Court 1-A1',
                'status' => Court::STATUS_ACTIVE,
            ]);
            $court1A2 = Court::create([
                'venue_id' => $venue1A->id,
                'code' => 'Court 1-A2',
                'status' => Court::STATUS_ACTIVE,
            ]);
            // A whole-day normal table per venue, linked from a day ago so it prices any booking a test makes.
            $priceCourts = function (array $courts, int $pricePerHour) {
                $tables = app(PriceTableRepositoryInterface::class);
                $table = $tables->create(['venue_id' => $courts[0]->venue_id, 'name' => 'Bảng giá '.number_format($pricePerHour, 0, ',', '.').' ₫', 'kind' => PriceTable::KIND_NORMAL]);
                $tables->publishVersion($table, now()->subDay(), now()->subDay(), [
                    ['weekdays' => [1, 2, 3, 4, 5, 6, 7], 'dates' => null, 'start_minute' => 0, 'end_minute' => 1440, 'price_per_hour' => $pricePerHour],
                ]);
                $tables->createLinks($table, array_map(fn (Court $court): int => $court->id, $courts), now()->subDay());
            };
            $priceCourts([$court1A1, $court1A2], 100000);
            // Venue 1-B (Sibling Venue of Org 1)
            $venue1B = Venue::create([
                'organization_id' => $org1->id,
                'name' => "Aura Venue 1-B Sibling {$runId}",
                'slug' => "venue-1b-{$runId}",
                'address' => '456 Le Duan, District 3',
                'city' => 'Ho Chi Minh',
                'district' => 'District 3',
                'phone' => '0901000005',
                'email' => "venue1b_{$runId}@auracourt.test",
                'status' => Venue::STATUS_ACTIVE,
                'published_at' => now(),
            ]);

            // Receiving Connection for Venue 1-B
            $rc1B = ReceivingConnection::create([
                'organization_id' => $org1->id,
                'venue_id' => $venue1B->id,
                'owner_user_id' => $owner1->id,
                'provider' => 'sepay',
                'routing_id' => (string) Str::uuid(),
                'bank_code' => 'MBBank',
                'account_name_ciphertext' => Crypt::encryptString("AURA COURT 1B {$runId}"),
                'account_number_ciphertext' => Crypt::encryptString('0987654322'),
                'account_last_four' => '4322',
                'status' => ReceivingConnection::STATUS_VERIFIED,
                'webhook_secret_ciphertext' => Crypt::encryptString("whsec_e2e_1b_{$runId}"),
                'credential_version' => 1,
                'owner_attested_at' => now()->subDay(),
                'verified_at' => now(),
                'effective_from' => now()->subDay(),
            ]);
            $venue1B->update(['current_receiving_connection_id' => $rc1B->id]);
            VenueOperatingSchedule::create([
                'venue_id' => $venue1B->id,
                'scope_key' => 'venue',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'version' => 1,
                'weekly_hours' => $weeklyHours,
                'created_by' => $owner1->id,
            ]);

            VenueBookingPolicy::create([
                'venue_id' => $venue1B->id,
                'version' => 1,
                'settings' => $bookingPolicySettings,
                'created_by' => $owner1->id,
                'effective_at' => now()->subDay(),
            ]);

            $court1B1 = Court::create([
                'venue_id' => $venue1B->id,
                'code' => 'Court 1-B1',
                'status' => Court::STATUS_ACTIVE,
            ]);

            $priceCourts([$court1B1], 110000);

            // 3.5 POS Products for Org 1
            $prodDrink = PosProduct::create([
                'organization_id' => $org1->id,
                'sku' => "DRINK-{$runId}",
                'barcode' => "893000001{$runId}",
                'name' => "Aquafina 500ml {$runId}",
                'description' => 'Bottled mineral water',
                'price' => 15000,
                'cost' => 8000,
                'stock_quantity' => 50,
                'is_active' => true,
                'metadata' => [
                    'category' => 'drinks',
                    'unit' => 'chai',
                    'tax_rate' => 8,
                ],
            ]);
            $prodGear = PosProduct::create([
                'organization_id' => $org1->id,
                'sku' => "GEAR-{$runId}",
                'barcode' => "893000002{$runId}",
                'name' => "Yonex Mavis 350 {$runId}",
                'description' => 'Nylon shuttlecock',
                'price' => 50000,
                'cost' => 35000,
                'stock_quantity' => 20,
                'is_active' => true,
                'metadata' => [
                    'category' => 'equipment',
                    'unit' => 'ong',
                    'tax_rate' => 10,
                ],
            ]);
            $prodService = PosProduct::create([
                'organization_id' => $org1->id,
                'sku' => "SRV-{$runId}",
                'barcode' => "893000003{$runId}",
                'name' => "Racket Rental {$runId}",
                'description' => 'Hourly racket rental',
                'price' => 30000,
                'cost' => 0,
                'stock_quantity' => 999,
                'is_active' => true,
                'metadata' => [
                    'category' => 'services',
                    'unit' => 'gio',
                    'is_service' => true,
                    'tax_rate' => 10,
                ],
            ]);

            // -------------------------------------------------------------
            // 4. Organization 2 (Foreign Tenant)
            // -------------------------------------------------------------
            $org2 = $createOrganization([
                'name' => "Foreign Club {$runId}",
                'slug' => "foreign-club-{$runId}",
                'status' => 'active',
                'partner_status' => 'approved',
                'partner_status_changed_at' => now(),
                'settings' => [
                    'e2e_run_id' => $runId,
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'support_email' => "support_foreign_{$runId}@auracourt.test",
                    'phone' => '0901000009',
                ],
            ], 1);

            OrganizationMember::create([
                'organization_id' => $org2->id,
                'user_id' => $owner2->id,
                'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
                'status' => 'active',
            ]);

            HostKyc::create([
                'kyc_code' => "KYC-ORG2-{$runId}",
                'organization_id' => $org2->id,
                'user_id' => $owner2->id,
                'business_type' => 'company',
                'business_name' => "Foreign Club {$runId} Ltd",
                'legal_name' => "Foreign Club {$runId} Ltd",
                'tax_code' => "0309{$runId}",
                'tax_status' => 'verified',
                'representative_name' => $owner2->name,
                'id_card_number' => '079090000002',
                'bank_name' => 'MBBank',
                'bank_code' => 'MBBank',
                'bank_account_number' => '0901000009',
                'bank_account_holder' => 'FOREIGN CLUB LTD',
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'submitted_at' => now()->subDay(),
                'version' => 1,
            ]);
            PlatformSubscription::create([
                'organization_id' => $org2->id,
                'plan_id' => $plan->id,
                'plan_version' => 1,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'auto_renew' => true,
                'starts_at' => now()->subDays(5),
                'ends_at' => now()->addMonths(1),
                'price_snapshot' => 500000,
                'currency_snapshot' => 'VND',
            ]);

            $venue2 = Venue::create([
                'organization_id' => $org2->id,
                'name' => "Foreign Venue 2 {$runId}",
                'slug' => "venue-2-foreign-{$runId}",
                'address' => '789 Vo Van Kiet, District 5',
                'city' => 'Ho Chi Minh',
                'district' => 'District 5',
                'phone' => '0901000009',
                'email' => "venue2_{$runId}@auracourt.test",
                'status' => Venue::STATUS_ACTIVE,
                'published_at' => now(),
            ]);

            // Receiving Connection for Venue 2
            $rc2 = ReceivingConnection::create([
                'organization_id' => $org2->id,
                'venue_id' => $venue2->id,
                'owner_user_id' => $owner2->id,
                'provider' => 'sepay',
                'routing_id' => (string) Str::uuid(),
                'bank_code' => 'MBBank',
                'account_name_ciphertext' => Crypt::encryptString("FOREIGN COURT 2 {$runId}"),
                'account_number_ciphertext' => Crypt::encryptString('0987654323'),
                'account_last_four' => '4323',
                'status' => ReceivingConnection::STATUS_VERIFIED,
                'webhook_secret_ciphertext' => Crypt::encryptString("whsec_e2e_2_{$runId}"),
                'credential_version' => 1,
                'owner_attested_at' => now()->subDay(),
                'verified_at' => now(),
                'effective_from' => now()->subDay(),
            ]);
            $venue2->update(['current_receiving_connection_id' => $rc2->id]);
            VenueOperatingSchedule::create([
                'venue_id' => $venue2->id,
                'scope_key' => 'venue',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'version' => 1,
                'weekly_hours' => $weeklyHours,
                'created_by' => $owner2->id,
            ]);

            VenueBookingPolicy::create([
                'venue_id' => $venue2->id,
                'version' => 1,
                'settings' => $bookingPolicySettings,
                'created_by' => $owner2->id,
                'effective_at' => now()->subDay(),
            ]);

            $court2 = Court::create([
                'venue_id' => $venue2->id,
                'code' => 'Court 2-1',
                'status' => Court::STATUS_ACTIVE,
            ]);

            $priceCourts([$court2], 150000);
            // Output JSON payload for the runner
            $payload = [
                'run_id' => $runId,
                'platform_plan' => ['id' => $plan->id, 'code' => $plan->code],
                'users' => [
                    'admin' => ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'password' => $adminPassword],
                    'customer' => ['id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email, 'role' => 'customer', 'password' => $customerPassword],
                    'customer2' => ['id' => $customer2->id, 'name' => $customer2->name, 'email' => $customer2->email, 'role' => 'customer', 'password' => $customer2Password],
                    'unverifiedCustomer' => ['id' => $unverifiedCustomer->id, 'name' => $unverifiedCustomer->name, 'email' => $unverifiedCustomer->email, 'role' => 'customer', 'password' => $unverifiedPassword],
                    'restrictedCustomer' => ['id' => $restrictedCustomer->id, 'name' => $restrictedCustomer->name, 'email' => $restrictedCustomer->email, 'role' => 'customer', 'password' => $restrictedPassword],
                    'owner' => ['id' => $owner1->id, 'name' => $owner1->name, 'email' => $owner1->email, 'role' => 'organization_owner', 'password' => $ownerPassword],
                    'manager' => ['id' => $manager1->id, 'name' => $manager1->name, 'email' => $manager1->email, 'role' => 'staff', 'password' => $managerPassword],
                    'cashier' => ['id' => $cashier1->id, 'name' => $cashier1->name, 'email' => $cashier1->email, 'role' => 'staff', 'password' => $cashierPassword],
                    'staff' => ['id' => $staff1->id, 'name' => $staff1->name, 'email' => $staff1->email, 'role' => 'staff', 'password' => $staffPassword],
                    'foreignOwner' => ['id' => $owner2->id, 'name' => $owner2->name, 'email' => $owner2->email, 'role' => 'organization_owner', 'password' => $foreignOwnerPassword],
                ],
                'organizations' => [
                    'org1' => [
                        'id' => $org1->id,
                        'name' => $org1->name,
                        'slug' => $org1->slug,
                        'venue1A' => ['id' => $venue1A->id, 'name' => $venue1A->name, 'slug' => $venue1A->slug, 'receivingConnectionId' => $rc1->id, 'routingId' => $rc1->routing_id, 'courts' => [$court1A1->id, $court1A2->id]],
                        'venue1B' => ['id' => $venue1B->id, 'name' => $venue1B->name, 'slug' => $venue1B->slug, 'receivingConnectionId' => $rc1B->id, 'routingId' => $rc1B->routing_id, 'courts' => [$court1B1->id]],
                        'products' => [
                            'drink' => ['id' => $prodDrink->id, 'sku' => $prodDrink->sku, 'name' => $prodDrink->name, 'price' => $prodDrink->price, 'stock' => $prodDrink->stock_quantity],
                            'gear' => ['id' => $prodGear->id, 'sku' => $prodGear->sku, 'name' => $prodGear->name, 'price' => $prodGear->price, 'stock' => $prodGear->stock_quantity],
                            'service' => ['id' => $prodService->id, 'sku' => $prodService->sku, 'name' => $prodService->name, 'price' => $prodService->price, 'stock' => $prodService->stock_quantity],
                        ],
                    ],
                    'org2' => [
                        'id' => $org2->id,
                        'name' => $org2->name,
                        'slug' => $org2->slug,
                        'venue2' => ['id' => $venue2->id, 'name' => $venue2->name, 'slug' => $venue2->slug, 'receivingConnectionId' => $rc2->id, 'routingId' => $rc2->routing_id, 'courts' => [$court2->id]],
                    ],
                ],
            ];

            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        });
    }
}
