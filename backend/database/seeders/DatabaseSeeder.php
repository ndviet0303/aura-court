<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\CourtPriceSlot;
use App\Models\DisputeCase;
use App\Models\FinanceException;
use App\Models\HostKyc;
use App\Models\MarketplaceListing;
use App\Models\MatchParticipation;
use App\Models\MatchPost;
use App\Models\MembershipPlan;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Payment;
use App\Models\PosProduct;
use App\Models\PriceRule;
use App\Models\ReconciliationRun;
use App\Models\Refund;
use App\Models\Review;
use App\Models\SportZone;
use App\Models\TenantMembershipPlan;
use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Models\TournamentParticipant;
use App\Models\TournamentStanding;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);
        $timestamp = $now->toDateTimeString();

        // =========================================================================
        // 1. Coupons & Promotions
        // =========================================================================
        $coupons = [
            [
                'code' => 'AURA2026',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'max_discount' => 200000,
                'min_spend' => 100000,
                'is_active' => true,
            ],
            [
                'code' => 'AURAVIP',
                'discount_type' => 'percent',
                'discount_value' => 15,
                'max_discount' => 500000,
                'min_spend' => 200000,
                'is_active' => true,
            ],
            [
                'code' => 'CHAOBANMOI',
                'discount_type' => 'fixed',
                'discount_value' => 50000,
                'max_discount' => null,
                'min_spend' => 100000,
                'is_active' => true,
            ],
            [
                'code' => 'PICKLEBALL2026',
                'discount_type' => 'percent',
                'discount_value' => 20,
                'max_discount' => 150000,
                'min_spend' => 120000,
                'is_active' => true,
            ],
            [
                'code' => 'FOOTBALLNIGHT',
                'discount_type' => 'fixed',
                'discount_value' => 100000,
                'max_discount' => null,
                'min_spend' => 500000,
                'is_active' => true,
            ],
            [
                'code' => 'SPORTFEST',
                'discount_type' => 'fixed',
                'discount_value' => 30000,
                'max_discount' => null,
                'min_spend' => 80000,
                'is_active' => true,
            ],
            [
                'code' => 'WEEKENDPLAY',
                'discount_type' => 'percent',
                'discount_value' => 12,
                'max_discount' => 300000,
                'min_spend' => 150000,
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $c) {
            Coupon::updateOrCreate(
                ['code' => $c['code']],
                [
                    'discount_type' => $c['discount_type'],
                    'discount_value' => $c['discount_value'],
                    'max_discount' => $c['max_discount'],
                    'min_spend' => $c['min_spend'],
                    'is_active' => $c['is_active'],
                    'starts_at' => $now->copy()->subDays(30),
                    'ends_at' => $now->copy()->addMonths(12),
                ]
            );
        }

        // =========================================================================
        // 2. Organization & Venues
        // =========================================================================
        $org = Organization::updateOrCreate(
            ['slug' => 'aura-badminton-club'],
            [
                'name' => 'Aura Sports Marketplace Club',
                'status' => 'active',
                'settings' => ['currency' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh'],
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

        $venues = [];
        foreach ($venuesData as $vd) {
            $venues[$vd['slug']] = Venue::updateOrCreate(
                ['slug' => $vd['slug']],
                [
                    'name' => $vd['name'],
                    'address' => $vd['address'],
                    'google_maps_url' => $vd['google_maps_url'],
                    'is_active' => true,
                    'organization_id' => $org->id,
                ]
            );
        }

        // =========================================================================
        // 3. User Accounts (Preserved exactly as requested)
        // =========================================================================
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

        OrganizationMember::updateOrCreate(
            ['organization_id' => $org->id, 'user_id' => $admin->id],
            ['role' => 'organization_owner', 'status' => 'active', 'permissions' => ['*']]
        );

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

        OrganizationMember::updateOrCreate(
            ['organization_id' => $org->id, 'user_id' => $adminExample->id],
            ['role' => 'organization_owner', 'status' => 'active', 'permissions' => ['*']]
        );

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

        OrganizationMember::updateOrCreate(
            ['organization_id' => $org->id, 'user_id' => $demoAdmin->id],
            ['role' => 'organization_owner', 'status' => 'active', 'permissions' => ['*']]
        );

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

        OrganizationMember::updateOrCreate(
            ['organization_id' => $org->id, 'user_id' => $demoUser->id],
            ['role' => 'customer', 'status' => 'active']
        );

        $customers = collect(range(1, 24))->map(function (int $number) use ($now, $org): User {
            $user = User::firstOrNew(['email' => sprintf('khach%02d@demo.auracourt.vn', $number)]);
            $user->forceFill([
                'name' => sprintf('Khách hàng %02d', $number),
                'password' => Hash::make('Demo12345!'),
                'role' => 'customer',
                'email_verified_at' => $now->copy()->subDays($number),
            ])->save();

            OrganizationMember::updateOrCreate(
                ['organization_id' => $org->id, 'user_id' => $user->id],
                ['role' => 'customer', 'status' => 'active']
            );

            return $user;
        });

        // =========================================================================
        // 4. Sport Zones (Across all sports & venues)
        // =========================================================================
        $sportZonesData = [
            // Venue 1: Cầu Giấy
            [
                'venue_slug' => 'aura-court',
                'key' => 'cg-badminton',
                'name' => 'Khu Sân Cầu Lông Tiêu Chuẩn',
                'sport_type' => 'badminton',
                'slot_duration' => 60,
                'description' => 'Khu sân cầu lông thảm BWF cao cấp, trần cao 9.5m, ánh sáng LED 500 Lux',
            ],
            [
                'venue_slug' => 'aura-court',
                'key' => 'cg-pickleball',
                'name' => 'Khu Sân Pickleball Dynamic',
                'sport_type' => 'pickleball',
                'slot_duration' => 60,
                'description' => 'Cụm sân pickleball mặt phủ sơn acrylic 5 lớp giảm chấn, hệ thống lưới chuyên nghiệp',
            ],
            [
                'venue_slug' => 'aura-court',
                'key' => 'cg-table-tennis',
                'name' => 'Khu Sân Bóng Bàn Tiêu Chuẩn',
                'sport_type' => 'table_tennis',
                'slot_duration' => 60,
                'description' => 'Bàn bóng bàn thi đấu Double Fish ITTF Approved, sàn cao su chống trơn trượt',
            ],

            // Venue 2: Quần Ngựa
            [
                'venue_slug' => 'aura-arena-quan-ngua',
                'key' => 'qn-badminton',
                'name' => 'Khu Sân Cầu Lông Thi Đấu Quần Ngựa',
                'sport_type' => 'badminton',
                'slot_duration' => 60,
                'description' => 'Sân cầu lông trung tâm có khán đài mini và hệ thống camera replay VAR',
            ],
            [
                'venue_slug' => 'aura-arena-quan-ngua',
                'key' => 'qn-football-5v5',
                'name' => 'Khu Sân Bóng Đá Mini 5v5 Quần Ngựa',
                'sport_type' => 'football_5v5',
                'slot_duration' => 60,
                'description' => 'Cụm sân cỏ nhân tạo FIFA Quality Pro, hạt cao su EPDM không mùi, dàn đèn cao áp',
            ],
            [
                'venue_slug' => 'aura-arena-quan-ngua',
                'key' => 'qn-football-7v7',
                'name' => 'Khu Sân Bóng Đá 7v7 Quần Ngựa',
                'sport_type' => 'football_7v7',
                'slot_duration' => 90,
                'description' => 'Sân bóng 7 người kích thước chuẩn 50x30m, thoát nước siêu nhanh',
            ],
            [
                'venue_slug' => 'aura-arena-quan-ngua',
                'key' => 'qn-table-tennis',
                'name' => 'Khu Sân Bóng Bàn Quần Ngựa',
                'sport_type' => 'table_tennis',
                'slot_duration' => 60,
                'description' => 'Phòng bóng bàn máy lạnh Stiga Master, hàng rào chắn bóng tiêu chuẩn',
            ],

            // Venue 3: Tây Hồ
            [
                'venue_slug' => 'aura-complex-tay-ho',
                'key' => 'th-tennis',
                'name' => 'Khu Sân Tennis Pro Grandslam Tây Hồ',
                'sport_type' => 'tennis',
                'slot_duration' => 60,
                'description' => 'Sân quần vợt mặt cứng DecoTurf và sân đất nện tiêu chuẩn Roland Garros',
            ],
            [
                'venue_slug' => 'aura-complex-tay-ho',
                'key' => 'th-pickleball',
                'name' => 'Khu Sân Pickleball View Hồ Tây',
                'sport_type' => 'pickleball',
                'slot_duration' => 60,
                'description' => 'Sân pickleball ngoài trời đón gió hồ, có mái che bán phần và quán lounge thư giãn',
            ],
            [
                'venue_slug' => 'aura-complex-tay-ho',
                'key' => 'th-badminton',
                'name' => 'Khu Sân Cầu Lông Victor Tây Hồ',
                'sport_type' => 'badminton',
                'slot_duration' => 60,
                'description' => 'Sân cầu lông chuyên dụng Victor, hệ thống quạt mát đối lưu trần cao',
            ],
            [
                'venue_slug' => 'aura-complex-tay-ho',
                'key' => 'th-basketball',
                'name' => 'Khu Sân Bóng Rổ Tây Hồ Streetball',
                'sport_type' => 'basketball',
                'slot_duration' => 60,
                'description' => 'Sân bóng rổ ngoài trời sơn nghệ thuật street-art, rổ chuẩn NBA chịu lực',
            ],

            // Venue 4: Landmark 72
            [
                'venue_slug' => 'aura-landmark-72',
                'key' => 'lm-basketball',
                'name' => 'Khu Sân Bóng Rổ Trong Nhà NBA Landmark',
                'sport_type' => 'basketball',
                'slot_duration' => 60,
                'description' => 'Sàn gỗ phong Bắc Mỹ Robbins All-Star, trụ rổ thủy lực Schelde Sports',
            ],
            [
                'venue_slug' => 'aura-landmark-72',
                'key' => 'lm-tennis',
                'name' => 'Khu Sân Tennis Mặt Cứng US Open Landmark',
                'sport_type' => 'tennis',
                'slot_duration' => 60,
                'description' => 'Sân tennis trong nhà điều hòa trung tâm 24/7, ánh sáng 750 Lux chuẩn ATP',
            ],
            [
                'venue_slug' => 'aura-landmark-72',
                'key' => 'lm-badminton',
                'name' => 'Khu Sân Cầu Lông Yonex Pro Landmark',
                'sport_type' => 'badminton',
                'slot_duration' => 60,
                'description' => 'Khu sân cầu lông sang trọng phân khu VIP, phòng thay đồ và sauna tiện nghi',
            ],
            [
                'venue_slug' => 'aura-landmark-72',
                'key' => 'lm-volleyball',
                'name' => 'Khu Sân Bóng Chuyền Taraflex Landmark',
                'sport_type' => 'volleyball',
                'slot_duration' => 60,
                'description' => 'Thảm Gerflor Taraflex bóng chuyền thi đấu quốc tế, lưới và cột căng trợ lực',
            ],

            // Venue 5: Sài Gòn
            [
                'venue_slug' => 'aura-central-saigon',
                'key' => 'sg-badminton',
                'name' => 'Khu Sân Cầu Lông Sài Gòn Central',
                'sport_type' => 'badminton',
                'slot_duration' => 60,
                'description' => 'Cụm sân cầu lông trung tâm Bình Thạnh, thảm BWF 5 lớp êm ái, bãi đỗ xe ô tô',
            ],
            [
                'venue_slug' => 'aura-central-saigon',
                'key' => 'sg-football-5v5',
                'name' => 'Khu Sân Bóng Đá Mini 5v5 Sài Gòn',
                'sport_type' => 'football_5v5',
                'slot_duration' => 60,
                'description' => 'Sân bóng đá mini cỏ nhân tạo đệm hạt cao su nguyên sinh, thoát nước tối ưu',
            ],
            [
                'venue_slug' => 'aura-central-saigon',
                'key' => 'sg-pickleball',
                'name' => 'Khu Sân Pickleball Rooftop Sài Gòn',
                'sport_type' => 'pickleball',
                'slot_duration' => 60,
                'description' => 'Sân pickleball tầng thượng thoáng đãng ngắm trọn Landmark 81 lung linh về đêm',
            ],
        ];

        $sportZones = [];
        foreach ($sportZonesData as $sz) {
            $venue = $venues[$sz['venue_slug']];
            $zone = SportZone::updateOrCreate(
                ['venue_id' => $venue->id, 'sport_type' => $sz['sport_type']],
                [
                    'name' => $sz['name'],
                    'sport_type' => $sz['sport_type'],
                    'slot_duration' => $sz['slot_duration'],
                    'description' => $sz['description'],
                    'is_active' => true,
                ]
            );
            $sportZones[$sz['key']] = $zone;
        }

        // =========================================================================
        // 5. Courts Definition (27 diverse courts across 8 sports)
        // =========================================================================
        $courtDefs = [
            // Cầu lông - Cầu Giấy (5 sân)
            [
                'name' => 'Sân 1 - Thảm BWF Tiêu Chuẩn',
                'slug' => 'san-1-tham-bwf-cau-giay',
                'description' => 'Sân cầu lông thảm BWF tiêu chuẩn 5 lớp, hệ thống đèn LED 500 Lux chống chói.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-badminton',
                'image_url' => '/aura/court-1.jpg',
                'base_price' => 100000,
                'sport_type' => 'badminton',
            ],
            [
                'name' => 'Sân 2 - Tiêu Chuẩn',
                'slug' => 'san-2-tieu-chuan-cau-giay',
                'description' => 'Mặt sàn cao cấp êm ái, trần cao 9.5m chuẩn thi đấu phong trào và bán chuyên.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-badminton',
                'image_url' => '/aura/court-2.jpg',
                'base_price' => 100000,
                'sport_type' => 'badminton',
            ],
            [
                'name' => 'Sân 3 - Tiêu Chuẩn',
                'slug' => 'san-3-tieu-chuan-cau-giay',
                'description' => 'Hệ thống đèn trần định hướng, không gian thoáng mát và thông gió liên tục.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-badminton',
                'image_url' => '/aura/court-3.jpg',
                'base_price' => 100000,
                'sport_type' => 'badminton',
            ],
            [
                'name' => 'Sân 4 - VIP Đơn/Đôi',
                'slug' => 'san-4-vip-cau-giay',
                'description' => 'Sân VIP không gian rộng rãi, có khu ghế nghỉ riêng biệt và điều hòa mát mẻ.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-badminton',
                'image_url' => '/aura/court-4.jpg',
                'base_price' => 130000,
                'sport_type' => 'badminton',
            ],
            [
                'name' => 'Sân 5 - VIP Tournament',
                'slug' => 'san-5-vip-tournament-cau-giay',
                'description' => 'Sân thi đấu giải quốc gia, thảm chuyên dụng đàn hồi cao giảm tối đa chấn thương.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-badminton',
                'image_url' => '/aura/court-5.jpg',
                'base_price' => 140000,
                'sport_type' => 'badminton',
            ],

            // Pickleball - Cầu Giấy (2 sân)
            [
                'name' => 'Sân Pickleball 1 - Aura Neon',
                'slug' => 'san-pickleball-1-neon-cau-giay',
                'description' => 'Sân Pickleball hiện đại phủ Acrylic 5 lớp, viền line neon dạ quang nổi bật.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-pickleball',
                'image_url' => '/aura/court-6.jpg',
                'base_price' => 130000,
                'sport_type' => 'pickleball',
            ],
            [
                'name' => 'Sân Pickleball 2 - Dynamic Pro',
                'slug' => 'san-pickleball-2-dynamic-cau-giay',
                'description' => 'Sân Pickleball cao cấp tiêu chuẩn USA Pickleball, lưới kéo cáp chống rung.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-pickleball',
                'image_url' => '/aura/court-1.jpg',
                'base_price' => 130000,
                'sport_type' => 'pickleball',
            ],

            // Bóng bàn - Cầu Giấy (1 sân)
            [
                'name' => 'Sân Bóng Bàn 1 - Double Fish ITTF',
                'slug' => 'san-bong-ban-1-double-fish-cau-giay',
                'description' => 'Bàn bóng bàn thi đấu Double Fish chuẩn quốc tế, thảm đỏ cao su chống trượt.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-table-tennis',
                'image_url' => '/aura/court-2.jpg',
                'base_price' => 60000,
                'sport_type' => 'table_tennis',
            ],

            // Cầu lông - Quần Ngựa (3 sân)
            [
                'name' => 'Sân 6 - Khán Đài Quần Ngựa',
                'slug' => 'san-6-khan-dai-quan-ngua',
                'description' => 'Sân trung tâm Cung Thể Thao Quần Ngựa có khán đài mini, thảm thi đấu BWF cao cấp.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'zone_key' => 'qn-badminton',
                'image_url' => '/aura/court-3.jpg',
                'base_price' => 120000,
                'sport_type' => 'badminton',
            ],
            [
                'name' => 'Sân 7 - Tiêu Chuẩn Quần Ngựa',
                'slug' => 'san-7-tieu-chuan-quan-ngua',
                'description' => 'Sân tập luyện và giao lưu câu lạc bộ tại Ba Đình, tiện nghi đầy đủ và bãi xe rộng.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'zone_key' => 'qn-badminton',
                'image_url' => '/aura/court-4.jpg',
                'base_price' => 100000,
                'sport_type' => 'badminton',
            ],
            [
                'name' => 'Sân 8 - Pro Replay Quần Ngựa',
                'slug' => 'san-8-pro-replay-quan-ngua',
                'description' => 'Sân công nghệ cao trang bị hệ thống camera xem lại pha cầu (VAR/Replay) thông minh.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'zone_key' => 'qn-badminton',
                'image_url' => '/aura/court-5.jpg',
                'base_price' => 140000,
                'sport_type' => 'badminton',
            ],

            // Bóng đá 5v5 - Quần Ngựa (2 sân)
            [
                'name' => 'Sân Bóng Đá 5v5 A1 Quần Ngựa',
                'slug' => 'san-bong-da-5v5-a1-quan-ngua',
                'description' => 'Sân bóng mini 5 người cỏ nhân tạo sợi mềm không chấn thương, đèn chiếu sáng 800 Lux.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'zone_key' => 'qn-football-5v5',
                'image_url' => '/aura/court-6.jpg',
                'base_price' => 350000,
                'sport_type' => 'football_5v5',
            ],
            [
                'name' => 'Sân Bóng Đá 5v5 A2 Quần Ngựa',
                'slug' => 'san-bong-da-5v5-a2-quan-ngua',
                'description' => 'Sân bóng đá mini khu vực khán đài B, mặt cỏ chất lượng cao thoát nước cực nhanh.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'zone_key' => 'qn-football-5v5',
                'image_url' => '/aura/court-1.jpg',
                'base_price' => 350000,
                'sport_type' => 'football_5v5',
            ],

            // Bóng đá 7v7 - Quần Ngựa (1 sân)
            [
                'name' => 'Sân Bóng Đá 7v7 C1 Quần Ngựa',
                'slug' => 'san-bong-da-7v7-c1-quan-ngua',
                'description' => 'Sân 7 người tiêu chuẩn thi đấu phong trào Hà Nội, cỏ nhập khẩu đạt chuẩn FIFA 2 sao.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'zone_key' => 'qn-football-7v7',
                'image_url' => '/aura/court-2.jpg',
                'base_price' => 750000,
                'sport_type' => 'football_7v7',
            ],

            // Bóng bàn - Quần Ngựa (1 sân)
            [
                'name' => 'Sân Bóng Bàn 2 - Stiga Master Quần Ngựa',
                'slug' => 'san-bong-ban-2-stiga-master-quan-ngua',
                'description' => 'Phòng tập bóng bàn máy lạnh riêng biệt, trang bị máy bắn bóng tự động tập kỹ thuật.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'zone_key' => 'qn-table-tennis',
                'image_url' => '/aura/court-3.jpg',
                'base_price' => 70000,
                'sport_type' => 'table_tennis',
            ],

            // Tennis - Tây Hồ (2 sân)
            [
                'name' => 'Sân Tennis 1 - Đất Nện French Open Tây Hồ',
                'slug' => 'san-tennis-1-dat-nen-tay-ho',
                'description' => 'Sân đất nện đỏ tiêu chuẩn Roland Garros độc đáo tại Hà Nội, trượt êm ái bảo vệ khớp gối.',
                'venue_slug' => 'aura-complex-tay-ho',
                'zone_key' => 'th-tennis',
                'image_url' => '/aura/court-4.jpg',
                'base_price' => 250000,
                'sport_type' => 'tennis',
            ],
            [
                'name' => 'Sân Tennis 2 - Hard Court Pro Tây Hồ',
                'slug' => 'san-tennis-2-hard-court-tay-ho',
                'description' => 'Sân cứng chuyên nghiệp sơn Plexipave độ nảy chuẩn xác, view thoáng mát Hồ Tây.',
                'venue_slug' => 'aura-complex-tay-ho',
                'zone_key' => 'th-tennis',
                'image_url' => '/aura/court-5.jpg',
                'base_price' => 220000,
                'sport_type' => 'tennis',
            ],

            // Pickleball - Tây Hồ (1 sân)
            [
                'name' => 'Sân Pickleball 3 - Sunset View Hồ Tây',
                'slug' => 'san-pickleball-3-sunset-tay-ho',
                'description' => 'Sân Pickleball ngoài trời ngắm hoàng hôn Hồ Tây cực chill, có lounge nước ép thể thao.',
                'venue_slug' => 'aura-complex-tay-ho',
                'zone_key' => 'th-pickleball',
                'image_url' => '/aura/court-6.jpg',
                'base_price' => 150000,
                'sport_type' => 'pickleball',
            ],

            // Cầu lông - Tây Hồ (1 sân)
            [
                'name' => 'Sân 9 - Thảm Victor Tây Hồ',
                'slug' => 'san-9-tham-victor-tay-ho',
                'description' => 'Sân cầu lông cao cấp Victor, hệ thống cách âm và điều hòa thông gió hiện đại.',
                'venue_slug' => 'aura-complex-tay-ho',
                'zone_key' => 'th-badminton',
                'image_url' => '/aura/court-1.jpg',
                'base_price' => 120000,
                'sport_type' => 'badminton',
            ],

            // Bóng rổ - Tây Hồ (1 sân)
            [
                'name' => 'Sân Bóng Rổ 1 - Streetball Zone Tây Hồ',
                'slug' => 'san-bong-ro-1-streetball-tay-ho',
                'description' => 'Sân bóng rổ 3x3 và 5x5 phong cách đường phố, vành rổ chuyên dụng dunk êm ái.',
                'venue_slug' => 'aura-complex-tay-ho',
                'zone_key' => 'th-basketball',
                'image_url' => '/aura/court-2.jpg',
                'base_price' => 250000,
                'sport_type' => 'basketball',
            ],

            // Bóng rổ - Landmark 72 (1 sân)
            [
                'name' => 'Sân Bóng Rổ Trong Nhà NBA Landmark',
                'slug' => 'san-bong-ro-in-house-nba-landmark',
                'description' => 'Sân bóng rổ trong nhà sàn gỗ tự nhiên tiêu chuẩn thi đấu NBA, điều hòa trung tâm 24/7.',
                'venue_slug' => 'aura-landmark-72',
                'zone_key' => 'lm-basketball',
                'image_url' => '/aura/court-3.jpg',
                'base_price' => 380000,
                'sport_type' => 'basketball',
            ],

            // Tennis - Landmark 72 (1 sân)
            [
                'name' => 'Sân Tennis 3 - Mặt Cứng US Open Landmark',
                'slug' => 'san-tennis-3-mat-cung-landmark',
                'description' => 'Sân tennis trong nhà đỉnh cao tại Keangnam Landmark 72, ánh sáng chuẩn truyền hình.',
                'venue_slug' => 'aura-landmark-72',
                'zone_key' => 'lm-tennis',
                'image_url' => '/aura/court-4.jpg',
                'base_price' => 320000,
                'sport_type' => 'tennis',
            ],

            // Cầu lông - Landmark 72 (1 sân)
            [
                'name' => 'Sân 10 - Yonex Pro Landmark',
                'slug' => 'san-10-yonex-pro-landmark',
                'description' => 'Sân cầu lông phân khúc thượng lưu, thảm Yonex Super Cushion, phòng tắm nóng lạnh.',
                'venue_slug' => 'aura-landmark-72',
                'zone_key' => 'lm-badminton',
                'image_url' => '/aura/court-5.jpg',
                'base_price' => 160000,
                'sport_type' => 'badminton',
            ],

            // Bóng chuyền - Landmark 72 (1 sân)
            [
                'name' => 'Sân Bóng Chuyền 1 - Thảm Taraflex Landmark',
                'slug' => 'san-bong-chuyen-1-taraflex-landmark',
                'description' => 'Sân bóng chuyền trong nhà thảm Taraflex Gerflor 7mm giảm tải chấn thương tối đa.',
                'venue_slug' => 'aura-landmark-72',
                'zone_key' => 'lm-volleyball',
                'image_url' => '/aura/court-6.jpg',
                'base_price' => 280000,
                'sport_type' => 'volleyball',
            ],

            // Cầu lông - Sài Gòn (1 sân)
            [
                'name' => 'Sân 11 - Sài Gòn Central BWF',
                'slug' => 'san-11-sai-gon-central-bwf',
                'description' => 'Sân cầu lông BWF tại Điện Biên Phủ Bình Thạnh, thảm mới 100%, bãi xe rộng rãi.',
                'venue_slug' => 'aura-central-saigon',
                'zone_key' => 'sg-badminton',
                'image_url' => '/aura/court-1.jpg',
                'base_price' => 110000,
                'sport_type' => 'badminton',
            ],

            // Bóng đá 5v5 - Sài Gòn (1 sân)
            [
                'name' => 'Sân Bóng Đá 5v5 Sài Gòn Central',
                'slug' => 'san-bong-da-5v5-sai-gon-central',
                'description' => 'Sân bóng đá mini 5 người trung tâm Sài Gòn, dàn đèn LED không chói lóa mắt.',
                'venue_slug' => 'aura-central-saigon',
                'zone_key' => 'sg-football-5v5',
                'image_url' => '/aura/court-2.jpg',
                'base_price' => 380000,
                'sport_type' => 'football_5v5',
            ],

            // Pickleball - Sài Gòn (1 sân)
            [
                'name' => 'Sân Pickleball 4 - Rooftop View Landmark 81',
                'slug' => 'san-pickleball-4-rooftop-saigon',
                'description' => 'Sân Pickleball tầng thượng ngắm Landmark 81 rực rỡ, gió mát tự nhiên, quầy bar cocktail thể thao.',
                'venue_slug' => 'aura-central-saigon',
                'zone_key' => 'sg-pickleball',
                'image_url' => '/aura/court-3.jpg',
                'base_price' => 160000,
                'sport_type' => 'pickleball',
            ],
        ];

        $courts = collect();

        foreach ($courtDefs as $def) {
            $venue = $venues[$def['venue_slug']];
            $zone = $sportZones[$def['zone_key']];

            $court = Court::updateOrCreate(
                ['slug' => $def['slug']],
                [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'image_url' => $def['image_url'],
                    'venue_id' => $venue->id,
                    'sport_zone_id' => $zone->id,
                    'is_active' => true,
                ]
            );
            $courts->push($court);

            // Generate full weekly pricing slots (weekdays 1..7, hours 6..22)
            $priceRows = [];
            $basePrice = $def['base_price'];

            for ($weekday = 1; $weekday <= 7; $weekday++) {
                for ($hour = 6; $hour < 23; $hour++) {
                    $isWeekend = $weekday >= 6;
                    $isPeak = $hour >= 17 && $hour < 22;

                    $weekendSurge = (int) round($basePrice * 0.2);
                    $peakSurge = (int) round($basePrice * 0.25);

                    $slotPrice = $basePrice + ($isWeekend ? $weekendSurge : 0) + ($isPeak ? $peakSurge : 0);
                    // Round to nearest 5000 VND
                    $slotPrice = (int) (round($slotPrice / 5000) * 5000);

                    $startsAt = sprintf('%02d:00', $hour);

                    $priceRows[] = [
                        'court_id' => $court->id,
                        'weekday' => $weekday,
                        'starts_at' => $startsAt,
                        'ends_at' => sprintf('%02d:00', $hour + 1),
                        'price' => $slotPrice,
                        'is_active' => true,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }
            CourtPriceSlot::upsert($priceRows, ['court_id', 'weekday', 'starts_at'], ['ends_at', 'price', 'is_active', 'updated_at']);

            // Create V2 PriceRules
            PriceRule::updateOrCreate(
                ['court_id' => $court->id, 'type' => 'standard', 'weekday' => 1],
                [
                    'organization_id' => $org->id,
                    'venue_id' => $venue->id,
                    'sport_zone_id' => $zone->id,
                    'price' => $basePrice,
                    'version' => 1,
                    'priority' => 1,
                    'is_active' => true,
                ]
            );
        }

        // =========================================================================
        // 6. Court Blocks (Realistic maintenance / event blocks)
        // =========================================================================
        $blockStart = $now->copy()->startOfDay()->addDays(14)->setTime(8, 0);
        foreach ([1, 5, 8, 12, 16] as $idx) {
            if (isset($courts[$idx])) {
                $court = $courts[$idx];
                CourtBlock::updateOrCreate(
                    ['court_id' => $court->id, 'starts_at' => $blockStart->copy()->utc()->format('Y-m-d H:i:s')],
                    [
                        'ends_at' => $blockStart->copy()->addHours(3)->utc()->format('Y-m-d H:i:s'),
                        'reason' => $idx % 2 === 0 ? 'Bảo trì và vệ sinh định kỳ sàn sân' : 'Tổ chức giải đấu nội bộ CLB',
                    ]
                );
            }
        }

        // =========================================================================
        // 7. Bookings, Slots, Payments & Refunds (64 diverse records)
        // =========================================================================
        $createdBookings = [];

        foreach (range(1, 64) as $number) {
            $court = $courts[($number - 1) % $courts->count()];
            $customer = $customers[($number - 1) % $customers->count()];
            $dayOffset = 1 + intdiv($number - 1, $courts->count());
            $hour = 6 + ((($number - 1) % 8) * 2);
            $slotCount = ($number % 3 === 0) ? 2 : 1;

            $starts = collect(range(0, $slotCount - 1))->map(
                fn (int $offset) => $now->copy()->startOfDay()->addDays($dayOffset)->setTime($hour + $offset, 0)
            );

            $status = match ($number % 10) {
                2 => 'cancelled',
                3 => 'expired',
                4 => 'refund_pending',
                5 => 'refunded',
                7 => 'no_show',
                default => $number % 10 === 1 ? 'pending_payment' : 'confirmed',
            };

            $isHistorical = in_array($status, ['cancelled', 'expired', 'refund_pending', 'refunded', 'no_show'], true) || ($number <= 20);
            if ($isHistorical) {
                $starts = $starts->map(fn (Carbon $start) => $start->subDays(15 + ($number % 20)));
            }

            $prices = $starts->map(function (Carbon $start) use ($court, $courts): int {
                $courtIndex = $courts->search(fn (Court $item): bool => $item->id === $court->id);
                $isWeekend = $start->dayOfWeekIso >= 6;
                $isPeak = $start->hour >= 17 && $start->hour < 22;
                $base = 100000;
                if ($courtIndex >= 10 && $courtIndex < 14) $base = 350000; // football
                elseif ($courtIndex >= 15 && $courtIndex < 18) $base = 250000; // tennis
                elseif ($courtIndex >= 5 && $courtIndex < 8) $base = 130000; // pickleball

                return $base + ($isWeekend ? (int) ($base * 0.2) : 0) + ($isPeak ? (int) ($base * 0.25) : 0);
            });

            $total = $prices->sum();
            $code = sprintf('DEMO-BK-%03d', $number);

            $booking = Booking::updateOrCreate(
                ['code' => $code],
                [
                    'user_id' => $customer->id,
                    'court_id' => $court->id,
                    'total_amount' => $total,
                    'status' => $status,
                    'payment_expires_at' => $status === 'pending_payment' ? $now->copy()->addMinutes(15) : ($status === 'expired' ? $now->copy()->subDay() : null),
                    'cancelled_at' => ($status === 'cancelled') ? $now->copy()->subDays(2) : null,
                    'cancellation_reason' => ($status === 'cancelled') ? 'Khách bận lịch đột xuất' : null,
                ]
            );

            $createdBookings[] = ['booking' => $booking, 'court' => $court, 'user' => $customer, 'status' => $status];

            foreach ($starts as $slotIndex => $start) {
                $slotStatus = match ($status) {
                    'pending_payment' => 'held',
                    'confirmed', 'no_show' => 'confirmed',
                    'expired' => 'expired',
                    default => 'cancelled',
                };

                BookingSlot::updateOrCreate(
                    ['booking_id' => $booking->id, 'starts_at' => $start->copy()->utc()->format('Y-m-d H:i:s')],
                    [
                        'court_id' => $court->id,
                        'ends_at' => $start->copy()->addHour()->utc()->format('Y-m-d H:i:s'),
                        'unit_price' => $prices[$slotIndex],
                        'status' => $slotStatus,
                    ]
                );
            }

            $paymentStatus = match ($status) {
                'confirmed', 'no_show', 'refunded' => 'paid',
                'pending_payment' => 'manual_review',
                default => 'rejected',
            };

            Payment::updateOrCreate(
                ['external_transaction_id' => sprintf('DEMO-TX-%03d', $number)],
                [
                    'booking_id' => $booking->id,
                    'provider' => 'sepay',
                    'amount' => $total,
                    'transfer_code' => $code,
                    'status' => $paymentStatus,
                    'payload' => ['demo' => true, 'source' => 'DatabaseSeeder'],
                    'received_at' => $paymentStatus === 'manual_review' ? null : $now->copy()->subDays(1),
                ]
            );

            if ($status === 'refund_pending' || $status === 'refunded') {
                Refund::updateOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'amount' => $total,
                        'status' => $status === 'refunded' ? 'processed' : 'pending',
                        'note' => 'Hoàn tiền vé đặt sân theo chính sách',
                        'processed_by' => $status === 'refunded' ? $admin->id : null,
                        'processed_at' => $status === 'refunded' ? $now->copy()->subDays(1) : null,
                    ]
                );
            }
        }

        // =========================================================================
        // 8. Authentic Customer Reviews (For courts with completed bookings)
        // =========================================================================
        $reviewComments = [
            'Sân thảm mới đánh rất êm chân, độ bám hoàn hảo, hệ thống ánh sáng LED dịu mắt không bị chói khi đập cầu.',
            'Cơ sở vật chất tuyệt vời, điều hòa mát mẻ, nhân viên lễ tân hỗ trợ nhiệt tình và thân thiện!',
            'Sân pickleball đẹp mê ly, mặt sân nảy chuẩn, có quầy nước và chỗ ngồi chờ thoáng mát.',
            'Sân bóng đá cỏ êm, hạt cao su không bị mùi, hệ thống đèn chiếu sáng ban đêm cực kỳ rõ nét.',
            'Hệ thống xem lại pha cầu (VAR) hoạt động rất mượt mà, trải nghiệm đánh giải đỉnh cao.',
            'Không gian rộng rãi, bãi đỗ xe ô tô và xe máy thoải mái, phòng tắm và thay đồ sạch sẽ 10/10.',
            'Đặt sân trên website Aura Court quá nhanh chóng và tiện lợi, quét mã QR vào sân ngay không phải chờ.',
            'Sân tennis mặt cứng độ nảy chuẩn xác, không gian yên tĩnh và đẳng cấp, sẽ quay lại thường xuyên.',
            'Bàn bóng bàn thi đấu Double Fish chuẩn xịn, có rào chắn riêng biệt nên đánh rất tập trung.',
            'Sàn gỗ bóng rổ chuẩn NBA giảm chấn cực tốt, anh em trong đội bóng ai cũng tấm tắc khen ngợi.',
        ];

        $reviewIndex = 0;
        foreach ($createdBookings as $item) {
            if ($item['status'] === 'confirmed' && $reviewIndex < 35) {
                $rating = ($reviewIndex % 6 === 0) ? 4 : 5;
                $comment = $reviewComments[$reviewIndex % count($reviewComments)];

                Review::updateOrCreate(
                    ['booking_id' => $item['booking']->id],
                    [
                        'user_id' => $item['user']->id,
                        'court_id' => $item['court']->id,
                        'rating' => $rating,
                        'body' => $comment,
                        'is_published' => true,
                    ]
                );
                $reviewIndex++;
            }
        }

        // =========================================================================
        // 9. POS Products (Retail items, beverages & accessories)
        // =========================================================================
        $products = [
            [
                'sku' => 'POS-POCARI-500',
                'barcode' => '8935001701011',
                'name' => 'Nước bù khoáng Pocari Sweat 500ml',
                'description' => 'Bù điện giải và ion thiết yếu nhanh chóng khi vận động cường độ cao.',
                'price' => 15000,
                'cost' => 10000,
                'stock_quantity' => 120,
            ],
            [
                'sku' => 'POS-REVIVE-500',
                'barcode' => '8935001701028',
                'name' => 'Nước ngọt thể thao Revive Chanh Muối 500ml',
                'description' => 'Cung cấp năng lượng tức thì và giải tỏa cơn khát.',
                'price' => 15000,
                'cost' => 9500,
                'stock_quantity' => 95,
            ],
            [
                'sku' => 'POS-REDBULL-250',
                'barcode' => '8935001701035',
                'name' => 'Nước tăng lực Red Bull Thái Lan 250ml',
                'description' => 'Bật năng lượng, bứt phá giới hạn trong từng séc đấu.',
                'price' => 20000,
                'cost' => 13500,
                'stock_quantity' => 80,
            ],
            [
                'sku' => 'POS-LAVIE-500',
                'barcode' => '8935001701042',
                'name' => 'Nước khoáng thiên nhiên LaVie 500ml',
                'description' => 'Nước khoáng thanh khiết nguyên bản từ thiên nhiên.',
                'price' => 10000,
                'cost' => 5000,
                'stock_quantity' => 200,
            ],
            [
                'sku' => 'POS-COCO-330',
                'barcode' => '8935001701059',
                'name' => 'Nước dừa tươi nguyên chất Vietcoco 330ml',
                'description' => 'Nước dừa tươi 100% giàu kali và chất điện giải tự nhiên.',
                'price' => 25000,
                'cost' => 16000,
                'stock_quantity' => 60,
            ],
            [
                'sku' => 'POS-GUGEL-32',
                'barcode' => '8935001701066',
                'name' => 'Gel năng lượng GU Energy Gel (Vị Cam/Dâu)',
                'description' => 'Nạp carbohydrate và chất điện giải tức thì cho VĐV.',
                'price' => 45000,
                'cost' => 30000,
                'stock_quantity' => 45,
            ],
            [
                'sku' => 'POS-PROBAR-60',
                'barcode' => '8935001701073',
                'name' => 'Thanh bánh ngũ cốc Protein Bar 60g',
                'description' => 'Cung cấp 20g Protein hỗ trợ phục hồi cơ bắp.',
                'price' => 40000,
                'cost' => 26000,
                'stock_quantity' => 50,
            ],
            [
                'sku' => 'POS-YONEX-GRIP',
                'barcode' => '8935001701080',
                'name' => 'Quấn cán vợt Yonex Super Grap AC102EX',
                'description' => 'Chống trơn trượt thấm hút mồ hôi số 1 thế giới.',
                'price' => 35000,
                'cost' => 22000,
                'stock_quantity' => 150,
            ],
            [
                'sku' => 'POS-YONEX-AS50',
                'barcode' => '8935001701097',
                'name' => 'Ống cầu lông Yonex Aerosensa AS-50 (12 quả)',
                'description' => 'Cầu lông thi đấu quốc tế cao cấp nhất của Yonex.',
                'price' => 780000,
                'cost' => 650000,
                'stock_quantity' => 30,
            ],
            [
                'sku' => 'POS-VINASTAR-BLUE',
                'barcode' => '8935001701103',
                'name' => 'Ống cầu lông VinaStar Xanh (12 quả)',
                'description' => 'Cầu lông phong trào bền bỉ, đường bay đầm tay và chuẩn xác.',
                'price' => 260000,
                'cost' => 210000,
                'stock_quantity' => 85,
            ],
            [
                'sku' => 'POS-WILSON-USOPEN',
                'barcode' => '8935001701110',
                'name' => 'Hộp bóng Tennis Wilson US Open (3 quả)',
                'description' => 'Bóng tennis chính thức của giải US Open Grand Slam.',
                'price' => 140000,
                'cost' => 105000,
                'stock_quantity' => 40,
            ],
            [
                'sku' => 'POS-FRANKLIN-X40',
                'barcode' => '8935001701127',
                'name' => 'Hộp bóng Pickleball Franklin X-40 Outdoor (3 quả)',
                'description' => 'Bóng thi đấu pickleball ngoài trời bền bỉ được USAP chứng nhận.',
                'price' => 160000,
                'cost' => 115000,
                'stock_quantity' => 50,
            ],
            [
                'sku' => 'POS-WRISTBAND-YNX',
                'barcode' => '8935001701134',
                'name' => 'Băng đeo cổ tay thấm mồ hôi Yonex',
                'description' => 'Chất liệu cotton co giãn cao cấp, thấm hút mồ hôi cực nhanh.',
                'price' => 65000,
                'cost' => 40000,
                'stock_quantity' => 70,
            ],
            [
                'sku' => 'POS-SOCKS-AURA',
                'barcode' => '8935001701141',
                'name' => 'Tất thể thao dệt cổ cao Aura Elite Cushion',
                'description' => 'Đệm bàn chân êm ái, chống phồng rộp và khử mùi kháng khuẩn.',
                'price' => 50000,
                'cost' => 25000,
                'stock_quantity' => 100,
            ],
        ];

        foreach ($products as $prod) {
            PosProduct::updateOrCreate(
                ['sku' => $prod['sku']],
                [
                    'organization_id' => $org->id,
                    'barcode' => $prod['barcode'],
                    'name' => $prod['name'],
                    'description' => $prod['description'],
                    'price' => $prod['price'],
                    'cost' => $prod['cost'],
                    'stock_quantity' => $prod['stock_quantity'],
                    'is_active' => true,
                    'metadata' => ['category' => 'Retail', 'brand' => 'Aura Pro'],
                ]
            );
        }

        // =========================================================================
        // 10. Membership Plans
        // =========================================================================
        $membershipPlans = [
            [
                'name' => 'Gói Thành Viên Bạc (Silver Pass)',
                'description' => 'Giảm 10% cho tất cả các lượt đặt sân trong khung giờ thường.',
                'discount_percent' => 10,
                'price' => 299000,
            ],
            [
                'name' => 'Gói Thành Viên Vàng VIP (Gold All-Access)',
                'description' => 'Giảm 20% đặt sân toàn hệ thống, tặng 2 nước Pocari mỗi lượt chơi và miễn phí gửi xe.',
                'discount_percent' => 20,
                'price' => 799000,
            ],
            [
                'name' => 'Gói Kim Cương Doanh Nghiệp (Diamond Club)',
                'description' => 'Giảm 30% giá sân, ưu tiên giữ lịch cố định theo tháng và sử dụng phòng tắm VIP Sauna.',
                'discount_percent' => 30,
                'price' => 1999000,
            ],
        ];

        foreach ($membershipPlans as $plan) {
            MembershipPlan::updateOrCreate(
                ['name' => $plan['name']],
                [
                    'description' => $plan['description'],
                    'discount_percent' => $plan['discount_percent'],
                    'is_active' => true,
                ]
            );

            TenantMembershipPlan::updateOrCreate(
                ['organization_id' => $org->id, 'name' => $plan['name']],
                [
                    'version' => 1,
                    'price' => $plan['price'],
                    'benefits' => ['discount' => $plan['discount_percent'], 'description' => $plan['description']],
                    'status' => 'published',
                    'published_at' => $now,
                ]
            );
        }

        // =========================================================================
        // =========================================================================
        // 11. Social Tournaments (Badminton, Pickleball, Tennis, Football)
        // =========================================================================
        $tournaments = [
            [
                'name' => 'Aura Badminton Summer Championship 2026',
                'format' => 'single_elimination',
                'status' => 'ongoing',
                'starts_at' => $now->copy()->addDays(2),
                'settings' => [
                    'max_participants' => 16,
                    'sport' => 'badminton',
                    'category' => 'Cầu Lông Đơn Nam Nâng Cao',
                    'prize_pool' => '20.000.000 ₫',
                    'venue' => 'Aura Badminton Complex',
                    'city' => 'Hà Nội',
                    'image_url' => 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=800&auto=format&fit=crop&q=80',
                ],
            ],
            [
                'name' => 'Aura Pickleball Dynamic Cup 2026',
                'format' => 'round_robin',
                'status' => 'open',
                'starts_at' => $now->copy()->addDays(14),
                'settings' => [
                    'max_participants' => 12,
                    'sport' => 'pickleball',
                    'category' => 'Pickleball Đôi Nam Nữ 3.5+',
                    'prize_pool' => '15.000.000 ₫',
                    'venue' => 'Aura Sport Hub Tây Hồ',
                    'city' => 'Hà Nội',
                    'image_url' => 'https://images.unsplash.com/photo-1599474924187-334a4ae5bd3c?w=800&auto=format&fit=crop&q=80',
                ],
            ],
            [
                'name' => 'Hanoi 5v5 Corporate Football League 2026',
                'format' => 'single_elimination',
                'status' => 'upcoming',
                'starts_at' => $now->copy()->addDays(20),
                'settings' => [
                    'max_participants' => 8,
                    'sport' => 'football_5v5',
                    'category' => 'Bóng Đá Mini Sân Cỏ 5 Người',
                    'prize_pool' => '30.000.000 ₫',
                    'venue' => 'Sân Bóng Mỹ Đình Stadium',
                    'city' => 'Hà Nội',
                    'image_url' => 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=800&auto=format&fit=crop&q=80',
                ],
            ],
            [
                'name' => 'Saigon Open Tennis Masters 2026',
                'format' => 'single_elimination',
                'status' => 'open',
                'starts_at' => $now->copy()->addDays(28),
                'settings' => [
                    'max_participants' => 32,
                    'sport' => 'tennis',
                    'category' => 'Quần Vợt Đơn Nam Chuyên Nghiệp',
                    'prize_pool' => '50.000.000 ₫',
                    'venue' => 'Aura Thảo Điền Tennis Club',
                    'city' => 'TP. Hồ Chí Minh',
                    'image_url' => 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?w=800&auto=format&fit=crop&q=80',
                ],
            ],
            [
                'name' => 'Da Nang Coastal Pickleball Open 2026',
                'format' => 'single_elimination',
                'status' => 'completed',
                'starts_at' => $now->copy()->subDays(10),
                'settings' => [
                    'max_participants' => 16,
                    'sport' => 'pickleball',
                    'category' => 'Pickleball Bãi Biển Sơn Trà',
                    'prize_pool' => '25.000.000 ₫',
                    'venue' => 'Aura Resort Beach Courts',
                    'city' => 'Đà Nẵng',
                    'image_url' => 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?w=800&auto=format&fit=crop&q=80',
                ],
            ],
            [
                'name' => 'Vietnam Badminton Super Cup 2026',
                'format' => 'single_elimination',
                'status' => 'open',
                'starts_at' => $now->copy()->addDays(40),
                'settings' => [
                    'max_participants' => 64,
                    'sport' => 'badminton',
                    'category' => 'Cầu Lông Đôi Nam Toàn Quốc',
                    'prize_pool' => '100.000.000 ₫',
                    'venue' => 'Nhà Thi Đấu Cầu Giấy',
                    'city' => 'Hà Nội',
                    'image_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=800&auto=format&fit=crop&q=80',
                ],
            ],
        ];

        $targetOrgs = Organization::where('status', 'active')->get();
        if ($targetOrgs->isEmpty()) {
            $targetOrgs = collect([$org]);
        }

        foreach ($targetOrgs as $currentOrg) {
            foreach ($tournaments as $tData) {
                $tournament = Tournament::updateOrCreate(
                    ['organization_id' => $currentOrg->id, 'name' => $tData['name']],
                    [
                        'created_by' => $admin->id,
                        'format' => $tData['format'],
                        'status' => $tData['status'],
                        'version' => 1,
                        'starts_at' => $tData['starts_at'],
                        'settings' => $tData['settings'],
                    ]
                );

                // Add 8 participants for tournament
                $participants = [];
                foreach (range(1, 8) as $pIdx) {
                    $user = $customers[$pIdx - 1];
                    $part = TournamentParticipant::updateOrCreate(
                        ['organization_id' => $currentOrg->id, 'tournament_id' => $tournament->id, 'user_id' => $user->id],
                        [
                            'seed' => $pIdx,
                            'status' => 'registered',
                            'checked_in_at' => in_array($tData['status'], ['ongoing', 'completed']) ? $now : null
                        ]
                    );
                    $participants[] = $part;
                }

                // Create Fixtures (Quarters, Semis, Finals)
                if (count($participants) >= 8) {
                    $isDoneOrLive = in_array($tData['status'], ['ongoing', 'completed']);

                    // Quarter Finals (Round 1)
                    $qfScores = [
                        ['p1' => 21, 'p2' => 18, 'winner' => 0],
                        ['p1' => 21, 'p2' => 19, 'winner' => 0],
                        ['p1' => 17, 'p2' => 21, 'winner' => 1],
                        ['p1' => 21, 'p2' => 15, 'winner' => 0],
                    ];

                    for ($pos = 1; $pos <= 4; $pos++) {
                        $p1Idx = ($pos - 1) * 2;
                        $p2Idx = $p1Idx + 1;
                        $cfg = $qfScores[$pos - 1];
                        $wId = $isDoneOrLive ? ($cfg['winner'] === 0 ? $participants[$p1Idx]->id : $participants[$p2Idx]->id) : null;

                        TournamentFixture::updateOrCreate(
                            ['organization_id' => $currentOrg->id, 'tournament_id' => $tournament->id, 'round' => 1, 'position' => $pos],
                            [
                                'participant_one_id' => $participants[$p1Idx]->id,
                                'participant_two_id' => $participants[$p2Idx]->id,
                                'winner_id' => $wId,
                                'score' => $isDoneOrLive ? ['p1' => $cfg['p1'], 'p2' => $cfg['p2'], 'sets' => [['p1' => $cfg['p1'], 'p2' => $cfg['p2']]]] : null,
                                'status' => $isDoneOrLive ? 'completed' : 'scheduled',
                                'version' => 1,
                            ]
                        );
                    }

                    // Semi Finals (Round 2)
                    $sfPairs = [
                        ['p1' => 0, 'p2' => 2, 'score' => ['p1' => 21, 'p2' => 17]],
                        ['p1' => 5, 'p2' => 6, 'score' => ['p1' => 19, 'p2' => 21]],
                    ];

                    for ($pos = 1; $pos <= 2; $pos++) {
                        $pair = $sfPairs[$pos - 1];
                        $wId = $tData['status'] === 'completed' ? $participants[$pair['p1']]->id : null;
                        $isLive = $tData['status'] === 'ongoing';

                        TournamentFixture::updateOrCreate(
                            ['organization_id' => $currentOrg->id, 'tournament_id' => $tournament->id, 'round' => 2, 'position' => $pos],
                            [
                                'participant_one_id' => $participants[$pair['p1']]->id,
                                'participant_two_id' => $participants[$pair['p2']]->id,
                                'winner_id' => $wId,
                                'score' => $tData['status'] === 'completed' ? $pair['score'] : ($isLive ? ['p1' => 14, 'p2' => 12] : null),
                                'status' => $tData['status'] === 'completed' ? 'completed' : ($isLive ? 'ongoing' : 'scheduled'),
                                'version' => 1,
                            ]
                        );
                    }

                    // Finals (Round 3)
                    $finalWinnerId = $tData['status'] === 'completed' ? $participants[0]->id : null;
                    TournamentFixture::updateOrCreate(
                        ['organization_id' => $currentOrg->id, 'tournament_id' => $tournament->id, 'round' => 3, 'position' => 1],
                        [
                            'participant_one_id' => $participants[0]->id,
                            'participant_two_id' => $participants[6]->id,
                            'winner_id' => $finalWinnerId,
                            'score' => $tData['status'] === 'completed' ? ['p1' => 21, 'p2' => 19, 'sets' => [['p1' => 21, 'p2' => 19]]] : null,
                            'status' => $tData['status'] === 'completed' ? 'completed' : 'scheduled',
                            'version' => 1,
                        ]
                    );
                }

                // Standings
                foreach ($participants as $rank => $part) {
                    TournamentStanding::updateOrCreate(
                        ['organization_id' => $currentOrg->id, 'tournament_id' => $tournament->id, 'participant_id' => $part->id],
                        [
                            'rank' => $rank + 1,
                            'points' => (8 - $rank) * 350,
                            'wins' => max(0, 4 - (int)($rank / 2)),
                            'losses' => min(3, (int)($rank / 2)),
                            'draws' => 0,
                            'played' => max(1, 4 - (int)($rank / 2)),
                        ]
                    );
                }
            }
        }



        // =========================================================================
        // 12. Matchmaking & Open Match Posts
        // =========================================================================
        $matchPostsData = [
            [
                'title' => 'Tìm 2 bạn đánh đôi nam/nữ cầu lông trình độ khá - Tối nay 19h Duy Tân',
                'starts_at' => $now->copy()->addDays(1)->setTime(19, 0),
                'ends_at' => $now->copy()->addDays(1)->setTime(21, 0),
                'city' => 'Hà Nội',
                'min_skill' => 3,
                'max_skill' => 5,
                'required_participants' => 4,
                'joined_count' => 2,
            ],
            [
                'title' => 'Giao lưu Pickleball đôi nam - Sân Aura Quần Ngựa chiều Chủ Nhật',
                'starts_at' => $now->copy()->addDays(3)->setTime(16, 0),
                'ends_at' => $now->copy()->addDays(3)->setTime(18, 0),
                'city' => 'Hà Nội',
                'min_skill' => 1,
                'max_skill' => 3,
                'required_participants' => 4,
                'joined_count' => 3,
            ],
            [
                'title' => 'Thiếu 2 chân đá bóng 5v5 sân cỏ nhân tạo A1 tối thứ 6 (20h-21h30)',
                'starts_at' => $now->copy()->addDays(2)->setTime(20, 0),
                'ends_at' => $now->copy()->addDays(2)->setTime(21, 30),
                'city' => 'Hà Nội',
                'min_skill' => 2,
                'max_skill' => 4,
                'required_participants' => 10,
                'joined_count' => 8,
            ],
            [
                'title' => 'Tìm bạn đánh đơn Tennis giao lưu học hỏi trình độ NTRP 3.0+ Tây Hồ',
                'starts_at' => $now->copy()->addDays(4)->setTime(7, 0),
                'ends_at' => $now->copy()->addDays(4)->setTime(9, 0),
                'city' => 'Hà Nội',
                'min_skill' => 3,
                'max_skill' => 5,
                'required_participants' => 2,
                'joined_count' => 1,
            ],
            [
                'title' => 'Kèo bóng rổ 3x3 cuối tuần Landmark 72 - Friendly match vui vẻ',
                'starts_at' => $now->copy()->addDays(5)->setTime(18, 0),
                'ends_at' => $now->copy()->addDays(5)->setTime(20, 0),
                'city' => 'Hà Nội',
                'min_skill' => 1,
                'max_skill' => 4,
                'required_participants' => 6,
                'joined_count' => 4,
            ],
            [
                'title' => 'Giao lưu Cầu lông phong trào Bình Thạnh Sài Gòn - Sân 11',
                'starts_at' => $now->copy()->addDays(2)->setTime(18, 30),
                'ends_at' => $now->copy()->addDays(2)->setTime(20, 30),
                'city' => 'TP. Hồ Chí Minh',
                'min_skill' => 2,
                'max_skill' => 4,
                'required_participants' => 4,
                'joined_count' => 3,
            ],
        ];

        foreach ($matchPostsData as $mIndex => $mp) {
            $creator = $customers[$mIndex % $customers->count()];

            $post = MatchPost::updateOrCreate(
                ['organization_id' => $org->id, 'title' => $mp['title']],
                [
                    'creator_id' => $creator->id,
                    'visibility' => 'public',
                    'starts_at' => $mp['starts_at'],
                    'ends_at' => $mp['ends_at'],
                    'city' => $mp['city'],
                    'approximate_latitude' => 21.0285,
                    'approximate_longitude' => 105.8542,
                    'min_skill' => $mp['min_skill'],
                    'max_skill' => $mp['max_skill'],
                    'required_participants' => $mp['required_participants'],
                    'joined_count' => $mp['joined_count'],
                    'status' => 'open',
                    'availability' => ['level' => 'all', 'court_booked' => true],
                    'metadata' => ['sport' => 'multi-sport', 'host' => $creator->name],
                ]
            );

            // Add Creator participation
            MatchParticipation::updateOrCreate(
                ['organization_id' => $org->id, 'match_post_id' => $post->id, 'user_id' => $creator->id],
                ['status' => 'accepted', 'joined_at' => $now->copy()->subHours(5)]
            );

            // Add other participants
            for ($p = 1; $p < $mp['joined_count']; $p++) {
                $joinedUser = $customers[($mIndex + $p) % $customers->count()];
                MatchParticipation::updateOrCreate(
                    ['organization_id' => $org->id, 'match_post_id' => $post->id, 'user_id' => $joinedUser->id],
                    ['status' => 'accepted', 'joined_at' => $now->copy()->subHours(2)]
                );
            }
        }

        // =========================================================================
        // 13. Host KYC Verifications
        // =========================================================================
        $kycRecords = [
            [
                'kyc_code' => 'KYC-2026-0892',
                'business_type' => 'enterprise',
                'business_name' => 'Smash City Arena',
                'legal_name' => 'CÔNG TY TNHH THỂ THAO SMASH CITY',
                'tax_code' => '0316589921',
                'tax_status' => 'Đang hoạt động',
                'representative_name' => 'NGUYEN VAN AN',
                'id_card_number' => '079092008892',
                'bank_name' => 'MBBank - Ngân hàng TMCP Quân Đội',
                'bank_code' => 'MBBANK',
                'bank_account_number' => '0909 888 999',
                'bank_account_holder' => 'CONG TY TNHH THE THAO SMASH CITY',
                'napas_match_status' => 'matched',
                'court_scale' => 12,
                'lighting_spec' => 'Hệ thống đèn LED chống chói 1000W',
                'lease_contract_status' => 'Hợp đồng 5 năm (đến 31/12/2030)',
                'facility_images' => ['/aura/court-1.jpg', '/aura/court-2.jpg'],
                'ai_ocr_score' => 99.0,
                'face_match_score' => 99.4,
                'status' => 'pending',
                'checklist_state' => [
                    'rep_identity_matched' => true,
                    'business_license_valid' => true,
                    'bank_account_matched' => true,
                    'brand_name_discrepancy_accepted' => false,
                    'facility_inspected' => true,
                ],
                'submitted_at' => $now->copy()->subHours(2),
            ],
            [
                'kyc_code' => 'KYC-2026-0891',
                'business_type' => 'business_household',
                'business_name' => 'Sân Cầu Lông Tân Bình Sport',
                'legal_name' => 'HỘ KINH DOANH TÂN BÌNH SPORT',
                'tax_code' => '8392104921',
                'tax_status' => 'Đang hoạt động',
                'representative_name' => 'TRAN THI BICH',
                'id_card_number' => '079194002341',
                'bank_name' => 'Techcombank - Ngân hàng TMCP Kỹ Thương',
                'bank_code' => 'TCB',
                'bank_account_number' => '1903 666 888',
                'bank_account_holder' => 'TRAN THI BICH',
                'napas_match_status' => 'matched',
                'court_scale' => 6,
                'lease_contract_status' => 'Chính chủ sở hữu mặt bằng',
                'facility_images' => ['/aura/court-3.jpg'],
                'ai_ocr_score' => 98.2,
                'face_match_score' => 99.0,
                'status' => 'pending',
                'checklist_state' => [
                    'rep_identity_matched' => true,
                    'business_license_valid' => true,
                    'bank_account_matched' => true,
                    'brand_name_discrepancy_accepted' => true,
                    'facility_inspected' => true,
                ],
                'submitted_at' => $now->copy()->subHours(4),
            ],
            [
                'kyc_code' => 'KYC-2026-0889',
                'business_type' => 'enterprise',
                'business_name' => 'Elite Badminton Club Q7',
                'legal_name' => 'CÔNG TY CP THỂ THAO ELITE VIỆT NAM',
                'tax_code' => '0317892110',
                'tax_status' => 'Đang hoạt động',
                'representative_name' => 'LE HOANG PHUC',
                'id_card_number' => '079088001122',
                'bank_name' => 'Vietcombank - Chi nhánh Nam Sài Gòn',
                'bank_code' => 'VCB',
                'bank_account_number' => '0071 000 888 999',
                'bank_account_holder' => 'CONG TY CP THE THAO ELITE VIET NAM',
                'napas_match_status' => 'matched',
                'court_scale' => 16,
                'lease_contract_status' => 'Hợp đồng 10 năm',
                'facility_images' => [],
                'ai_ocr_score' => 94.5,
                'face_match_score' => 96.8,
                'status' => 'flagged',
                'rejection_reason' => 'Cảnh báo: Trùng địa chỉ khai báo với một cơ sở đã đăng ký khác trên hệ thống.',
                'submitted_at' => $now->copy()->subDay(),
            ],
        ];

        foreach ($kycRecords as $kycData) {
            HostKyc::updateOrCreate(
                ['kyc_code' => $kycData['kyc_code']],
                array_merge($kycData, [
                    'organization_id' => $org->id,
                    'user_id' => $admin->id,
                ])
            );
        }

        // =========================================================================
        // 14. Dispute Cases
        // =========================================================================
        $disputeCases = [
            [
                'dispute_code' => 'DSP-8924',
                'court_name' => 'Sân số 4 (Phú Thọ)',
                'dispute_type' => 'double_booking',
                'severity' => 'critical',
                'title' => 'Xung đột Trùng Lịch Sân số 4',
                'summary' => 'Hệ thống ghi nhận đặt qua App của Alex Lawson và nhân viên quầy chèn tay lịch của Trần Quốc Bảo lúc 19:00.',
                'status' => 'investigating',
                'sla_deadline' => $now->copy()->addMinutes(15),
                'amount_disputed' => 360000,
                'currency' => 'VND',
                'complainant_name' => 'Alex Lawson',
                'complainant_tier' => 'Hạng Vàng',
                'complainant_reputation_score' => 98,
                'complainant_phone' => '0909 *** 892',
                'respondent_name' => 'Trần Quốc Bảo',
                'respondent_tier' => 'Hạng Kim Cương',
                'respondent_reputation_score' => 96,
                'respondent_phone' => '0912 *** 341',
                'facility_name' => 'Cụm Sân Cầu Lông Phú Thọ',
                'facility_operator_name' => 'Lê Hoàng Long (Trưởng ca trực)',
                'timeline_events' => [
                    [
                        'time' => '20 Thg 10, 18:30:15',
                        'title' => 'Thanh toán đặt sân thành công',
                        'description' => 'Alex Lawson thanh toán thành công đơn qua PayOS (Slot 19:00 - 21:00 Sân 4).',
                        'type' => 'primary',
                    ],
                    [
                        'time' => '20 Thg 10, 18:45:22',
                        'title' => 'Cảnh báo: Độ trễ Webhook Facility API',
                        'description' => 'Độ trễ đồng bộ 45 giây từ đối tác khiến trạng thái slot chưa cập nhật kịp thời trên bảng lễ tân.',
                        'type' => 'warning',
                    ],
                ],
                'chat_messages' => [
                    [
                        'sender' => 'Alex Lawson',
                        'role' => 'complainant',
                        'avatar' => 'AL',
                        'message' => 'Tôi đã đến sân lúc 18:55 nhưng sân số 4 đang có nhóm khác đánh. Lễ tân bảo họ có lịch đặt cố định.',
                        'time' => '19:05',
                    ],
                ],
                'court_map_context' => [
                    ['name' => 'Sân 3', 'status' => 'occupied', 'label' => 'Đang đánh'],
                    ['name' => 'Sân 4', 'status' => 'conflict', 'label' => 'Xung Đột'],
                    ['name' => 'Sân 7', 'status' => 'available', 'label' => 'Trống - Có thể Đổi'],
                ],
                'ai_analysis' => [
                    'fault_rate_facility' => 100,
                    'fault_rate_user' => 0,
                    'root_cause' => 'Độ trễ Webhook đồng bộ kết hợp thao tác ghi đè thủ công tại quầy của nhân viên cơ sở.',
                    'recommendation_summary' => 'Hoàn 100% tiền (360.000 ₫) vào Ví nội bộ cho Alex Lawson + Tặng Voucher 20% + Đổi Sân 7 cho Trần Quốc Bảo.',
                ],
            ],
            [
                'dispute_code' => 'DSP-8921',
                'court_name' => 'Sân Tân Bình Sport - Court 2',
                'dispute_type' => 'match_stake',
                'severity' => 'high',
                'title' => 'Tranh chấp Tiền cọc Kèo Giao Lưu Matchmaking',
                'summary' => 'Đối thủ đơn nam tự ý bỏ cuộc giữa chừng ở Set 3 nhưng không chịu xác nhận hủy cọc trên ví nội bộ.',
                'status' => 'pending',
                'sla_deadline' => $now->copy()->addHours(2),
                'amount_disputed' => 200000,
                'currency' => 'VND',
                'complainant_name' => 'Vũ Minh Tuấn',
                'complainant_tier' => 'Hạng Bạc',
                'complainant_reputation_score' => 92,
                'complainant_phone' => '0938 *** 112',
                'respondent_name' => 'Hoàng Nam',
                'respondent_tier' => 'Hạng Đồng',
                'respondent_reputation_score' => 84,
                'respondent_phone' => '0977 *** 554',
                'facility_name' => 'Sân Cầu Lông Tân Bình Sport',
                'facility_operator_name' => 'Trần Thị Bích',
                'timeline_events' => [],
                'chat_messages' => [],
                'court_map_context' => [],
                'ai_analysis' => [
                    'fault_rate_facility' => 0,
                    'fault_rate_user' => 100,
                    'root_cause' => 'Bỏ trận đấu giao lưu có đặt cọc không có lý do chính đáng.',
                    'recommendation_summary' => 'Xử thua kỹ thuật cho Hoàng Nam, giải ngân 200.000 ₫ tiền cọc cho Vũ Minh Tuấn và trừ 15 điểm uy tín đối thủ.',
                ],
            ],
        ];

        foreach ($disputeCases as $dispData) {
            DisputeCase::updateOrCreate(
                ['dispute_code' => $dispData['dispute_code']],
                array_merge($dispData, [
                    'organization_id' => $org->id,
                ])
            );
        }

        // =========================================================================
        // 15. Finance Exceptions & Reconciliation
        // =========================================================================
        $financeExceptions = [
            [
                'reference' => 'TXN-88291A',
                'type' => 'limit_exceeded',
                'severity' => 'critical',
                'status' => 'open',
                'amount' => 4500000,
                'currency' => 'VND',
                'description' => 'Vượt hạn mức nạp 24h từ tài khoản hội viên Vàng',
                'detected_at' => $now->copy()->subHours(3),
                'due_at' => $now->copy()->addHours(21),
                'metadata' => [
                    'shift' => 'Ca Sáng (06:00 - 14:00)',
                    'user' => ['name' => 'Lê Văn B.', 'id' => 'UID-442', 'tier' => 'Hội viên Vàng'],
                    'reason' => 'Vượt hạn mức nạp 24h',
                ],
            ],
            [
                'reference' => 'TXN-88290B',
                'type' => 'settlement_mismatch',
                'severity' => 'medium',
                'status' => 'open',
                'amount' => 12000000,
                'currency' => 'VND',
                'description' => 'Lệch đối soát VietQR giữa cổng thanh toán và sổ cái',
                'detected_at' => $now->copy()->subHours(4),
                'due_at' => $now->copy()->addHours(20),
                'metadata' => [
                    'shift' => 'Ca Sáng (06:00 - 14:00)',
                    'user' => ['name' => 'Trần Thị C.', 'id' => 'UID-119', 'tier' => 'Khách vãng lai'],
                    'reason' => 'Lệch đối soát VietQR',
                ],
            ],
            [
                'reference' => 'TXN-88289C',
                'type' => 'late_refund',
                'severity' => 'low',
                'status' => 'open',
                'amount' => 2150000,
                'currency' => 'VND',
                'description' => 'Hoàn tiền hủy sân quá 48h',
                'detected_at' => $now->copy()->subHours(6),
                'due_at' => $now->copy()->addHours(18),
                'metadata' => [
                    'shift' => 'Ca Sáng (06:00 - 14:00)',
                    'user' => ['name' => 'Minh Quân', 'id' => 'UID-992', 'tier' => 'Hội viên Kim Cương'],
                    'reason' => 'Hoàn tiền hủy sân quá 48h',
                ],
            ],
        ];

        foreach ($financeExceptions as $fEx) {
            FinanceException::updateOrCreate(
                ['reference' => $fEx['reference']],
                array_merge($fEx, [
                    'organization_id' => $org->id,
                ])
            );
        }

        ReconciliationRun::updateOrCreate(
            ['organization_id' => $org->id, 'run_date' => $now->toDateString()],
            [
                'status' => 'mismatch',
                'provider_total' => 842560000,
                'journal_total' => 842560000,
                'settlement_total' => 841160000,
                'payout_total' => 318400000,
                'mismatch_amount' => 1400000,
                'started_at' => $now->copy()->subHours(1),
                'completed_at' => $now,
                'details' => [
                    'channel' => 'VietQR Pro (MBBank)',
                    'txnCode' => 'TXN-88291A',
                    'riskLevel' => 'high',
                    'statusLabel' => 'Lệch Quyết Toán',
                    'reason' => 'Webhook Napas delay lúc 06:42:15, tài khoản trung gian chưa ghi có settlement.',
                    'court' => 'Sân Cầu Lông Aura Q7 - Sân VIP 1',
                    'customer' => 'Trần Minh Quang (UID-8821)',
                ],
            ]
        );

        $this->call(MarketplaceSeeder::class);
    }
}

