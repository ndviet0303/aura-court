<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Models\VenueOperatingSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OrganizationVenueSeeder extends Seeder
{
    public function run(): void
    {
        $settings = ['currency' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh'];
        // Approval dates match the partners' KYC reviews in HostKycSeeder.
        $now = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'));
        $orgApprovedAt = $now->copy()->subMonths(2)->addDays(2);
        $partnerApprovedAt = $now->copy()->subMonth()->subDays(8);

        $org = Organization::updateOrCreate(
            ['slug' => 'aura-badminton-club'],
            [
                'name' => 'Aura Sports Marketplace Club',
                'tax_code' => '0109876543',
                'status' => 'active',
                'partner_status' => 'approved',
                'partner_status_changed_at' => $orgApprovedAt,
                'settings' => $settings,
                'created_at' => $orgApprovedAt,
            ]
        );

        // The venues migration leaves a legacy org 'aura-court' behind; reuse it as the second tenant.
        $partner = Organization::firstWhere('slug', 'hoang-gia-sports')
            ?? Organization::firstWhere('slug', 'aura-court')
            ?? new Organization;
        $partner->forceFill([
            'name' => 'Hoàng Gia Sports',
            'slug' => 'hoang-gia-sports',
            'tax_code' => '0318765432',
            'status' => 'active',
            'partner_status' => 'approved',
            'partner_status_changed_at' => $partnerApprovedAt,
            'settings' => $settings,
            'created_at' => $partnerApprovedAt,
        ])->save();

        $growth = PlatformPlan::updateOrCreate(
            ['code' => 'PLATFORM_GROWTH'],
            [
                'name' => 'Gói Phát Triển',
                'price' => 500000,
                'billing_cycle' => 'monthly',
                // Column defaults (1 venue / 4 courts) are below what this seeder creates.
                'max_venues' => 10,
                'max_courts' => 60,
                'max_staff' => 20,
                'status' => 'published',
                'published_at' => now()->subMonths(3),
                'is_active' => true,
            ]
        );

        $starter = PlatformPlan::updateOrCreate(
            ['code' => 'PLATFORM_STARTER'],
            [
                'name' => 'Gói Khởi Đầu',
                'price' => 200000,
                'billing_cycle' => 'monthly',
                'max_venues' => 2,
                'max_courts' => 10,
                'max_staff' => 5,
                'status' => 'published',
                'published_at' => now()->subMonths(3),
                'is_active' => true,
            ]
        );

        foreach ([[$org, $growth], [$partner, $starter]] as [$tenant, $plan]) {
            PlatformSubscription::updateOrCreate(
                ['organization_id' => $tenant->id],
                [
                    'plan_id' => $plan->id,
                    'plan_version' => 1,
                    'status' => 'active',
                    'billing_cycle' => 'monthly',
                    'auto_renew' => true,
                    'starts_at' => now()->subMonths(1),
                    'ends_at' => now()->addYear(),
                    'price_snapshot' => $plan->price,
                    'currency_snapshot' => 'VND',
                ]
            );
        }

        $venuesData = [
            [
                'org' => $org,
                'slug' => 'aura-court',
                'name' => 'Aura Court Cầu Giấy',
                'address' => 'Số 18 Phố Duy Tân, Phường Dịch Vọng Hậu, Quận Cầu Giấy, Hà Nội',
                'google_maps_url' => 'https://maps.google.com/?q=18+Duy+Tan+Dich+Vong+Hau+Cau+Giay+Ha+Noi',
                'city' => 'Hà Nội',
                'district' => 'Cầu Giấy',
                'ward' => 'Dịch Vọng Hậu',
                'phone' => '0243 795 1188',
                'description' => 'Cụm sân trong nhà gần các tòa văn phòng Duy Tân: cầu lông thảm BWF, pickleball và bóng bàn, mở cửa từ 6h đến 23h.',
                'amenities' => ['parking', 'wifi', 'shower', 'shop'],
            ],
            [
                'org' => $org,
                'slug' => 'aura-arena-quan-ngua',
                'name' => 'Aura Arena Quần Ngựa',
                'address' => 'Cung Thể Thao Quần Ngựa, 30 Đường Văn Cao, Phường Liễu Giai, Quận Ba Đình, Hà Nội',
                'google_maps_url' => 'https://maps.google.com/?q=Cung+The+Thao+Quan+Ngua+30+Van+Cao+Ba+Dinh+Ha+Noi',
                'city' => 'Hà Nội',
                'district' => 'Ba Đình',
                'ward' => 'Liễu Giai',
                'phone' => '0243 762 2299',
                'description' => 'Tổ hợp trong Cung Thể Thao Quần Ngựa với sân cầu lông có khán đài, sân bóng đá 5 và 7 người, phòng bóng bàn máy lạnh.',
                'amenities' => ['parking', 'shower', 'cafe', 'lounge'],
            ],
            [
                'org' => $org,
                'slug' => 'aura-complex-tay-ho',
                'name' => 'Aura Sports Complex Tây Hồ',
                'address' => 'Số 68 Phố Quảng Bá, Phường Quảng An, Quận Tây Hồ, Hà Nội',
                'google_maps_url' => 'https://maps.google.com/?q=68+Quang+Ba+Tay+Ho+Ha+Noi',
                'city' => 'Hà Nội',
                'district' => 'Tây Hồ',
                'ward' => 'Quảng An',
                'phone' => '0243 718 3366',
                'description' => 'Khu thể thao ven Hồ Tây: tennis đất nện và sân cứng, pickleball ngoài trời, bóng rổ đường phố.',
                'amenities' => ['parking', 'wifi', 'cafe', 'lounge'],
            ],
            [
                'org' => $org,
                'slug' => 'aura-landmark-72',
                'name' => 'Aura Multi-Sport Landmark 72',
                'address' => 'Tòa Tháp Keangnam Landmark 72, Đường Phạm Hùng, Quận Nam Từ Liêm, Hà Nội',
                'google_maps_url' => 'https://maps.google.com/?q=Keangnam+Landmark+72+Pham+Hung+Ha+Noi',
                'city' => 'Hà Nội',
                'district' => 'Nam Từ Liêm',
                'ward' => 'Mễ Trì',
                'phone' => '0243 555 7272',
                'description' => 'Sân trong nhà điều hòa trung tâm tại Keangnam: bóng rổ sàn gỗ, tennis mặt cứng, bóng chuyền và cầu lông cao cấp.',
                'amenities' => ['parking', 'wifi', 'shower', 'cafe', 'shop', 'lounge'],
            ],
            [
                'org' => $org,
                'slug' => 'aura-central-saigon',
                'name' => 'Aura Sports Center Sài Gòn',
                'address' => 'Số 120 Đường Điện Biên Phủ, Phường 25, Quận Bình Thạnh, TP. Hồ Chí Minh',
                'google_maps_url' => 'https://maps.google.com/?q=120+Dien+Bien+Phu+Binh+Thanh+Ho+Chi+Minh',
                'city' => 'TP. Hồ Chí Minh',
                'district' => 'Bình Thạnh',
                'ward' => 'Phường 25',
                'phone' => '0283 512 0120',
                'description' => 'Cơ sở đầu tiên của Aura tại TP. Hồ Chí Minh: cầu lông, bóng đá mini và pickleball tầng thượng.',
                'amenities' => ['parking', 'wifi', 'shower', 'cafe'],
            ],
            [
                'org' => $partner,
                'slug' => 'hoang-gia-phu-my-hung',
                'name' => 'Hoàng Gia Sports Phú Mỹ Hưng',
                'address' => 'Số 15 Đường Nguyễn Lương Bằng, Phường Tân Phú, Quận 7, TP. Hồ Chí Minh',
                'google_maps_url' => 'https://maps.google.com/?q=15+Nguyen+Luong+Bang+Tan+Phu+Quan+7+Ho+Chi+Minh',
                'city' => 'TP. Hồ Chí Minh',
                'district' => 'Quận 7',
                'ward' => 'Tân Phú',
                'phone' => '0287 300 1515',
                'description' => 'Đối tác độc lập tại Phú Mỹ Hưng với sân pickleball, tennis và bóng đá mini cho cư dân khu Nam Sài Gòn.',
                'amenities' => ['parking', 'shower', 'cafe'],
            ],
        ];

        $weeklyHours = [];
        for ($day = 1; $day <= 7; $day++) {
            $weeklyHours[(string) $day] = ['closed' => false, 'intervals' => [['start' => '06:00', 'end' => '23:00']]];
        }

        foreach ($venuesData as $vd) {
            /** @var Organization $tenant */
            $tenant = $vd['org'];
            $venue = Venue::updateOrCreate(
                ['slug' => $vd['slug']],
                [
                    'name' => $vd['name'],
                    'address' => $vd['address'],
                    'google_maps_url' => $vd['google_maps_url'],
                    'city' => $vd['city'],
                    'district' => $vd['district'],
                    'ward' => $vd['ward'],
                    'phone' => $vd['phone'],
                    'email' => $vd['slug'].'@demo.auracourt.vn',
                    'description' => $vd['description'],
                    'amenities' => $vd['amenities'],
                    'status' => 'published',
                    'is_active' => true,
                    'published_at' => now()->subMonths(1),
                    'organization_id' => $tenant->id,
                ]
            );
            // The venues migration inserts aura-court at the real clock; pin it to the tenant's approval like the others.
            $venue->forceFill(['created_at' => $tenant->created_at])->save();

            VenueOperatingSchedule::updateOrCreate(
                ['venue_id' => $venue->id, 'scope_key' => 'venue'],
                ['version' => 1, 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_hours' => $weeklyHours]
            );

            VenueBookingPolicy::updateOrCreate(
                ['venue_id' => $venue->id, 'version' => 1],
                ['settings' => $this->bookingPolicy(), 'effective_at' => now()->subMonths(1)]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingPolicy(): array
    {
        return [
            'booking' => [
                'hold_ttl_minutes' => 15,
                'max_days_ahead' => 30,
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 240,
                'require_continuity' => true,
            ],
            'arrival' => ['early_minutes' => 15, 'late_grace_minutes' => 15, 'no_show_after_minutes' => 30],
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
    }
}
