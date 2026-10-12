<?php

namespace Database\Seeders;

use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Models\TournamentGrant;
use App\Models\TournamentParticipant;
use App\Models\TournamentStanding;
use App\Models\User;
use App\Models\Venue;
use App\Services\Tournament\TournamentService;
use Carbon\Carbon;
use Database\Seeders\Concerns\KeepsWakingHours;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

/**
 * One tournament per sport across both tenants and every lifecycle stage. Brackets come from
 * TournamentService::generateSingleElimination and results advance winners the way submitScore does,
 * so fixtures, champions and standings (TournamentService::recomputeStandings) agree.
 * Seeded registrations carry no wallet debit: backdated debits would break wallet running balances.
 */
class TournamentSeeder extends Seeder
{
    use KeepsWakingHours;

    /**
     * players: 0 is user@example.com, n is khach{n}; list order is the seed.
     * upsets: "round-position" fixtures the worse seed wins. played: fixtures completed (in_progress only).
     * day/round_days: start offset in days from today and each round's day offset from the start.
     */
    private const TOURNAMENTS = [
        [
            'key' => 'cau-long-don-nam-cau-giay',
            'venue' => 'aura-court',
            'sport' => 'badminton',
            'name' => 'Giải Cầu Lông Đơn Nam Aura Cầu Giấy Mở Rộng',
            'category' => 'Đơn nam - trình độ khá',
            'stage' => 'finalized',
            'day' => -24,
            'round_days' => [0, 0, 1],
            'max' => 8,
            'players' => [1, 3, 5, 7, 9, 11, 13, 15],
            'upsets' => ['1-2', '3-1'],
            'prize_pool' => '12.000.000 ₫',
            'entry_fee' => '250.000 ₫ / VĐV',
            'image_url' => '/courts/badminton-1.jpg',
            'description' => 'Giải phong trào thường niên cho các tay vợt đơn nam trình độ khá tại Hà Nội, thi đấu trên thảm BWF tiêu chuẩn của Aura Court Cầu Giấy.',
            'rules' => 'Loại trực tiếp, mỗi trận 3 ván, ván 21 điểm tính điểm liên tục. BTC cung cấp cầu lông vũ. VĐV check-in trước giờ thi đấu 30 phút.',
        ],
        [
            'key' => 'quan-vot-hoang-gia-cup',
            'venue' => 'hoang-gia-phu-my-hung',
            'sport' => 'tennis',
            'name' => 'Giải Quần Vợt Đơn Nam Hoàng Gia Phú Mỹ Hưng Cup',
            'category' => 'Đơn nam - NTRP 3.5+',
            'stage' => 'finalized',
            'day' => -17,
            'round_days' => [0, 1, 1],
            'max' => 8,
            'players' => [13, 15, 17, 19, 21, 23, 3, 9],
            'upsets' => ['1-4', '2-1'],
            'prize_pool' => '20.000.000 ₫',
            'entry_fee' => '400.000 ₫ / VĐV',
            'image_url' => '/courts/tennis-2.jpg',
            'description' => 'Giải quần vợt do Hoàng Gia Sports tổ chức cho cộng đồng tennis khu Nam Sài Gòn, thi đấu trên sân cứng Hồ Bán Nguyệt.',
            'rules' => 'Loại trực tiếp, mỗi trận 3 set, tie-break 7 điểm khi hòa 6-6. BTC cung cấp bóng mới cho mỗi trận.',
        ],
        [
            'key' => 'bong-ban-quan-ngua-open',
            'venue' => 'aura-arena-quan-ngua',
            'sport' => 'table_tennis',
            'name' => 'Giải Bóng Bàn Phong Trào Quần Ngựa Open',
            'category' => 'Đơn nam nữ phong trào',
            'stage' => 'finalized',
            'day' => -9,
            'round_days' => [0, 0, 0],
            'max' => 8,
            'players' => [6, 9, 0, 12, 15, 18, 21, 24],
            'upsets' => ['1-3'],
            'prize_pool' => '6.000.000 ₫',
            'entry_fee' => '150.000 ₫ / VĐV',
            'image_url' => '/courts/table-tennis-2.jpg',
            'description' => 'Sân chơi bóng bàn cuối tuần cho hội viên Aura tại phòng thi đấu máy lạnh trong Cung Thể Thao Quần Ngựa, bàn và bóng chuẩn ITTF.',
            'rules' => 'Loại trực tiếp, mỗi trận 5 ván (thắng 3), ván 11 điểm, cách biệt 2 điểm. Đổi giao bóng sau mỗi 2 điểm.',
        ],
        [
            'key' => 'pickleball-don-ho-tay',
            'venue' => 'aura-complex-tay-ho',
            'sport' => 'pickleball',
            'name' => 'Giải Pickleball Đơn Hồ Tây Mở Rộng',
            'category' => 'Đơn phong trào - DUPR 3.0+',
            'stage' => 'in_progress',
            'day' => -2,
            'round_days' => [0, 7, 7],
            'end_day' => 7,
            'played' => 4,
            'max' => 8,
            'players' => [2, 0, 5, 8, 11, 14, 17, 20],
            'upsets' => ['1-2'],
            'prize_pool' => '10.000.000 ₫',
            'entry_fee' => '200.000 ₫ / VĐV',
            'image_url' => '/courts/pickleball-outdoor.jpg',
            'description' => 'Giải pickleball ngoài trời ven Hồ Tây. Vòng tứ kết đã khép lại, bán kết và chung kết đang chờ bốn tay vợt xuất sắc nhất.',
            'rules' => 'Loại trực tiếp, mỗi trận 3 game, game 11 điểm, cách biệt 2 điểm. Bóng thi đấu ngoài trời do BTC cung cấp.',
        ],
        [
            'key' => 'bong-da-5-sai-gon-central-cup',
            'venue' => 'aura-central-saigon',
            'sport' => 'football_5v5',
            'name' => 'Giải Bóng Đá Mini 5 Người Sài Gòn Central Cup',
            'category' => 'Bóng đá 5 người - 8 đội',
            'stage' => 'in_progress',
            'day' => -3,
            'round_days' => [0, 2, 9],
            'end_day' => 9,
            'played' => 5,
            'max' => 8,
            'players' => [1, 3, 7, 9, 13, 19, 21, 23],
            'teams' => ['Bình Thạnh FC', 'Sài Gòn Xanh', 'FC Văn Thánh', 'Thanh Đa United', 'FC Điện Biên Phủ', 'Hàng Xanh Stars', 'FC Bến Nghé', 'Gia Định FC'],
            'upsets' => ['1-3'],
            'prize_pool' => '25.000.000 ₫',
            'entry_fee' => '1.500.000 ₫ / đội',
            'image_url' => '/courts/football-saigon.jpg',
            'description' => '8 đội bóng văn phòng và phong trào khu Bình Thạnh tranh cúp trên sân cỏ nhân tạo Aura Sài Gòn. Mỗi đội đăng ký qua đội trưởng.',
            'rules' => 'Loại trực tiếp, 2 hiệp x 20 phút, hòa sau 2 hiệp đá luân lưu 5 quả. Mỗi đội tối đa 10 cầu thủ, thay người không giới hạn.',
        ],
        [
            'key' => 'bong-ro-3x3-landmark',
            'venue' => 'aura-landmark-72',
            'sport' => 'basketball',
            'name' => 'Giải Bóng Rổ 3x3 Landmark Streetball',
            'category' => 'Bóng rổ 3x3 - 4 đội',
            'stage' => 'ready',
            'day' => 3,
            'round_days' => [0, 0],
            'max' => 4,
            'players' => [5, 11, 15, 17],
            'teams' => ['Mễ Trì Ballers', 'Keangnam Hoops', 'Phạm Hùng 3x3', 'Nam Từ Liêm Dunkers'],
            'upsets' => [],
            'prize_pool' => '8.000.000 ₫',
            'entry_fee' => '600.000 ₫ / đội',
            'image_url' => '/courts/basketball-nba.jpg',
            'description' => 'Giải bóng rổ 3x3 trong nhà tại Keangnam Landmark 72. Bốn đội đã chốt danh sách, sơ đồ bán kết và chung kết đã được bốc thăm.',
            'rules' => 'Luật FIBA 3x3: trận 10 phút hoặc đội đầu tiên đạt 21 điểm. Bán kết và chung kết thi đấu trong cùng một ngày.',
        ],
        [
            'key' => 'cau-long-phong-trao-quan-ngua',
            'venue' => 'aura-arena-quan-ngua',
            'sport' => 'badminton',
            'name' => 'Giải Cầu Lông Phong Trào Quần Ngựa Mở Rộng',
            'category' => 'Đơn nam nữ phong trào',
            'stage' => 'open',
            'day' => 10,
            'max' => 16,
            'players' => [0, 2, 4, 7, 10, 16, 19, 22, 23],
            'prize_pool' => '15.000.000 ₫',
            'entry_fee' => '200.000 ₫ / VĐV',
            'image_url' => '/courts/badminton-night.jpg',
            'description' => 'Giải mở rộng cho người chơi phong trào tại Hà Nội, thi đấu trên các sân có khán đài của Aura Arena Quần Ngựa. Đang nhận đăng ký.',
            'rules' => 'Loại trực tiếp, trận 3 ván 21 điểm. Xếp hạt giống theo trình độ đăng ký. Đóng đăng ký trước ngày khai mạc 2 ngày.',
        ],
        [
            'key' => 'bong-chuyen-doanh-nghiep-landmark',
            'venue' => 'aura-landmark-72',
            'sport' => 'volleyball',
            'name' => 'Giải Bóng Chuyền Giao Hữu Doanh Nghiệp Landmark',
            'category' => 'Bóng chuyền 6 người - vòng tròn',
            'format' => 'round_robin',
            'stage' => 'open',
            'day' => 17,
            'max' => 6,
            'players' => [2, 10, 16, 22],
            'teams' => ['Bóng Chuyền Văn Phòng Mỹ Đình', 'CLB Phạm Hùng', 'Nam Từ Liêm Volley', 'Mễ Trì Spikers'],
            'prize_pool' => '10.000.000 ₫',
            'entry_fee' => '1.000.000 ₫ / đội',
            'image_url' => '/courts/volleyball-indoor.jpg',
            'description' => 'Các đội bóng chuyền văn phòng khu Mỹ Đình - Phạm Hùng thi đấu vòng tròn một lượt trên thảm Taraflex của Aura Landmark 72.',
            'rules' => 'Vòng tròn một lượt, mỗi trận 3 set: hai set đầu 25 điểm, set quyết định 15 điểm. Thắng được 3 điểm.',
        ],
        [
            'key' => 'bong-da-7-ba-dinh',
            'venue' => 'aura-arena-quan-ngua',
            'sport' => 'football_7v7',
            'name' => 'Giải Bóng Đá 7 Người Ba Đình Mở Rộng',
            'category' => 'Bóng đá 7 người - 8 đội',
            'stage' => 'open',
            'day' => 24,
            'max' => 8,
            'players' => [13, 15, 17, 21, 23],
            'teams' => ['Liễu Giai FC', 'Văn Cao United', 'FC Đội Cấn', 'Ngọc Hà Stars', 'Kim Mã FC'],
            'prize_pool' => '30.000.000 ₫',
            'entry_fee' => '2.000.000 ₫ / đội',
            'image_url' => '/courts/football-wide.jpg',
            'description' => 'Giải bóng đá sân 7 cho các đội phong trào quận Ba Đình và lân cận, thi đấu tại sân cỏ nhân tạo C1 Quần Ngựa.',
            'rules' => 'Loại trực tiếp, 2 hiệp x 25 phút, hòa đá luân lưu 5 quả. Mỗi đội đăng ký tối đa 14 cầu thủ.',
        ],
        [
            'key' => 'pickleball-cu-dan-phu-my-hung',
            'venue' => 'hoang-gia-phu-my-hung',
            'sport' => 'pickleball',
            'name' => 'Giải Pickleball Cư Dân Phú Mỹ Hưng',
            'category' => 'Đơn phong trào',
            'stage' => 'open',
            'day' => 31,
            'max' => 16,
            'players' => [4, 6, 12, 18, 20, 24],
            'prize_pool' => '8.000.000 ₫',
            'entry_fee' => '150.000 ₫ / VĐV',
            'image_url' => '/courts/pickleball-2.jpg',
            'description' => 'Giải pickleball dành cho cư dân khu Nam Sài Gòn do Hoàng Gia Sports tổ chức trên hai sân có mái che.',
            'rules' => 'Loại trực tiếp, trận 3 game 11 điểm, cách biệt 2 điểm. VĐV tự chuẩn bị vợt, BTC cung cấp bóng.',
        ],
    ];

    /** Grants by tournament key: [email, role]. */
    private const GRANTS = [
        'cau-long-don-nam-cau-giay' => [['referee@demo.auracourt.vn', 'official']],
        'pickleball-don-ho-tay' => [['referee@demo.auracourt.vn', 'official'], ['manager@demo.auracourt.vn', 'organizer']],
        'bong-ro-3x3-landmark' => [['referee@demo.auracourt.vn', 'official']],
        'cau-long-phong-trao-quan-ngua' => [['referee@demo.auracourt.vn', 'official'], ['manager@demo.auracourt.vn', 'organizer']],
        'bong-da-7-ba-dinh' => [['manager@demo.auracourt.vn', 'organizer']],
    ];

    /** Winner-side game scores per sport, cycled by fixture index. Team sports store goals or points only. */
    private const SCORES = [
        'badminton' => [[[21, 15], [21, 18]], [[21, 19], [17, 21], [21, 16]], [[23, 21], [21, 14]], [[18, 21], [21, 19], [22, 20]], [[21, 12], [21, 17]]],
        'pickleball' => [[[11, 7], [11, 9]], [[11, 8], [9, 11], [11, 6]], [[12, 10], [11, 5]], [[11, 4], [11, 8]]],
        'table_tennis' => [[[11, 8], [11, 6], [11, 9]], [[11, 9], [8, 11], [11, 7], [11, 5]], [[9, 11], [11, 7], [12, 10], [8, 11], [11, 9]], [[11, 5], [11, 7], [13, 11]]],
        'tennis' => [[[6, 4], [6, 3]], [[7, 5], [4, 6], [6, 2]], [[6, 2], [7, 6]], [[3, 6], [6, 4], [6, 4]]],
        'volleyball' => [[[25, 21], [25, 19]], [[25, 23], [22, 25], [15, 12]], [[25, 18], [25, 22]]],
        'football_5v5' => [[3, 1], [2, 1], [4, 2], [1, 0], [5, 3]],
        'football_7v7' => [[2, 0], [3, 2], [1, 0], [4, 1]],
        'basketball' => [[21, 17], [21, 18], [19, 15], [21, 14]],
    ];

    private Carbon $now;

    public function run(TournamentService $service): void
    {
        $this->now = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'))->setSecond(0);

        $emails = ['user@example.com', ...array_map(fn (int $n) => sprintf('khach%02d@demo.auracourt.vn', $n), range(1, 24))];
        $users = User::whereIn('email', [...$emails, 'owner@demo.auracourt.vn', 'owner.hoanggia@demo.auracourt.vn', 'referee@demo.auracourt.vn', 'manager@demo.auracourt.vn'])
            ->get()->keyBy('email');
        $players = collect($emails)->map(fn (string $email) => $users->get($email));

        foreach (self::TOURNAMENTS as $spec) {
            $venue = Venue::where('slug', $spec['venue'])->first();
            $creator = $users->get($spec['venue'] === 'hoang-gia-phu-my-hung' ? 'owner.hoanggia@demo.auracourt.vn' : 'owner@demo.auracourt.vn');
            if (! $venue || ! $creator) {
                continue;
            }

            $tournament = $this->tournament($spec, $venue, $creator);
            $participants = $this->participants($tournament, $spec, $players);

            if ($spec['stage'] !== 'open' && $participants->count() >= 2) {
                $this->playBracket($service, $tournament, $spec, $participants);
            }

            foreach (self::GRANTS[$spec['key']] ?? [] as [$email, $role]) {
                if ($grantee = $users->get($email)) {
                    $at = $this->past($tournament->created_at->copy()->addDay()->setTime(9, 0));
                    TournamentGrant::forceCreate([
                        'organization_id' => $tournament->organization_id,
                        'tournament_id' => $tournament->id,
                        'user_id' => $grantee->id,
                        'role' => $role,
                        'granted_by' => $creator->id,
                        'created_at' => $at,
                        'updated_at' => $at,
                    ]);
                }
            }
        }
    }

    private function tournament(array $spec, Venue $venue, User $creator): Tournament
    {
        $start = $this->now->copy()->startOfDay()->addDays($spec['day'])->setTime(8, 0);
        $end = $start->copy()->addDays($spec['end_day'] ?? max($spec['round_days'] ?? [1]))->setTime(18, 0);
        $createdAt = $start->copy()->subDays(30)->min($this->now->copy()->subDays(10)->setTime(9, 0));
        $uuid = Uuid::uuid5(Uuid::NAMESPACE_URL, 'auracourt-demo/tournament/'.$spec['key'])->toString();

        // Cascades to participants, fixtures, standings, events and grants, so reseeding starts clean.
        Tournament::where('uuid', $uuid)->delete();

        $settings = [
            'sport' => $spec['sport'],
            'category' => $spec['category'],
            'venue' => $venue->getTranslation('name', 'vi'),
            'city' => $venue->city,
            'image_url' => $spec['image_url'],
            'prize_pool' => $spec['prize_pool'],
            'entry_fee' => $spec['entry_fee'],
            'max_participants' => $spec['max'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'registration_deadline' => $start->copy()->subDays($spec['stage'] === 'open' ? 2 : 4)->setTime(23, 59)->toIso8601String(),
            'description' => $spec['description'],
            'rules' => $spec['rules'],
        ];
        if (isset($spec['teams'])) {
            // No team column on participants: each team registers through its captain, keyed here by seed.
            $settings['team_names'] = array_combine(range(1, count($spec['teams'])), $spec['teams']);
        }

        return Tournament::forceCreate([
            'uuid' => $uuid,
            'organization_id' => $venue->organization_id,
            'created_by' => $creator->id,
            'name' => $spec['name'].' '.$start->year,
            'format' => $spec['format'] ?? 'single_elimination',
            'status' => $spec['stage'],
            'version' => 1,
            'starts_at' => $start,
            'settings' => $settings,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /**
     * @param  Collection<int, User|null>  $players
     * @return Collection<int, TournamentParticipant>
     */
    private function participants(Tournament $tournament, array $spec, Collection $players): Collection
    {
        $started = in_array($spec['stage'], ['in_progress', 'finalized'], true);
        $entrants = collect($spec['players'])->map(fn (int $number) => $players->get($number))->filter()->values();
        // Registrations spread evenly between the announcement and two days before kick-off (or now).
        $window = $this->past($tournament->starts_at->copy()->subDays(2))->getTimestamp() - $tournament->created_at->getTimestamp();

        return $entrants
            ->map(function (User $user, int $index) use ($tournament, $started, $entrants, $window) {
                $registeredAt = $tournament->created_at->copy()->addSeconds(intdiv($window * ($index + 1), $entrants->count() + 1));
                $registeredAt = $this->awake($registeredAt, $tournament->created_at, $tournament->created_at->copy()->addSeconds($window));
                $participant = TournamentParticipant::forceCreate([
                    'organization_id' => $tournament->organization_id,
                    'tournament_id' => $tournament->id,
                    'user_id' => $user->id,
                    'seed' => $index + 1,
                    'status' => $started ? 'checked_in' : 'registered',
                    'checked_in_at' => $started ? $tournament->starts_at->copy()->subMinutes(45 - $index * 3) : null,
                    'created_at' => $registeredAt,
                    'updated_at' => $registeredAt,
                ]);
                // TournamentService::register creates an empty standing per registration.
                TournamentStanding::forceCreate([
                    'organization_id' => $tournament->organization_id,
                    'tournament_id' => $tournament->id,
                    'participant_id' => $participant->id,
                    'created_at' => $registeredAt,
                    'updated_at' => $registeredAt,
                ]);

                return $participant;
            });
    }

    /**
     * @param  Collection<int, TournamentParticipant>  $participants
     */
    private function playBracket(TournamentService $service, Tournament $tournament, array $spec, Collection $participants): void
    {
        $service->generateSingleElimination($tournament);
        $generatedAt = $this->past($tournament->starts_at->copy()->subDay()->setTime(20, 0));
        TournamentFixture::where('tournament_id', $tournament->id)->update(['created_at' => $generatedAt, 'updated_at' => $generatedAt]);

        $seeds = $participants->pluck('seed', 'id');
        $toPlay = $spec['stage'] === 'finalized' ? PHP_INT_MAX : ($spec['played'] ?? 0);
        $played = 0;
        $lastPlayedAt = null;
        $fixtures = TournamentFixture::where('tournament_id', $tournament->id)->orderBy('round')->orderBy('position')->get();

        foreach ($fixtures as $index => $fixture) {
            $fixture->refresh();
            [$one, $two] = [$fixture->participant_one_id, $fixture->participant_two_id];
            if ($fixture->status === 'bye' || ! $one || ! $two) {
                continue;
            }
            if ($played >= $toPlay) {
                // Query update keeps the last bracket change as updated_at (save() would stamp an unchanged one with now()).
                TournamentFixture::whereKey($fixture->id)->update(['status' => 'scheduled', 'updated_at' => $fixture->updated_at]);

                continue;
            }

            $oneWins = ($seeds[$one] < $seeds[$two]) !== in_array("{$fixture->round}-{$fixture->position}", $spec['upsets'], true);
            $winnerId = $oneWins ? $one : $two;
            $playedAt = $tournament->starts_at->copy()->addDays($spec['round_days'][$fixture->round - 1])
                ->addHours(($fixture->round - 1) * 3 + $fixture->position - 1);
            $fixture->forceFill([
                'score' => $this->score($spec['sport'], $oneWins, $index),
                'winner_id' => $winnerId,
                'status' => 'completed',
                'version' => 2,
                'updated_at' => $playedAt,
            ])->save();

            // Same advancement as TournamentService::submitScore.
            TournamentFixture::where('tournament_id', $tournament->id)
                ->where('round', $fixture->round + 1)
                ->where('position', (int) ceil($fixture->position / 2))
                ->update([$fixture->position % 2 === 1 ? 'participant_one_id' : 'participant_two_id' => $winnerId, 'updated_at' => $playedAt]);

            $played++;
            $lastPlayedAt = $playedAt;
        }

        if ($played === 0) {
            return;
        }

        $service->recomputeStandings($tournament);
        TournamentStanding::where('tournament_id', $tournament->id)->update(['updated_at' => $lastPlayedAt]);

        $finalized = $spec['stage'] === 'finalized';
        $settings = $tournament->settings;
        if ($finalized) {
            $settings['finalized_at'] = $lastPlayedAt->copy()->addHour()->toIso8601String();
        }
        // submitScore bumps the version per result and finalize once more.
        $tournament->forceFill([
            'version' => 1 + $played + ($finalized ? 1 : 0),
            'settings' => $settings,
            'updated_at' => $finalized ? $lastPlayedAt->copy()->addHour() : $lastPlayedAt,
        ])->save();
    }

    /**
     * @return array{p1: int, p2: int, sets?: list<array{p1: int, p2: int}>}
     */
    private function score(string $sport, bool $oneWins, int $index): array
    {
        $patterns = self::SCORES[$sport] ?? self::SCORES['badminton'];
        $pattern = $patterns[$index % count($patterns)];

        if (is_int($pattern[0])) {
            [$winner, $loser] = $pattern;

            return $oneWins ? ['p1' => $winner, 'p2' => $loser] : ['p1' => $loser, 'p2' => $winner];
        }

        $sets = array_map(fn (array $game) => $oneWins ? ['p1' => $game[0], 'p2' => $game[1]] : ['p1' => $game[1], 'p2' => $game[0]], $pattern);

        return [
            'p1' => count(array_filter($sets, fn (array $set) => $set['p1'] > $set['p2'])),
            'p2' => count(array_filter($sets, fn (array $set) => $set['p2'] > $set['p1'])),
            'sets' => $sets,
        ];
    }

    /** Clamps a timestamp so seeded history never lands in the future, nor in the small hours. */
    private function past(Carbon $at): Carbon
    {
        $latest = $this->now->copy()->subHours(3);

        return $this->awake($at->min($latest), before: $latest);
    }
}
