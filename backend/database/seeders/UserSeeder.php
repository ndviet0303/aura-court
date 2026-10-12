<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\ReceivingConnection;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Demo accounts for every platform and organization role.
 * docs/demo-accounts.md lists them for presenters; keep the two in sync.
 */
class UserSeeder extends Seeder
{
    private const CUSTOMER_NAMES = [
        'Nguyễn Văn Nam', 'Trần Thị Mai', 'Lê Hoàng Long', 'Phạm Ngọc Ánh', 'Hoàng Đức Minh', 'Vũ Thị Hồng Nhung',
        'Đặng Quang Huy', 'Bùi Thu Trang', 'Đỗ Văn Khánh', 'Ngô Thanh Hương', 'Dương Minh Tuấn', 'Lý Kim Ngân',
        'Phan Văn Đức', 'Trịnh Lan Anh', 'Võ Thành Đạt', 'Đinh Thu Hà', 'Mai Xuân Trường', 'Hồ Phương Linh',
        'Tạ Quốc Việt', 'Chu Bảo Ngọc', 'Lương Văn Hải', 'Cao Diễm My', 'Kiều Minh Quân', 'Tô Yến Nhi',
    ];

    private const PLAY_LEVELS = ['beginner', 'intermediate', 'advanced'];

    private Carbon $now;

    private string $demoPassword;

    public function run(): void
    {
        $this->now = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'));
        $this->demoPassword = Hash::make(env('SEED_DEMO_PASSWORD', 'Demo12345!'));
        $adminPassword = Hash::make(env('SEED_ADMIN_PASSWORD', 'Admin@123456'));

        $org = Organization::where('slug', 'aura-badminton-club')->firstOrFail();
        $partner = Organization::where('slug', 'hoang-gia-sports')->firstOrFail();
        $caugiay = Venue::where('slug', 'aura-court')->firstOrFail();
        $caugiayScope = ['venue_id' => $caugiay->id, 'venue_ids' => [$caugiay->id]];

        // 1. Platform admins. They also own the flagship club so they can run owner-only flows.
        $admins = [
            ['admin@auracourt.vn', 'Quản trị hệ thống Aura', '0988888888', $adminPassword],
            [env('SEED_ADMIN_EMAIL', 'admin@example.com'), 'Quản trị viên', '0912345678', $adminPassword],
            ['soicuteeee@gmail.com', 'Zịt', '0987654321', Hash::make('Admin@123456')],
            [env('SEED_DEMO_ADMIN_EMAIL', 'demo-20260811@demo.auracourt.vn'), 'Quản trị demo', null, Hash::make(env('SEED_DEMO_ADMIN_PASSWORD', 'Admin@123456'))],
        ];
        foreach ($admins as [$email, $name, $phone, $password]) {
            $admin = $this->user($email, [
                'name' => $name,
                'password' => $password,
                'role' => 'admin',
                'phone' => $phone,
                'play_level' => 'advanced',
            ], 180);
            $this->member($org, $admin, OrganizationMember::ROLE_ORGANIZATION_OWNER, ['*']);
        }

        $walletService = app(WalletPaymentService::class);
        $soicute = User::where('email', 'soicuteeee@gmail.com')->firstOrFail();
        $this->topUp($walletService, $soicute, $org, 1000000000, 1, 'Admin nạp 1 tỷ đồng khởi tạo');

        // 2. Flagship club staff, from owner down to a cashier limited to the Cầu Giấy venue.
        $owner = $this->user('owner@demo.auracourt.vn', ['name' => 'Trần Quốc Bảo', 'phone' => '0902222333', 'play_level' => 'advanced'], 75);
        $this->member($org, $owner, OrganizationMember::ROLE_ORGANIZATION_OWNER, ['*']);

        $manager = $this->user('manager@demo.auracourt.vn', ['name' => 'Nguyễn Hữu Thắng', 'phone' => '0906111222', 'play_level' => 'advanced'], 52);
        $this->member($org, $manager, OrganizationMember::ROLE_VENUE_MANAGER);

        $venueManager = $this->user('manager.caugiay@demo.auracourt.vn', ['name' => 'Đỗ Minh Khoa', 'phone' => '0906333444', 'play_level' => 'intermediate'], 49);
        $this->member($org, $venueManager, OrganizationMember::ROLE_VENUE_MANAGER, $caugiayScope);

        $staff = $this->user('staff@demo.auracourt.vn', ['name' => 'Vũ Đức Thịnh', 'phone' => '0906555666', 'play_level' => 'intermediate'], 46);
        $this->member($org, $staff, OrganizationMember::ROLE_STAFF, $caugiayScope);

        $cashier = $this->user('cashier@demo.auracourt.vn', ['name' => 'Ngô Thị Lan', 'phone' => '0903333444', 'play_level' => 'beginner'], 43);
        $this->member($org, $cashier, OrganizationMember::ROLE_CASHIER, $caugiayScope);

        // 3. Owner of the second tenant, used to show tenant isolation.
        $partnerOwner = $this->user('owner.hoanggia@demo.auracourt.vn', ['name' => 'Lâm Gia Huy', 'phone' => '0909777888', 'play_level' => 'advanced'], 50);
        $this->member($partner, $partnerOwner, OrganizationMember::ROLE_ORGANIZATION_OWNER, ['*']);

        // 4. Customers with wallet balance so wallet checkout works out of the box.
        $demoUser = $this->user('user@example.com', [
            'name' => 'Nguyễn Minh Anh',
            'password' => Hash::make('Password@123456'),
            'phone' => '0901234567',
            'play_level' => 'intermediate',
        ], 120);
        $this->member($org, $demoUser, OrganizationMember::ROLE_CUSTOMER);
        $this->topUp($walletService, $demoUser, $org, 5000000);

        foreach (self::CUSTOMER_NAMES as $index => $name) {
            $number = $index + 1;
            $customer = $this->user(sprintf('khach%02d@demo.auracourt.vn', $number), [
                'name' => $name,
                'phone' => sprintf('09%08d', 11000000 + $number * 1137),
                'play_level' => self::PLAY_LEVELS[$index % 3],
            ], 30 + 3 * $number);
            $this->member($org, $customer, OrganizationMember::ROLE_CUSTOMER);
            $this->topUp($walletService, $customer, $org, 2000000 + ($number % 4) * 500000);
        }

        $referee = $this->user('referee@demo.auracourt.vn', ['name' => 'Hoàng Văn Trọng', 'phone' => '0907888999', 'play_level' => 'advanced'], 60);
        $this->member($org, $referee, OrganizationMember::ROLE_CUSTOMER);

        // 5. Account states: not onboarded, email not verified, suspended.
        $this->user('newbie@demo.auracourt.vn', ['name' => 'Phạm Gia Bảo', 'onboarded' => false, 'play_level' => null]);
        $this->user('unverified@demo.auracourt.vn', ['name' => 'Đoàn Thị Hạnh', 'phone' => '0904444555', 'play_level' => 'beginner', 'email_verified_at' => null], 2);
        $this->user('restricted@demo.auracourt.vn', ['name' => 'Phùng Văn Lực', 'phone' => '0905555666', 'play_level' => 'beginner', 'status' => 'suspended'], 35);

        // 6. Partner applicants. HostKycSeeder attaches one application to each.
        $applicants = [
            ['partner.draft@demo.auracourt.vn', 'Bùi Thanh Tâm', '0908100200', 3],
            ['partner.pending@demo.auracourt.vn', 'Nguyễn Văn An', '0908300400', 6],
            ['partner.revision@demo.auracourt.vn', 'Trần Thị Bích', '0908500600', 5],
            ['partner.flagged@demo.auracourt.vn', 'Lê Hoàng Phúc', '0908700800', 4],
        ];
        foreach ($applicants as [$email, $name, $phone, $ageDays]) {
            $this->user($email, ['name' => $name, 'phone' => $phone, 'play_level' => 'intermediate'], $ageDays);
        }

        // 7. Verified bank accounts the owners attested for each venue (needed for direct owner payments and resale payouts).
        foreach (Venue::whereIn('organization_id', [$org->id, $partner->id])->orderBy('id')->get() as $venue) {
            $isPartner = $venue->organization_id === $partner->id;
            $this->receivingConnection(
                $venue,
                $isPartner ? $partnerOwner : $owner,
                $isPartner ? 'CONG TY TNHH THE THAO HOANG GIA' : 'CONG TY TNHH AURA SPORTS',
                ($isPartner ? '190366889900' : '045100012').str_pad((string) $venue->id, 4, '0', STR_PAD_LEFT),
            );
        }

        // 8. A staff invitation still waiting to be accepted.
        OrganizationInvitation::updateOrCreate(
            ['organization_id' => $org->id, 'email' => 'newstaff@demo.auracourt.vn', 'status' => OrganizationInvitation::STATUS_PENDING],
            [
                'invited_by' => $owner->id,
                'role' => OrganizationMember::ROLE_STAFF,
                'venue_id' => $caugiay->id,
                'venue_ids' => [$caugiay->id],
                'permissions' => $caugiayScope,
                'token' => Str::random(64),
                'expires_at' => $this->now->copy()->addDays(7),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  int  $ageDays  how long ago the account signed up, so admin lists and histories read naturally
     */
    private function user(string $email, array $attributes, int $ageDays = 0): User
    {
        $joinedAt = $this->now->copy()->subDays($ageDays);
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill($attributes + [
            'created_at' => $joinedAt,
            'password' => $this->demoPassword,
            'role' => 'customer',
            'status' => 'active',
            'onboarded' => true,
            'password_set' => true,
            'email_verified_at' => $joinedAt,
        ])->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $permissions
     */
    private function member(Organization $org, User $user, string $role, array $permissions = []): void
    {
        OrganizationMember::updateOrCreate(
            ['organization_id' => $org->id, 'user_id' => $user->id],
            ['role' => $role, 'status' => 'active', 'permissions' => $permissions ?: null, 'created_at' => $user->created_at->max($org->created_at)]
        );
    }

    private function topUp(WalletPaymentService $wallets, User $user, Organization $org, int $target, ?int $sourceId = null, string $reference = 'Nạp ví demo'): void
    {
        $wallet = $wallets->getOrCreateWallet($user->id, $org->id);
        if ($wallet->balance < $target) {
            $wallets->credit($wallet, $target - $wallet->balance, 'manual_seed', $sourceId ?? $user->id, $reference);
        }
    }

    private function receivingConnection(Venue $venue, User $owner, string $accountName, string $accountNumber): void
    {
        $connection = ReceivingConnection::firstOrNew(['venue_id' => $venue->id, 'status' => ReceivingConnection::STATUS_VERIFIED]);
        if (! $connection->exists) {
            $connection->forceFill([
                'organization_id' => $venue->organization_id,
                'owner_user_id' => $owner->id,
                'provider' => 'sepay',
                'routing_id' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'auracourt-demo/'.$venue->slug)->toString(),
                'bank_code' => 'MBBank',
                'account_name_ciphertext' => Crypt::encryptString($accountName),
                'account_number_ciphertext' => Crypt::encryptString($accountNumber),
                'account_last_four' => substr($accountNumber, -4),
                'webhook_secret_ciphertext' => Crypt::encryptString('whsec_'.Str::random(32)),
                'credential_version' => 1,
                'owner_attested_at' => $this->now->copy()->subMonth(),
                'verified_at' => $this->now->copy()->subMonth(),
                'effective_from' => $this->now->copy()->subMonth(),
            ])->save();
        }
        $venue->update(['current_receiving_connection_id' => $connection->id]);
    }
}
