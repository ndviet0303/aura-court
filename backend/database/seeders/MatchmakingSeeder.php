<?php

namespace Database\Seeders;

use App\Models\MatchParticipation;
use App\Models\MatchPost;
use App\Models\Organization;
use App\Models\PlayerProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MatchmakingSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $customers = User::where('role', 'customer')->get();
        if ($customers->isEmpty()) {
            return;
        }

        $matchPostsData = [
            // 1. Cầu lông
            [
                'title' => 'Tìm 2 bạn đánh đôi nam/nữ cầu lông trình độ khá - Tối nay 19h Duy Tân',
                'starts_at' => $now->copy()->addDays(1)->setTime(19, 0),
                'ends_at' => $now->copy()->addDays(1)->setTime(21, 0),
                'city' => 'Hà Nội',
                'min_skill' => 3,
                'max_skill' => 5,
                'required_participants' => 4,
                'joined_count' => 2,
                'sport' => 'badminton',
                'venue_name' => 'Aura Court Cầu Giấy',
                'court_name' => 'Sân 1 - Thảm BWF Tiêu Chuẩn',
                'game_format' => 'Đánh đôi',
                'cost_per_person' => 50000,
            ],
            [
                'title' => 'Giao lưu Cầu lông phong trào Bình Thạnh Sài Gòn - Sân 11 BWF',
                'starts_at' => $now->copy()->addDays(2)->setTime(18, 30),
                'ends_at' => $now->copy()->addDays(2)->setTime(20, 30),
                'city' => 'TP. Hồ Chí Minh',
                'min_skill' => 2,
                'max_skill' => 4,
                'required_participants' => 4,
                'joined_count' => 3,
                'sport' => 'badminton',
                'venue_name' => 'Aura Sports Center Sài Gòn',
                'court_name' => 'Sân 11 - Sài Gòn Central BWF',
                'game_format' => 'Đánh đôi',
                'cost_per_person' => 55000,
            ],
            [
                'title' => 'Kèo Cầu lông phân hạng VIP - Tối thứ 7 Sân 10 Yonex Pro Landmark 72',
                'starts_at' => $now->copy()->addDays(4)->setTime(19, 30),
                'ends_at' => $now->copy()->addDays(4)->setTime(21, 30),
                'city' => 'Hà Nội',
                'min_skill' => 4,
                'max_skill' => 5,
                'required_participants' => 4,
                'joined_count' => 2,
                'sport' => 'badminton',
                'venue_name' => 'Aura Multi-Sport Landmark 72',
                'court_name' => 'Sân 10 - Yonex Pro Landmark',
                'game_format' => 'Đánh đôi nâng cao',
                'cost_per_person' => 80000,
            ],
            [
                'title' => 'Giao lưu đôi nam cầu lông phong trào Ba Đình - Sân 6 Quần Ngựa',
                'starts_at' => $now->copy()->addDays(3)->setTime(18, 0),
                'ends_at' => $now->copy()->addDays(3)->setTime(20, 0),
                'city' => 'Hà Nội',
                'min_skill' => 2,
                'max_skill' => 4,
                'required_participants' => 4,
                'joined_count' => 3,
                'sport' => 'badminton',
                'venue_name' => 'Aura Arena Quần Ngựa',
                'court_name' => 'Sân 6 - Khán Đài Quần Ngựa',
                'game_format' => 'Đánh đôi',
                'cost_per_person' => 60000,
            ],

            // 2. Pickleball
            [
                'title' => 'Giao lưu Pickleball đôi nam - Sân Aura Quần Ngựa chiều Chủ Nhật',
                'starts_at' => $now->copy()->addDays(3)->setTime(16, 0),
                'ends_at' => $now->copy()->addDays(3)->setTime(18, 0),
                'city' => 'Hà Nội',
                'min_skill' => 1,
                'max_skill' => 3,
                'required_participants' => 4,
                'joined_count' => 3,
                'sport' => 'pickleball',
                'venue_name' => 'Aura Arena Quần Ngựa',
                'court_name' => 'Sân Pickleball 2 - Dynamic Pro',
                'game_format' => 'Đánh đôi',
                'cost_per_person' => 65000,
            ],
            [
                'title' => 'Kèo Pickleball Sunset view Hồ Tây - Luyện tập dink & drop shot 17h',
                'starts_at' => $now->copy()->addDays(2)->setTime(17, 0),
                'ends_at' => $now->copy()->addDays(2)->setTime(19, 0),
                'city' => 'Hà Nội',
                'min_skill' => 2,
                'max_skill' => 4,
                'required_participants' => 4,
                'joined_count' => 2,
                'sport' => 'pickleball',
                'venue_name' => 'Aura Sports Complex Tây Hồ',
                'court_name' => 'Sân Pickleball 3 - Sunset View Hồ Tây',
                'game_format' => 'Đánh đôi',
                'cost_per_person' => 75000,
            ],
            [
                'title' => 'Tuyển 2 tay vợt Pickleball DUPR 3.5+ sân Rooftop Sài Gòn tối thứ 5',
                'starts_at' => $now->copy()->addDays(3)->setTime(19, 0),
                'ends_at' => $now->copy()->addDays(3)->setTime(21, 0),
                'city' => 'TP. Hồ Chí Minh',
                'min_skill' => 3,
                'max_skill' => 5,
                'required_participants' => 4,
                'joined_count' => 2,
                'sport' => 'pickleball',
                'venue_name' => 'Aura Sports Center Sài Gòn',
                'court_name' => 'Sân Pickleball 4 - Rooftop View Landmark 81',
                'game_format' => 'Đánh đôi',
                'cost_per_person' => 80000,
            ],
            [
                'title' => 'Pickleball đôi nam nữ giao lưu vui vẻ cho người mới chơi - Sân 1 Cầu Giấy',
                'starts_at' => $now->copy()->addDays(1)->setTime(18, 0),
                'ends_at' => $now->copy()->addDays(1)->setTime(20, 0),
                'city' => 'Hà Nội',
                'min_skill' => 1,
                'max_skill' => 2,
                'required_participants' => 4,
                'joined_count' => 1,
                'sport' => 'pickleball',
                'venue_name' => 'Aura Court Cầu Giấy',
                'court_name' => 'Sân Pickleball 1 - Aura Neon',
                'game_format' => 'Đánh đôi',
                'cost_per_person' => 65000,
            ],

            // 3. Tennis
            [
                'title' => 'Tìm bạn đánh đơn Tennis giao lưu học hỏi trình độ NTRP 3.0+ Tây Hồ',
                'starts_at' => $now->copy()->addDays(4)->setTime(7, 0),
                'ends_at' => $now->copy()->addDays(4)->setTime(9, 0),
                'city' => 'Hà Nội',
                'min_skill' => 3,
                'max_skill' => 5,
                'required_participants' => 2,
                'joined_count' => 1,
                'sport' => 'tennis',
                'venue_name' => 'Aura Sports Complex Tây Hồ',
                'court_name' => 'Sân Tennis 2 - Hard Court Pro Tây Hồ',
                'game_format' => 'Đánh đơn',
                'cost_per_person' => 110000,
            ],
            [
                'title' => 'Kèo Tennis đôi nam sân đất nện Roland Garros Tây Hồ sáng CN 6h30',
                'starts_at' => $now->copy()->addDays(5)->setTime(6, 30),
                'ends_at' => $now->copy()->addDays(5)->setTime(8, 30),
                'city' => 'Hà Nội',
                'min_skill' => 3,
                'max_skill' => 5,
                'required_participants' => 4,
                'joined_count' => 3,
                'sport' => 'tennis',
                'venue_name' => 'Aura Sports Complex Tây Hồ',
                'court_name' => 'Sân Tennis 1 - Đất Nện French Open Tây Hồ',
                'game_format' => 'Đánh đôi',
                'cost_per_person' => 125000,
            ],
            [
                'title' => 'Tennis giao lưu câu lạc bộ US Open Landmark 72 tối thứ 4',
                'starts_at' => $now->copy()->addDays(2)->setTime(19, 0),
                'ends_at' => $now->copy()->addDays(2)->setTime(21, 0),
                'city' => 'Hà Nội',
                'min_skill' => 3,
                'max_skill' => 4,
                'required_participants' => 4,
                'joined_count' => 2,
                'sport' => 'tennis',
                'venue_name' => 'Aura Multi-Sport Landmark 72',
                'court_name' => 'Sân Tennis 3 - Mặt Cứng US Open Landmark',
                'game_format' => 'Đánh đôi',
                'cost_per_person' => 160000,
            ],

            // 4. Bóng đá
            [
                'title' => 'Thiếu 2 chân đá bóng 5v5 sân cỏ nhân tạo A1 tối thứ 6 (20h-21h30)',
                'starts_at' => $now->copy()->addDays(2)->setTime(20, 0),
                'ends_at' => $now->copy()->addDays(2)->setTime(21, 30),
                'city' => 'Hà Nội',
                'min_skill' => 2,
                'max_skill' => 4,
                'required_participants' => 10,
                'joined_count' => 8,
                'sport' => 'football',
                'venue_name' => 'Aura Arena Quần Ngựa',
                'court_name' => 'Sân Bóng Đá 5v5 A1 Quần Ngựa',
                'game_format' => '5v5',
                'cost_per_person' => 70000,
            ],
            [
                'title' => 'Giao lưu bóng đá mini 5v5 Sài Gòn Central tối thứ 7 vui vẻ, chia tiền sân',
                'starts_at' => $now->copy()->addDays(4)->setTime(19, 30),
                'ends_at' => $now->copy()->addDays(4)->setTime(21, 0),
                'city' => 'TP. Hồ Chí Minh',
                'min_skill' => 2,
                'max_skill' => 4,
                'required_participants' => 10,
                'joined_count' => 7,
                'sport' => 'football',
                'venue_name' => 'Aura Sports Center Sài Gòn',
                'court_name' => 'Sân Bóng Đá 5v5 Sài Gòn Central',
                'game_format' => '5v5',
                'cost_per_person' => 75000,
            ],
            [
                'title' => 'Kèo bóng đá 7v7 sân C1 Quần Ngựa chiều Chủ Nhật - Thiếu 3 vị trí tiền vệ & hậu vệ',
                'starts_at' => $now->copy()->addDays(5)->setTime(16, 30),
                'ends_at' => $now->copy()->addDays(5)->setTime(18, 0),
                'city' => 'Hà Nội',
                'min_skill' => 2,
                'max_skill' => 5,
                'required_participants' => 14,
                'joined_count' => 11,
                'sport' => 'football',
                'venue_name' => 'Aura Arena Quần Ngựa',
                'court_name' => 'Sân Bóng Đá 7v7 C1 Quần Ngựa',
                'game_format' => '7v7',
                'cost_per_person' => 65000,
            ],

            // 5. Bóng rổ
            [
                'title' => 'Kèo bóng rổ 3x3 cuối tuần Landmark 72 - Friendly match vui vẻ',
                'starts_at' => $now->copy()->addDays(5)->setTime(18, 0),
                'ends_at' => $now->copy()->addDays(5)->setTime(20, 0),
                'city' => 'Hà Nội',
                'min_skill' => 1,
                'max_skill' => 4,
                'required_participants' => 6,
                'joined_count' => 4,
                'sport' => 'basketball',
                'venue_name' => 'Aura Multi-Sport Landmark 72',
                'court_name' => 'Sân Bóng Rổ Trong Nhà NBA Landmark',
                'game_format' => '3x3',
                'cost_per_person' => 65000,
            ],
            [
                'title' => 'Bóng rổ Streetball 3x3 Zone Tây Hồ chiều thứ 7 đón gió lộng',
                'starts_at' => $now->copy()->addDays(4)->setTime(16, 30),
                'ends_at' => $now->copy()->addDays(4)->setTime(18, 30),
                'city' => 'Hà Nội',
                'min_skill' => 2,
                'max_skill' => 4,
                'required_participants' => 6,
                'joined_count' => 3,
                'sport' => 'basketball',
                'venue_name' => 'Aura Sports Complex Tây Hồ',
                'court_name' => 'Sân Bóng Rổ 1 - Streetball Zone Tây Hồ',
                'game_format' => '3x3',
                'cost_per_person' => 45000,
            ],

            // 6. Bóng chuyền & Bóng bàn
            [
                'title' => 'Kèo Bóng chuyền phong trào 6v6 thảm Taraflex Landmark 72 sáng CN',
                'starts_at' => $now->copy()->addDays(5)->setTime(8, 0),
                'ends_at' => $now->copy()->addDays(5)->setTime(10, 0),
                'city' => 'Hà Nội',
                'min_skill' => 2,
                'max_skill' => 4,
                'required_participants' => 12,
                'joined_count' => 9,
                'sport' => 'volleyball',
                'venue_name' => 'Aura Multi-Sport Landmark 72',
                'court_name' => 'Sân Bóng Chuyền 1 - Thảm Taraflex Landmark',
                'game_format' => '6v6',
                'cost_per_person' => 50000,
            ],
            [
                'title' => 'Giao lưu Bóng bàn đơn/đôi Double Fish ITTF Cầu Giấy tối thứ 3',
                'starts_at' => $now->copy()->addDays(1)->setTime(19, 0),
                'ends_at' => $now->copy()->addDays(1)->setTime(21, 0),
                'city' => 'Hà Nội',
                'min_skill' => 2,
                'max_skill' => 4,
                'required_participants' => 4,
                'joined_count' => 2,
                'sport' => 'table_tennis',
                'venue_name' => 'Aura Court Cầu Giấy',
                'court_name' => 'Sân Bóng Bàn 1 - Double Fish ITTF',
                'game_format' => 'Đánh đôi / Đơn',
                'cost_per_person' => 30000,
            ],
        ];

        $targetOrgs = Organization::where('status', 'active')->get();
        if ($targetOrgs->isEmpty()) {
            $targetOrgs = Organization::all();
        }

        foreach ($targetOrgs as $currentOrg) {
            foreach ($matchPostsData as $mIndex => $mp) {
                $creator = $customers[$mIndex % $customers->count()];

                $post = MatchPost::updateOrCreate(
                    ['organization_id' => $currentOrg->id, 'title' => $mp['title']],
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
                        'status' => ($mp['joined_count'] >= $mp['required_participants']) ? 'full' : 'open',
                        'availability' => [
                            'sport' => $mp['sport'],
                            'format' => $mp['game_format'],
                            'split_cost' => true,
                            'cost_per_person' => $mp['cost_per_person'],
                            'venue_name' => $mp['venue_name'],
                            'court_name' => $mp['court_name'],
                        ],
                        'metadata' => [
                            'sport' => $mp['sport'],
                            'host' => $creator->name,
                            'venue_name' => $mp['venue_name'],
                            'court_name' => $mp['court_name'],
                            'game_format' => $mp['game_format'],
                            'cost_per_person' => $mp['cost_per_person'],
                        ],
                    ]
                );

                // Add Creator participation
                MatchParticipation::updateOrCreate(
                    ['organization_id' => $currentOrg->id, 'match_post_id' => $post->id, 'user_id' => $creator->id],
                    ['status' => 'accepted', 'joined_at' => $now->copy()->subHours(5)]
                );

                // Add other participants
                for ($p = 1; $p < $mp['joined_count']; $p++) {
                    $joinedUser = $customers[($mIndex + $p) % $customers->count()];
                    MatchParticipation::updateOrCreate(
                        ['organization_id' => $currentOrg->id, 'match_post_id' => $post->id, 'user_id' => $joinedUser->id],
                        ['status' => 'accepted', 'joined_at' => $now->copy()->subHours(2)]
                    );
                }
            }

            // Seed Player Profiles for customers
            foreach ($customers as $cIdx => $cust) {
                PlayerProfile::updateOrCreate(
                    ['organization_id' => $currentOrg->id, 'user_id' => $cust->id],
                    [
                        'skill_level' => ($cIdx % 4) + 2,
                        'city' => ($cIdx % 3 === 0) ? 'TP. Hồ Chí Minh' : 'Hà Nội',
                        'approximate_latitude' => 21.0285,
                        'approximate_longitude' => 105.8542,
                        'reputation_score' => 95 + ($cIdx % 5),
                        'availability' => ['weekday_evenings', 'weekend_mornings'],
                        'visibility' => 'organization',
                    ]
                );
            }
        }
    }
}
