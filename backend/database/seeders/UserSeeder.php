<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $org = Organization::where('slug', 'aura-badminton-club')->first() ?? Organization::first();

        // 1. Root Admin
        $admin = User::firstOrNew(['email' => 'admin@auracourt.vn']);
        $admin->forceFill([
            'name' => 'Aura Administrator',
            'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'Admin@123456')),
            'role' => 'admin',
            'phone' => '0988888888',
            'play_level' => 'advanced',
            'onboarded' => true,
            'password_set' => true,
            'email_verified_at' => $now,
        ])->save();

        if ($org) {
            OrganizationMember::updateOrCreate(
                ['organization_id' => $org->id, 'user_id' => $admin->id],
                ['role' => 'organization_owner', 'status' => 'active', 'permissions' => ['*']]
            );
        }

        // 2. Admin Example
        $adminExample = User::firstOrNew(['email' => env('SEED_ADMIN_EMAIL', 'admin@example.com')]);
        $adminExample->forceFill([
            'name' => 'Quản trị viên',
            'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'Admin@123456')),
            'role' => 'admin',
            'phone' => '0912345678',
            'play_level' => 'advanced',
            'onboarded' => true,
            'password_set' => true,
            'email_verified_at' => $now,
        ])->save();

        if ($org) {
            OrganizationMember::updateOrCreate(
                ['organization_id' => $org->id, 'user_id' => $adminExample->id],
                ['role' => 'organization_owner', 'status' => 'active', 'permissions' => ['*']]
            );
        }

        // 2b. Zịt Admin (soicuteeee@gmail.com)
        $soicuteUser = User::firstOrNew(['email' => 'soicuteeee@gmail.com']);
        $soicuteUser->forceFill([
            'name' => 'Zịt',
            'password' => Hash::make('Admin@123456'),
            'role' => 'admin',
            'phone' => '0987654321',
            'play_level' => 'advanced',
            'onboarded' => true,
            'password_set' => true,
            'email_verified_at' => $now,
        ])->save();

        if ($org) {
            OrganizationMember::updateOrCreate(
                ['organization_id' => $org->id, 'user_id' => $soicuteUser->id],
                ['role' => 'organization_owner', 'status' => 'active', 'permissions' => ['*']]
            );
        }

        // Seed 1,000,000,000 VND into soicuteeee wallet
        $walletService = app(\App\Services\Payment\WalletPaymentService::class);
        $soicuteWallet = $walletService->getOrCreateWallet($soicuteUser->id, $org?->id);
        if ($soicuteWallet->balance < 1000000000) {
            $walletService->credit($soicuteWallet, 1000000000 - $soicuteWallet->balance, 'manual_seed', 1, 'Admin nạp 1 tỷ đồng khởi tạo');
        }

        // 3. Demo Admin
        $demoAdmin = User::firstOrNew([
            'email' => env('SEED_DEMO_ADMIN_EMAIL', 'demo-20260811@demo.auracourt.vn'),
        ]);
        $demoAdmin->forceFill([
            'name' => 'Quản trị demo',
            'password' => Hash::make(env('SEED_DEMO_ADMIN_PASSWORD', 'Admin@123456')),
            'role' => 'admin',
            'onboarded' => true,
            'password_set' => true,
            'email_verified_at' => $now,
        ])->save();

        if ($org) {
            OrganizationMember::updateOrCreate(
                ['organization_id' => $org->id, 'user_id' => $demoAdmin->id],
                ['role' => 'organization_owner', 'status' => 'active', 'permissions' => ['*']]
            );
        }

        // 4. Demo Customer
        $demoUser = User::firstOrNew(['email' => 'user@example.com']);
        $demoUser->forceFill([
            'name' => 'Khách hàng Demo',
            'password' => Hash::make('Password@123456'),
            'role' => 'customer',
            'phone' => '0901234567',
            'play_level' => 'intermediate',
            'onboarded' => true,
            'password_set' => true,
            'email_verified_at' => $now,
        ])->save();

        if ($org) {
            OrganizationMember::updateOrCreate(
                ['organization_id' => $org->id, 'user_id' => $demoUser->id],
                ['role' => 'customer', 'status' => 'active']
            );
        }

        // 5. 24 Customer Accounts
        collect(range(1, 24))->each(function (int $number) use ($now, $org): void {
            $user = User::firstOrNew(['email' => sprintf('khach%02d@demo.auracourt.vn', $number)]);
            $user->forceFill([
                'name' => sprintf('Khách hàng %02d', $number),
                'password' => Hash::make('Demo12345!'),
                'role' => 'customer',
                'email_verified_at' => $now->copy()->subDays($number),
            ])->save();

            if ($org) {
                OrganizationMember::updateOrCreate(
                    ['organization_id' => $org->id, 'user_id' => $user->id],
                    ['role' => 'customer', 'status' => 'active']
                );
            }
        });

        // 6. Demo Scoped Owner & Cashier
        $demoOwner = User::firstOrNew(['email' => 'owner@demo.auracourt.vn']);
        $demoOwner->forceFill([
            'name' => 'Chủ sân Demo',
            'password' => Hash::make(env('SEED_DEMO_PASSWORD', 'Demo12345!')),
            'role' => 'customer',
            'phone' => '0902222333',
            'play_level' => 'advanced',
            'onboarded' => true,
            'password_set' => true,
            'email_verified_at' => $now,
        ])->save();

        if ($org) {
            OrganizationMember::updateOrCreate(
                ['organization_id' => $org->id, 'user_id' => $demoOwner->id],
                ['role' => 'organization_owner', 'status' => 'active', 'permissions' => ['*']]
            );
        }

        $demoCashier = User::firstOrNew(['email' => 'cashier@demo.auracourt.vn']);
        $demoCashier->forceFill([
            'name' => 'Thu ngân Demo',
            'password' => Hash::make(env('SEED_DEMO_PASSWORD', 'Demo12345!')),
            'role' => 'customer',
            'phone' => '0903333444',
            'play_level' => 'intermediate',
            'onboarded' => true,
            'password_set' => true,
            'email_verified_at' => $now,
        ])->save();

        if ($org) {
            OrganizationMember::updateOrCreate(
                ['organization_id' => $org->id, 'user_id' => $demoCashier->id],
                ['role' => 'cashier', 'status' => 'active', 'permissions' => ['pos.sell', 'pos.shift']]
            );
        }
    }
}
