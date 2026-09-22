<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class OrganizationVenueSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::updateOrCreate(
            ['slug' => 'aura-badminton-club'],
            [
                'name' => 'Aura Sports Marketplace Club',
                'status' => 'active',
                'partner_status' => 'approved',
                'settings' => ['currency' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh', 'cancellation_grace_hours' => 2],
            ]
        );

        $plan = PlatformPlan::updateOrCreate(
            ['code' => 'PLATFORM_GROWTH'],
            [
                'name' => 'Gói Phát Triển',
                'price' => 500000,
                'billing_cycle' => 'monthly',
                'is_active' => true,
            ]
        );

        PlatformSubscription::updateOrCreate(
            ['organization_id' => $org->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now()->subMonths(1),
                'ends_at' => now()->addYear(),
            ]
        );

        $venuesData = [
            [
                'slug' => 'aura-court',
                'name' => 'Aura Court Cầu Giấy',
                'address' => 'Số 18 Phố Duy Tân, Phường Dịch Vọng Hậu, Quận Cầu Giấy, Hà Nội',
                'google_maps_url' => 'https://maps.google.com/?q=18+Duy+Tan+Dich+Vong+Hau+Cau+Giay+Ha+Noi',
            ],
            [
                'slug' => 'aura-arena-quan-ngua',
                'name' => 'Aura Arena Quần Ngựa',
                'address' => 'Cung Thể Thao Quần Ngựa, 30 Đường Văn Cao, Phường Liễu Giai, Quận Ba Đình, Hà Nội',
                'google_maps_url' => 'https://maps.google.com/?q=Cung+The+Thao+Quan+Ngua+30+Van+Cao+Ba+Dinh+Ha+Noi',
            ],
            [
                'slug' => 'aura-complex-tay-ho',
                'name' => 'Aura Sports Complex Tây Hồ',
                'address' => 'Số 68 Phố Quảng Bá, Phường Quảng An, Quận Tây Hồ, Hà Nội',
                'google_maps_url' => 'https://maps.google.com/?q=68+Quang+Ba+Tay+Ho+Ha+Noi',
            ],
            [
                'slug' => 'aura-landmark-72',
                'name' => 'Aura Multi-Sport Landmark 72',
                'address' => 'Tòa Tháp Keangnam Landmark 72, Đường Phạm Hùng, Quận Nam Từ Liêm, Hà Nội',
                'google_maps_url' => 'https://maps.google.com/?q=Keangnam+Landmark+72+Pham+Hung+Ha+Noi',
            ],
            [
                'slug' => 'aura-central-saigon',
                'name' => 'Aura Sports Center Sài Gòn',
                'address' => 'Số 120 Đường Điện Biên Phủ, Phường 25, Quận Bình Thạnh, TP. Hồ Chí Minh',
                'google_maps_url' => 'https://maps.google.com/?q=120+Dien+Bien+Phu+Binh+Thanh+Ho+Chi+Minh',
            ],
        ];

        foreach ($venuesData as $vd) {
            Venue::updateOrCreate(
                ['slug' => $vd['slug']],
                [
                    'name' => $vd['name'],
                    'address' => $vd['address'],
                    'google_maps_url' => $vd['google_maps_url'],
                    'status' => 'published',
                    'is_active' => true,
                    'published_at' => now()->subMonths(1),
                    'organization_id' => $org->id,
                ]
            );
        }
    }
}
