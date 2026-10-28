<?php

namespace Database\Seeders;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Models\Court;
use App\Models\CourtPriceTableLink;
use App\Models\PriceTable;
use App\Models\Venue;
use App\Models\VenueOperatingSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CourtSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $venues = Venue::all()->keyBy('slug');

        if ($venues->isEmpty()) {
            return;
        }

        // Court definitions; each court carries its own sport.
        $courtDefs = [
            // Cầu lông - Cầu Giấy (5 sân)
            [
                'code' => 'Sân 1 - Thảm BWF Tiêu Chuẩn',
                'description' => 'Sân cầu lông thảm BWF tiêu chuẩn 5 lớp, hệ thống đèn LED 500 Lux chống chói.',
                'venue_slug' => 'aura-court',
                'image_url' => '/courts/badminton-0.jpg',
                'base_price' => 100000,
                'sport_type' => 'badminton',
            ],
            [
                'code' => 'Sân 2 - Tiêu Chuẩn',
                'description' => 'Mặt sàn cao cấp êm ái, trần cao 9.5m chuẩn thi đấu phong trào và bán chuyên.',
                'venue_slug' => 'aura-court',
                'image_url' => '/courts/badminton-court.jpg',
                'base_price' => 100000,
                'sport_type' => 'badminton',
            ],
            [
                'code' => 'Sân 3 - Tiêu Chuẩn',
                'description' => 'Hệ thống đèn trần định hướng, không gian thoáng mát và thông gió liên tục.',
                'venue_slug' => 'aura-court',
                'image_url' => '/courts/badminton-night.jpg',
                'base_price' => 100000,
                'sport_type' => 'badminton',
            ],
            [
                'code' => 'Sân 4 - VIP Đơn/Đôi',
                'description' => 'Sân VIP không gian rộng rãi, có khu ghế nghỉ riêng biệt và điều hòa mát mẻ.',
                'venue_slug' => 'aura-court',
                'image_url' => '/courts/badminton-wood.jpg',
                'base_price' => 130000,
                'sport_type' => 'badminton',
            ],
            [
                'code' => 'Sân 5 - VIP Tournament',
                'description' => 'Sân thi đấu giải quốc gia, thảm chuyên dụng đàn hồi cao giảm tối đa chấn thương.',
                'venue_slug' => 'aura-court',
                'image_url' => '/courts/badminton-1.jpg',
                'base_price' => 140000,
                'sport_type' => 'badminton',
            ],

            // Pickleball - Cầu Giấy (2 sân)
            [
                'code' => 'Sân Pickleball 1 - Aura Neon',
                'description' => 'Sân Pickleball hiện đại phủ Acrylic 5 lớp, viền line neon dạ quang nổi bật.',
                'venue_slug' => 'aura-court',
                'image_url' => '/courts/pickleball-neon.jpg',
                'base_price' => 130000,
                'sport_type' => 'pickleball',
            ],
            [
                'code' => 'Sân Pickleball 2 - Dynamic Pro',
                'description' => 'Sân Pickleball cao cấp tiêu chuẩn USA Pickleball, lưới kéo cáp chống rung.',
                'venue_slug' => 'aura-court',
                'image_url' => '/courts/pickleball-outdoor.jpg',
                'base_price' => 130000,
                'sport_type' => 'pickleball',
            ],

            // Bóng bàn - Cầu Giấy (1 sân)
            [
                'code' => 'Sân Bóng Bàn 1 - Double Fish ITTF',
                'description' => 'Bàn bóng bàn thi đấu Double Fish chuẩn quốc tế, thảm đỏ cao su chống trượt.',
                'venue_slug' => 'aura-court',
                'image_url' => '/courts/table-tennis-1.jpg',
                'base_price' => 60000,
                'sport_type' => 'table_tennis',
            ],

            // Cầu lông - Quần Ngựa (3 sân)
            [
                'code' => 'Sân 6 - Khán Đài Quần Ngựa',
                'description' => 'Sân trung tâm Cung Thể Thao Quần Ngựa có khán đài mini, thảm thi đấu BWF cao cấp.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'image_url' => '/courts/badminton-night.jpg',
                'base_price' => 120000,
                'sport_type' => 'badminton',
            ],
            [
                'code' => 'Sân 7 - Tiêu Chuẩn Quần Ngựa',
                'description' => 'Sân tập luyện và giao lưu câu lạc bộ tại Ba Đình, tiện nghi đầy đủ và bãi xe rộng.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'image_url' => '/courts/badminton-court.jpg',
                'base_price' => 100000,
                'sport_type' => 'badminton',
            ],
            [
                'code' => 'Sân 8 - Pro Replay Quần Ngựa',
                'description' => 'Sân công nghệ cao trang bị hệ thống camera xem lại pha cầu (VAR/Replay) thông minh.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'image_url' => '/courts/badminton-0.jpg',
                'base_price' => 140000,
                'sport_type' => 'badminton',
            ],

            // Bóng đá 5v5 - Quần Ngựa (2 sân)
            [
                'code' => 'Sân Bóng Đá 5v5 A1 Quần Ngựa',
                'description' => 'Sân bóng mini 5 người cỏ nhân tạo sợi mềm không chấn thương, đèn chiếu sáng 800 Lux.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'image_url' => '/courts/football-mini-turf.jpg',
                'base_price' => 350000,
                'sport_type' => 'football_5v5',
            ],
            [
                'code' => 'Sân Bóng Đá 5v5 A2 Quần Ngựa',
                'description' => 'Sân bóng đá mini khu vực khán đài B, mặt cỏ chất lượng cao thoát nước cực nhanh.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'image_url' => '/courts/football-3.jpg',
                'base_price' => 350000,
                'sport_type' => 'football_5v5',
            ],

            // Bóng đá 7v7 - Quần Ngựa (1 sân)
            [
                'code' => 'Sân Bóng Đá 7v7 C1 Quần Ngựa',
                'description' => 'Sân 7 người tiêu chuẩn thi đấu phong trào Hà Nội, cỏ nhập khẩu đạt chuẩn FIFA 2 sao.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'image_url' => '/courts/football-wide.jpg',
                'base_price' => 750000,
                'sport_type' => 'football_7v7',
            ],

            // Bóng bàn - Quần Ngựa (1 sân)
            [
                'code' => 'Sân Bóng Bàn 2 - Stiga Master Quần Ngựa',
                'description' => 'Phòng tập bóng bàn máy lạnh riêng biệt, trang bị máy bắn bóng tự động tập kỹ thuật.',
                'venue_slug' => 'aura-arena-quan-ngua',
                'image_url' => '/courts/table-tennis-2.jpg',
                'base_price' => 70000,
                'sport_type' => 'table_tennis',
            ],

            // Tennis - Tây Hồ (2 sân)
            [
                'code' => 'Sân Tennis 1 - Đất Nện French Open Tây Hồ',
                'description' => 'Sân đất nện đỏ tiêu chuẩn Roland Garros độc đáo tại Hà Nội, trượt êm ái bảo vệ khớp gối.',
                'venue_slug' => 'aura-complex-tay-ho',
                'image_url' => '/courts/tennis-1.jpg',
                'base_price' => 250000,
                'sport_type' => 'tennis',
            ],
            [
                'code' => 'Sân Tennis 2 - Hard Court Pro Tây Hồ',
                'description' => 'Sân cứng chuyên nghiệp sơn Plexipave độ nảy chuẩn xác, view thoáng mát Hồ Tây.',
                'venue_slug' => 'aura-complex-tay-ho',
                'image_url' => '/courts/tennis-2.jpg',
                'base_price' => 220000,
                'sport_type' => 'tennis',
            ],

            // Pickleball - Tây Hồ (1 sân)
            [
                'code' => 'Sân Pickleball 3 - Sunset View Hồ Tây',
                'description' => 'Sân Pickleball ngoài trời ngắm hoàng hôn Hồ Tây cực chill, có lounge nước ép thể thao.',
                'venue_slug' => 'aura-complex-tay-ho',
                'image_url' => '/courts/pickleball-outdoor.jpg',
                'base_price' => 150000,
                'sport_type' => 'pickleball',
            ],

            // Cầu lông - Tây Hồ (1 sân)
            [
                'code' => 'Sân 9 - Thảm Victor Tây Hồ',
                'description' => 'Sân cầu lông cao cấp Victor, hệ thống cách âm và điều hòa thông gió hiện đại.',
                'venue_slug' => 'aura-complex-tay-ho',
                'image_url' => '/courts/badminton-wood.jpg',
                'base_price' => 120000,
                'sport_type' => 'badminton',
            ],

            // Bóng rổ - Tây Hồ (1 sân)
            [
                'code' => 'Sân Bóng Rổ 1 - Streetball Zone Tây Hồ',
                'description' => 'Sân bóng rổ 3x3 và 5x5 phong cách đường phố, vành rổ chuyên dụng dunk êm ái.',
                'venue_slug' => 'aura-complex-tay-ho',
                'image_url' => '/courts/basketball-2.jpg',
                'base_price' => 250000,
                'sport_type' => 'basketball',
            ],

            // Bóng rổ - Landmark 72 (1 sân)
            [
                'code' => 'Sân Bóng Rổ Trong Nhà NBA Landmark',
                'description' => 'Sân bóng rổ trong nhà sàn gỗ tự nhiên tiêu chuẩn thi đấu NBA, điều hòa trung tâm 24/7.',
                'venue_slug' => 'aura-landmark-72',
                'image_url' => '/courts/basketball-nba.jpg',
                'base_price' => 380000,
                'sport_type' => 'basketball',
            ],

            // Tennis - Landmark 72 (1 sân)
            [
                'code' => 'Sân Tennis 3 - Mặt Cứng US Open Landmark',
                'description' => 'Sân tennis trong nhà đỉnh cao tại Keangnam Landmark 72, ánh sáng chuẩn truyền hình.',
                'venue_slug' => 'aura-landmark-72',
                'image_url' => '/courts/tennis-3.jpg',
                'base_price' => 320000,
                'sport_type' => 'tennis',
            ],

            // Cầu lông - Landmark 72 (1 sân)
            [
                'code' => 'Sân 10 - Yonex Pro Landmark',
                'description' => 'Sân cầu lông phân khúc thượng lưu, thảm Yonex Super Cushion, phòng tắm nóng lạnh.',
                'venue_slug' => 'aura-landmark-72',
                'image_url' => '/courts/badminton-0.jpg',
                'base_price' => 160000,
                'sport_type' => 'badminton',
            ],

            // Bóng chuyền - Landmark 72 (1 sân)
            [
                'code' => 'Sân Bóng Chuyền 1 - Thảm Taraflex Landmark',
                'description' => 'Sân bóng chuyền trong nhà thảm Taraflex Gerflor 7mm giảm tải chấn thương tối đa.',
                'venue_slug' => 'aura-landmark-72',
                'image_url' => '/courts/volleyball-indoor.jpg',
                'base_price' => 280000,
                'sport_type' => 'volleyball',
            ],

            // Cầu lông - Sài Gòn (1 sân)
            [
                'code' => 'Sân 11 - Sài Gòn Central BWF',
                'description' => 'Sân cầu lông BWF tại Điện Biên Phủ Bình Thạnh, thảm mới 100%, bãi xe rộng rãi.',
                'venue_slug' => 'aura-central-saigon',
                'image_url' => '/courts/badminton-night.jpg',
                'base_price' => 110000,
                'sport_type' => 'badminton',
            ],

            // Bóng đá 5v5 - Sài Gòn (1 sân)
            [
                'code' => 'Sân Bóng Đá 5v5 Sài Gòn Central',
                'description' => 'Sân bóng đá mini 5 người trung tâm Sài Gòn, dàn đèn LED không chói lóa mắt.',
                'venue_slug' => 'aura-central-saigon',
                'image_url' => '/courts/football-saigon.jpg',
                'base_price' => 380000,
                'sport_type' => 'football_5v5',
            ],

            // Pickleball - Sài Gòn (1 sân)
            [
                'code' => 'Sân Pickleball 4 - Rooftop View Landmark 81',
                'description' => 'Sân Pickleball tầng thượng ngắm Landmark 81 rực rỡ, gió mát tự nhiên, quầy bar cocktail thể thao.',
                'venue_slug' => 'aura-central-saigon',
                'image_url' => '/courts/pickleball-neon.jpg',
                'base_price' => 160000,
                'sport_type' => 'pickleball',
            ],

            // Hoàng Gia Sports Phú Mỹ Hưng (4 sân)
            [
                'code' => 'Sân Pickleball PMH 1 - Mái Che',
                'description' => 'Sân pickleball có mái che chống nắng mưa, phù hợp chơi cả ngày tại Phú Mỹ Hưng.',
                'venue_slug' => 'hoang-gia-phu-my-hung',
                'image_url' => '/courts/pickleball-1.jpg',
                'base_price' => 140000,
                'sport_type' => 'pickleball',
            ],
            [
                'code' => 'Sân Pickleball PMH 2 - Thi Đấu',
                'description' => 'Sân pickleball tiêu chuẩn thi đấu, lưới kéo cáp và đèn LED 600 Lux.',
                'venue_slug' => 'hoang-gia-phu-my-hung',
                'image_url' => '/courts/pickleball-2.jpg',
                'base_price' => 150000,
                'sport_type' => 'pickleball',
            ],
            [
                'code' => 'Sân Tennis PMH - Hồ Bán Nguyệt',
                'description' => 'Sân tennis mặt cứng view hồ Bán Nguyệt, có huấn luyện viên kèm theo yêu cầu.',
                'venue_slug' => 'hoang-gia-phu-my-hung',
                'image_url' => '/courts/tennis-2.jpg',
                'base_price' => 240000,
                'sport_type' => 'tennis',
            ],
            [
                'code' => 'Sân Bóng Đá Mini PMH',
                'description' => 'Sân bóng đá 5 người cỏ nhân tạo thế hệ mới, có khán đài và phòng thay đồ.',
                'venue_slug' => 'hoang-gia-phu-my-hung',
                'image_url' => '/courts/football-1.jpg',
                'base_price' => 360000,
                'sport_type' => 'football_5v5',
            ],
        ];

        // Round-robin by sport so the first courts by id (homepage grid) show a mix of sports.
        $bySport = collect($courtDefs)->groupBy('sport_type');
        $courtDefs = [];
        for ($i = 0; $i < $bySport->max(fn ($defs) => $defs->count()); $i++) {
            foreach ($bySport as $defs) {
                if (isset($defs[$i])) {
                    $courtDefs[] = $defs[$i];
                }
            }
        }

        $venueHours = VenueOperatingSchedule::query()->where('scope_key', 'venue')->get()->keyBy('venue_id');
        $courtIdsByPrice = [];
        foreach ($courtDefs as $def) {
            if (! isset($venues[$def['venue_slug']])) {
                continue;
            }
            $venue = $venues[$def['venue_slug']];

            // Seeded codes are unique across all venues, so the other seeders find a court by its code alone.
            $court = Court::updateOrCreate(
                ['venue_id' => $venue->id, 'code' => $def['code']],
                [
                    'description' => $def['description'],
                    'images' => [$def['image_url']],
                    'sport_type' => $def['sport_type'],
                    'status' => Court::STATUS_ACTIVE,
                ]
            );

            // UC-2.7 A-1: a court has its own service schedule, here the venue's demo hours; it never inherits them.
            $hours = $venueHours->get($venue->id);
            if ($hours) {
                VenueOperatingSchedule::firstOrCreate(
                    ['venue_id' => $venue->id, 'scope_key' => 'court:'.$court->id],
                    ['court_id' => $court->id, 'version' => 1, 'timezone' => $hours->timezone, 'weekly_hours' => $hours->weekly_hours],
                );
            }

            $courtIdsByPrice[$venue->id][$def['base_price']][] = $court->id;
        }

        $this->seedPriceTables($courtIdsByPrice, $now);
    }

    /**
     * Per venue and base price, a normal table (peak 17:00–22:00 and weekends dearer, rounded to 5.000 ₫) and a
     * holiday table for the coming public holidays, both covering the demo hours 06:00–23:00. Linked from a year
     * back so the past demo bookings price too; courts already linked are left as they are.
     *
     * @param  array<int, array<int, list<int>>>  $courtIdsByPrice  venue id => base price => court ids
     */
    private function seedPriceTables(array $courtIdsByPrice, Carbon $now): void
    {
        $tables = app(PriceTableRepositoryInterface::class);
        $since = $now->copy()->subYear();
        $holidays = array_map(fn (string $monthDay): string => ($date = Carbon::parse($now->year.'-'.$monthDay, $now->timezone))->lt($now->copy()->startOfDay())
            ? $date->addYear()->toDateString()
            : $date->toDateString(), ['01-01', '04-30', '05-01', '09-02']);
        foreach ($courtIdsByPrice as $venueId => $byPrice) {
            foreach ($byPrice as $base => $courtIds) {
                $price = fn (bool $weekend, bool $peak): int => (int) (round(($base + ($weekend ? (int) round($base * 0.2) : 0) + ($peak ? (int) round($base * 0.25) : 0)) / 5000) * 5000);
                $day = fn (array $weekdays, bool $weekend): array => array_map(fn (array $part): array => [
                    'weekdays' => $weekdays, 'dates' => null, 'start_minute' => $part[0], 'end_minute' => $part[1], 'price_per_hour' => $price($weekend, $part[2]),
                ], [[360, 1020, false], [1020, 1320, true], [1320, 1380, false]]);
                $label = number_format($base, 0, ',', '.').' ₫';
                foreach ([
                    PriceTable::KIND_NORMAL => ["Bảng giá {$label}", [...$day([1, 2, 3, 4, 5], false), ...$day([6, 7], true)]],
                    PriceTable::KIND_HOLIDAY => ["Ngày lễ {$label}", [['weekdays' => null, 'dates' => $holidays, 'start_minute' => 360, 'end_minute' => 1380, 'price_per_hour' => $price(true, true)]]],
                ] as $kind => [$name, $intervals]) {
                    $unlinked = array_values(array_diff($courtIds, CourtPriceTableLink::whereIn('court_id', $courtIds)->where('kind', $kind)->pluck('court_id')->all()));
                    if ($unlinked === []) {
                        continue;
                    }
                    $table = $tables->create(['venue_id' => $venueId, 'name' => $name, 'kind' => $kind]);
                    $tables->publishVersion($table, $since, $since, $intervals);
                    $tables->createLinks($table, $unlinked, $since);
                }
            }
        }
    }
}
