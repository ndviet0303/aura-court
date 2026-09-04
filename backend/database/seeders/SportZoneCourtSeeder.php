<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\PriceRule;
use App\Models\SportZone;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SportZoneCourtSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);
        $timestamp = $now->toDateTimeString();

        $org = Organization::where('slug', 'aura-badminton-club')->first() ?? Organization::first();
        $venues = Venue::all()->keyBy('slug');

        if ($venues->isEmpty()) {
            return;
        }

        // 1. Sport Zones
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
            if (! isset($venues[$sz['venue_slug']])) {
                continue;
            }
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

        // 2. Court Definitions
        $courtDefs = [
            // Cầu lông - Cầu Giấy (5 sân)
            [
                'name' => 'Sân 1 - Thảm BWF Tiêu Chuẩn',
                'slug' => 'san-1-tham-bwf-cau-giay',
                'description' => 'Sân cầu lông thảm BWF tiêu chuẩn 5 lớp, hệ thống đèn LED 500 Lux chống chói.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-badminton',
                'image_url' => '/courts/badminton-0.jpg',
                'base_price' => 100000,
                'sport_type' => 'badminton',
            ],
            [
                'name' => 'Sân 2 - Tiêu Chuẩn',
                'slug' => 'san-2-tieu-chuan-cau-giay',
                'description' => 'Mặt sàn cao cấp êm ái, trần cao 9.5m chuẩn thi đấu phong trào và bán chuyên.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-badminton',
                'image_url' => '/courts/badminton-court.jpg',
                'base_price' => 100000,
                'sport_type' => 'badminton',
            ],
            [
                'name' => 'Sân 3 - Tiêu Chuẩn',
                'slug' => 'san-3-tieu-chuan-cau-giay',
                'description' => 'Hệ thống đèn trần định hướng, không gian thoáng mát và thông gió liên tục.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-badminton',
                'image_url' => '/courts/badminton-night.jpg',
                'base_price' => 100000,
                'sport_type' => 'badminton',
            ],
            [
                'name' => 'Sân 4 - VIP Đơn/Đôi',
                'slug' => 'san-4-vip-cau-giay',
                'description' => 'Sân VIP không gian rộng rãi, có khu ghế nghỉ riêng biệt và điều hòa mát mẻ.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-badminton',
                'image_url' => '/courts/badminton-wood.jpg',
                'base_price' => 130000,
                'sport_type' => 'badminton',
            ],
            [
                'name' => 'Sân 5 - VIP Tournament',
                'slug' => 'san-5-vip-tournament-cau-giay',
                'description' => 'Sân thi đấu giải quốc gia, thảm chuyên dụng đàn hồi cao giảm tối đa chấn thương.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-badminton',
                'image_url' => '/courts/badminton-1.jpg',
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
                'image_url' => '/courts/pickleball-neon.jpg',
                'base_price' => 130000,
                'sport_type' => 'pickleball',
            ],
            [
                'name' => 'Sân Pickleball 2 - Dynamic Pro',
                'slug' => 'san-pickleball-2-dynamic-cau-giay',
                'description' => 'Sân Pickleball cao cấp tiêu chuẩn USA Pickleball, lưới kéo cáp chống rung.',
                'venue_slug' => 'aura-court',
                'zone_key' => 'cg-pickleball',
                'image_url' => '/courts/pickleball-outdoor.jpg',
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
                'image_url' => '/courts/table-tennis-1.jpg',
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
                'image_url' => '/courts/badminton-night.jpg',
                'base_price' => 120000,
                'sport_type' => 'badminton',
            ],
            [
                'name' => 'Sân 7 - Tiêu Chuẩn Quần Ngựa',
                'slug' => 'san-7-tieu-chuan-quan-ngua',
                'description' => 'Sân tập luyện và giao lưu câu lạc bộ tại Ba Đình, tiện nghi đầy đủ và bãi xe rộng.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'zone_key' => 'qn-badminton',
                'image_url' => '/courts/badminton-court.jpg',
                'base_price' => 100000,
                'sport_type' => 'badminton',
            ],
            [
                'name' => 'Sân 8 - Pro Replay Quần Ngựa',
                'slug' => 'san-8-pro-replay-quan-ngua',
                'description' => 'Sân công nghệ cao trang bị hệ thống camera xem lại pha cầu (VAR/Replay) thông minh.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'zone_key' => 'qn-badminton',
                'image_url' => '/courts/badminton-0.jpg',
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
                'image_url' => '/courts/football-mini-turf.jpg',
                'base_price' => 350000,
                'sport_type' => 'football_5v5',
            ],
            [
                'name' => 'Sân Bóng Đá 5v5 A2 Quần Ngựa',
                'slug' => 'san-bong-da-5v5-a2-quan-ngua',
                'description' => 'Sân bóng đá mini khu vực khán đài B, mặt cỏ chất lượng cao thoát nước cực nhanh.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'zone_key' => 'qn-football-5v5',
                'image_url' => '/courts/football-3.jpg',
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
                'image_url' => '/courts/football-wide.jpg',
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
                'image_url' => '/courts/table-tennis-2.jpg',
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
                'image_url' => '/courts/tennis-1.jpg',
                'base_price' => 250000,
                'sport_type' => 'tennis',
            ],
            [
                'name' => 'Sân Tennis 2 - Hard Court Pro Tây Hồ',
                'slug' => 'san-tennis-2-hard-court-tay-ho',
                'description' => 'Sân cứng chuyên nghiệp sơn Plexipave độ nảy chuẩn xác, view thoáng mát Hồ Tây.',
                'venue_slug' => 'aura-complex-tay-ho',
                'zone_key' => 'th-tennis',
                'image_url' => '/courts/tennis-2.jpg',
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
                'image_url' => '/courts/pickleball-outdoor.jpg',
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
                'image_url' => '/courts/badminton-wood.jpg',
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
                'image_url' => '/courts/basketball-2.jpg',
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
                'image_url' => '/courts/basketball-nba.jpg',
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
                'image_url' => '/courts/tennis-3.jpg',
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
                'image_url' => '/courts/badminton-0.jpg',
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
                'image_url' => '/courts/volleyball-indoor.jpg',
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
                'image_url' => '/courts/badminton-night.jpg',
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
                'image_url' => '/courts/football-saigon.jpg',
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
                'image_url' => '/courts/pickleball-neon.jpg',
                'base_price' => 160000,
                'sport_type' => 'pickleball',
            ],
        ];

        foreach ($courtDefs as $def) {
            if (! isset($venues[$def['venue_slug']]) || ! isset($sportZones[$def['zone_key']])) {
                continue;
            }
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
            if ($org) {
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
        }
    }
}
