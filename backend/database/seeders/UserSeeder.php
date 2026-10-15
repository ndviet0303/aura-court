<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\ReceivingConnection;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;

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
        $this->demoPassword = $this->hashedPassword('SEED_DEMO_PASSWORD');
        $adminPassword = $this->hashedPassword('SEED_ADMIN_PASSWORD');
        // Read before any write, so a missing value aborts the seed with nothing half-created.
        $receiver = $this->receiver();

        $org = Organization::where('slug', 'aura-badminton-club')->firstOrFail();
        $partner = Organization::where('slug', 'hoang-gia-sports')->firstOrFail();
        $caugiay = Venue::where('slug', 'aura-court')->firstOrFail();
        $caugiayScope = ['venue_id' => $caugiay->id, 'venue_ids' => [$caugiay->id]];

        // 1. Platform admins. They also own the flagship club so they can run owner-only flows.
        $admins = [
            ['admin@auracourt.vn', 'Quản trị hệ thống Aura', '0988888888', $adminPassword],
            [env('SEED_ADMIN_EMAIL', 'admin@example.com'), 'Quản trị viên', '0912345678', $adminPassword],
            [env('SEED_DEMO_ADMIN_EMAIL', 'demo-20260811@demo.auracourt.vn'), 'Quản trị demo', null, $this->hashedPassword('SEED_DEMO_ADMIN_PASSWORD')],
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

        // 2. Flagship club owner and staff; staff share one permission set and differ only by venue.
        $owner = $this->user('owner@demo.auracourt.vn', ['name' => 'Trần Quốc Bảo', 'phone' => '0902222333', 'play_level' => 'advanced'], 75);
        $this->member($org, $owner, OrganizationMember::ROLE_ORGANIZATION_OWNER, ['*']);

        $manager = $this->user('manager@demo.auracourt.vn', ['name' => 'Nguyễn Hữu Thắng', 'phone' => '0906111222', 'play_level' => 'advanced'], 52);
        // Staff reach only the venues they are assigned, so "every venue" is spelled out.
        $orgVenueIds = Venue::where('organization_id', $org->id)->orderBy('id')->pluck('id')->all();
        $this->member($org, $manager, OrganizationMember::ROLE_STAFF, ['venue_id' => $orgVenueIds[0] ?? null, 'venue_ids' => $orgVenueIds]);

        $venueManager = $this->user('manager.caugiay@demo.auracourt.vn', ['name' => 'Đỗ Minh Khoa', 'phone' => '0906333444', 'play_level' => 'intermediate'], 49);
        $this->member($org, $venueManager, OrganizationMember::ROLE_STAFF, $caugiayScope);

        $staff = $this->user('staff@demo.auracourt.vn', ['name' => 'Vũ Đức Thịnh', 'phone' => '0906555666', 'play_level' => 'intermediate'], 46);
        $this->member($org, $staff, OrganizationMember::ROLE_STAFF, $caugiayScope);

        $cashier = $this->user('cashier@demo.auracourt.vn', ['name' => 'Ngô Thị Lan', 'phone' => '0903333444', 'play_level' => 'beginner'], 43);
        $this->member($org, $cashier, OrganizationMember::ROLE_STAFF, $caugiayScope);

        // 3. Owner of the second tenant, used to show tenant isolation.
        $partnerOwner = $this->user('owner.hoanggia@demo.auracourt.vn', ['name' => 'Lâm Gia Huy', 'phone' => '0909777888', 'play_level' => 'advanced'], 50);
        $this->member($partner, $partnerOwner, OrganizationMember::ROLE_ORGANIZATION_OWNER, ['*']);

        // 4. Customers. They pay venue owners directly by bank transfer, so no wallet balance is seeded.
        $demoUser = $this->user('user@example.com', [
            'name' => 'Nguyễn Minh Anh',
            'phone' => '0901234567',
            'play_level' => 'intermediate',
        ], 120);
        $this->member($org, $demoUser, OrganizationMember::ROLE_CUSTOMER);

        foreach (self::CUSTOMER_NAMES as $index => $name) {
            $number = $index + 1;
            $customer = $this->user(sprintf('khach%02d@demo.auracourt.vn', $number), [
                'name' => $name,
                'phone' => sprintf('09%08d', 11000000 + $number * 1137),
                'play_level' => self::PLAY_LEVELS[$index % 3],
            ], 30 + 3 * $number);
            $this->member($org, $customer, OrganizationMember::ROLE_CUSTOMER);
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

        // 7. Verified SePay connections the owners attested for each venue, all on the one configured demo account.
        // Only the Cầu Giấy connection carries the webhook key registered in SePay, so only it auto-confirms payments.
        foreach (Venue::whereIn('organization_id', [$org->id, $partner->id])->orderBy('id')->get() as $venue) {
            $this->receivingConnection(
                $venue,
                $venue->organization_id === $partner->id ? $partnerOwner : $owner,
                $receiver,
                $venue->is($caugiay) ? $receiver['webhook_key'] : null,
            );
        }
        $this->command?->info('SePay webhook URL for Cầu Giấy: '.route('webhooks.sepay.connection', $caugiay->fresh()->currentReceivingConnection->routing_id));

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

    /**
     * @param  array{bank: string, account: string, name: string, webhook_key: string}  $receiver
     * @param  string|null  $webhookKey  the key registered in SePay; null gives the connection a random key no webhook knows
     */
    private function receivingConnection(Venue $venue, User $owner, array $receiver, ?string $webhookKey): void
    {
        $connection = ReceivingConnection::firstOrNew(['venue_id' => $venue->id, 'status' => ReceivingConnection::STATUS_VERIFIED]);
        if (! $connection->exists) {
            $attestedAt = $this->now->copy()->subMonths(4)->setTime(9, 30);
            $connection->forceFill([
                'organization_id' => $venue->organization_id,
                'owner_user_id' => $owner->id,
                'provider' => 'sepay',
                'routing_id' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'auracourt-demo/'.$venue->slug)->toString(),
                'webhook_secret_ciphertext' => Crypt::encryptString($webhookKey ?? Str::random(40)),
                'credential_version' => 1,
                'owner_attested_at' => $attestedAt,
                'verified_at' => $attestedAt,
                'effective_from' => $attestedAt,
                'created_at' => $attestedAt,
            ]);
        } elseif ($webhookKey !== null && $connection->webhookSecret() !== $webhookKey) {
            $connection->forceFill([
                'webhook_secret_ciphertext' => Crypt::encryptString($webhookKey),
                'credential_version' => $connection->credential_version + 1,
            ]);
        }
        $connection->forceFill([
            'bank_code' => $receiver['bank'],
            'account_name_ciphertext' => Crypt::encryptString($receiver['name']),
            'account_number_ciphertext' => Crypt::encryptString($receiver['account']),
            'account_last_four' => substr($receiver['account'], -4),
        ])->save();
        $venue->update(['current_receiving_connection_id' => $connection->id]);
    }

    /**
     * The real demo bank account and Cầu Giấy webhook key come only from the environment. Outside local/testing a
     * missing or malformed value aborts the seed; locally the connections get an obviously fake account and a random key.
     *
     * @return array{bank: string, account: string, name: string, webhook_key: string}
     */
    private function receiver(): array
    {
        $keys = [
            'bank' => ['SEED_DEMO_RECEIVER_BANK', '/^[A-Za-z0-9_-]{1,32}$/', 'TPBank'],
            'account' => ['SEED_DEMO_RECEIVER_ACCOUNT', '/^\d{4,34}$/', '0000000000'],
            'name' => ['SEED_DEMO_RECEIVER_NAME', '/^.{1,160}$/u', 'AURA COURT DEMO'],
            'webhook_key' => ['SEED_DEMO_RECEIVER_WEBHOOK_KEY', '/^[A-Za-z0-9_-]{32,512}$/', null],
        ];
        $receiver = [];
        foreach ($keys as $field => [$key, $pattern, $fallback]) {
            $value = trim((string) env($key));
            if (preg_match($pattern, $value) !== 1) {
                if (! app()->environment('local', 'testing')) {
                    throw new RuntimeException("{$key} is not set or malformed. Seeding with APP_ENV=".app()->environment().' requires SEED_DEMO_RECEIVER_BANK, SEED_DEMO_RECEIVER_ACCOUNT, SEED_DEMO_RECEIVER_NAME and SEED_DEMO_RECEIVER_WEBHOOK_KEY (see backend/.env.example); there is no committed default.');
                }
                $value = $fallback ?? Str::random(40);
            }
            $receiver[$field] = $value;
        }

        return $receiver;
    }

    /**
     * Seeded accounts never fall back to a committed password: outside local/testing a missing
     * value aborts the seed; locally the accounts get a random password until the key is set.
     */
    private function hashedPassword(string $key): string
    {
        $password = env($key);

        if (blank($password)) {
            if (! app()->environment('local', 'testing')) {
                throw new RuntimeException("{$key} is not set. Seeding accounts with APP_ENV=".app()->environment().' requires SEED_ADMIN_PASSWORD, SEED_DEMO_ADMIN_PASSWORD and SEED_DEMO_PASSWORD (see backend/.env.example); there is no committed default.');
            }

            $password = Str::password();
        }

        return Hash::make($password);
    }
}
