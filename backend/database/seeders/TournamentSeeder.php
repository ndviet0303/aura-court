<?php

namespace Database\Seeders;

use App\Domain\Tournament\Actions\AddRosterEntry;
use App\Domain\Tournament\Actions\CorrectFixtureResult;
use App\Domain\Tournament\Actions\CreateInternalTournament;
use App\Domain\Tournament\Actions\CreatePublicTournament;
use App\Domain\Tournament\Actions\GrantTournamentRole;
use App\Domain\Tournament\Actions\PublishTournament;
use App\Domain\Tournament\DTOs\TournamentSetupData;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Tournament;
use App\Models\TournamentCourtAllocation;
use App\Models\TournamentEntry;
use App\Models\TournamentFixture;
use App\Models\TournamentGrant;
use App\Models\TournamentRental;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Database\Seeders\Concerns\FindsFreeCourtTime;
use Database\Seeders\Concerns\PaysVenueOwner;
use Database\Seeders\Concerns\PlaysTournaments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Module 07 part C demo tournaments, each built through the real actions with the clock at every step: a public
 * badminton group stage → knockout in progress (with a corrected result), a public football tournament open for
 * entries in every application state, an internal doubles tournament inside one renter's confirmed bookings, and a
 * finished football cup. A tournament whose uuid exists is left as it is, and each one is written in one transaction.
 */
class TournamentSeeder extends Seeder
{
    use FindsFreeCourtTime;
    use PaysVenueOwner;
    use PlaysTournaments;

    private const OWNER = 'owner@demo.auracourt.vn';

    private const PARTNER_OWNER = 'owner.hoanggia@demo.auracourt.vn';

    private const STAFF = 'staff@demo.auracourt.vn';

    private Carbon $now;

    /** @var Collection<string, User> */
    private Collection $users;

    public function run(): void
    {
        $this->now = now()->setSecond(0);
        $customers = ['user@example.com', ...array_map(fn (int $n): string => sprintf('khach%02d@demo.auracourt.vn', $n), range(1, 18))];
        $this->users = User::whereIn('email', [...$customers, self::OWNER, self::PARTNER_OWNER, self::STAFF])->get()->keyBy('email');
        if ($this->users->count() < count($customers) + 3) {
            return;
        }
        foreach (['badmintonCup', 'footballLeague', 'internalDoubles', 'finishedCup'] as $scenario) {
            DB::transaction(fn () => $this->{$scenario}());
        }
    }

    /** Group stage done, one result corrected afterwards, the knockout placed and one semifinal played. */
    private function badmintonCup(): void
    {
        [$owner, $staff] = [$this->users[self::OWNER], $this->users[self::STAFF]];
        $tournament = $this->publish('cau-long-don-cau-giay', 'aura-court', $owner, [
            'name' => 'Giải Cầu Lông Đơn Mở Rộng Aura Cầu Giấy '.$this->day(-2, '08:00')->year,
            'sport' => 'badminton', 'format' => Tournament::FORMAT_GROUP, 'group_count' => 2, 'advance_per_group' => 2,
            'max_entries' => 8, 'fee' => 300_000,
            'eligibility' => 'Vận động viên phong trào từ 16 tuổi, không thuộc đội tuyển cấp tỉnh trở lên.',
            'prizes' => 'Vô địch 3.000.000đ, Á quân 1.500.000đ, hai đồng hạng ba 500.000đ, kèm cúp và huy chương.',
            'registration_deadline' => $this->day(-12, '23:59'), 'payment_deadline' => $this->day(-6, '23:59'),
            'starts_at' => $this->day(-2, '08:00'), 'ends_at' => $this->day(1, '12:00'),
        ], [[-2, '08:00', '12:00', 2], [-1, '08:00', '09:00', 1], [1, '09:00', '09:30', 1]], $this->day(-30, '09:00'));
        if (! $tournament) {
            return;
        }
        $this->atClock($this->day(-29, '10:00'), fn () => app(GrantTournamentRole::class)->execute($tournament, $owner, TournamentGrant::ROLE_OFFICIAL, self::STAFF));
        foreach (range(0, 7) as $i) {
            $user = $this->customer($i);
            $this->enter($tournament, $owner, $user, $user->name, $i, $this->day(-28 + 2 * $i, '20:00'), TournamentEntry::STATUS_PAID);
        }
        $this->lockAndDraw($tournament, $owner, $staff, $this->day(-5, '09:00'), $this->day(-4, '20:00'));

        $groups = $tournament->fixtures()->orderBy('round')->orderBy('group')->orderBy('position')->get();
        $before = fn (TournamentCourtAllocation $allocation): bool => $allocation->starts_at->lt($this->day(-1, '00:00'));
        $this->schedule($staff, $groups, $tournament->allocations()->get()->filter($before), 30, $this->day(-4, '20:30'));
        $groups->each(fn (TournamentFixture $fixture, int $i) => $this->play($staff, $fixture, $i));

        $fixed = $tournament->fixtures()->where('stage', TournamentFixture::STAGE_GROUP)->orderBy('id')->get()
            ->first(fn (TournamentFixture $fixture): bool => count($fixture->score['games']) === 2);
        $this->atClock($this->day(-2, '14:00'), fn () => app(CorrectFixtureResult::class)->execute(
            $fixed, $owner, 'Trọng tài ghi nhầm biên bản: trận đấu kéo dài 3 ván, đã đối chiếu với hai vận động viên.',
            ['games' => $this->side((int) $fixed->winner_entry_id === (int) $fixed->entry_one_id, [[21, 17], [18, 21], [21, 19]])],
        ));

        $knockout = $tournament->fixtures()->where('stage', TournamentFixture::STAGE_KNOCKOUT)->orderBy('round')->orderBy('position')->get();
        $this->schedule($staff, $knockout, $tournament->allocations()->get()->reject($before), 30, $this->day(-2, '15:00'));
        $this->play($staff, $knockout->first(), 2);
    }

    /** Registration still open: applications submitted, approved (user@example.com, not paid yet), paid, rejected and withdrawn. */
    private function footballLeague(): void
    {
        $owner = $this->users[self::OWNER];
        $tournament = $this->publish('bong-da-mini-quan-ngua', 'aura-arena-quan-ngua', $owner, [
            'name' => 'Giải Bóng Đá Mini Phong Trào Quần Ngựa '.$this->day(14, '07:00')->year,
            'sport' => 'football', 'entry_type' => 'team', 'team_main' => 5, 'team_reserve' => 3,
            'format' => Tournament::FORMAT_GROUP, 'group_count' => 2, 'advance_per_group' => 2,
            'max_entries' => 8, 'fee' => 1_200_000,
            'eligibility' => 'Đội phong trào, cầu thủ từ 18 đến 45 tuổi, không có cầu thủ đang thi đấu chuyên nghiệp.',
            'prizes' => 'Vô địch 10.000.000đ, Á quân 5.000.000đ, Vua phá lưới 1.000.000đ.',
            'registration_deadline' => $this->day(7, '23:59'), 'payment_deadline' => $this->day(10, '23:59'),
            'starts_at' => $this->day(14, '07:00'), 'ends_at' => $this->day(15, '11:00'),
        ], [[14, '07:00', '11:00', 2], [15, '07:00', '11:00', 1]], $this->day(-10, '09:00'));
        if (! $tournament) {
            return;
        }
        $teams = [
            [0, 'FC Văn Phòng Duy Tân', TournamentEntry::STATUS_APPROVED],
            [8, 'FC Cầu Giấy', TournamentEntry::STATUS_PAID],
            [9, 'Sao Đỏ Ba Đình', TournamentEntry::STATUS_PAID],
            [10, 'Kỳ Lân FC', TournamentEntry::STATUS_PAID],
            [11, 'Thanh Xuân United', TournamentEntry::STATUS_SUBMITTED],
            [12, 'FC Tây Hồ', TournamentEntry::STATUS_SUBMITTED],
            [13, 'Hà Đông Stars', TournamentEntry::STATUS_REJECTED],
            [14, 'Long Biên FC', TournamentEntry::STATUS_WITHDRAWN],
        ];
        foreach ($teams as $i => [$customer, $name, $status]) {
            $this->enter($tournament, $owner, $this->customer($customer), $name, 10 + $i, $this->day(-9 + $i, '20:00'), $status,
                'Đội có cầu thủ đang đăng ký thi đấu chuyên nghiệp, chưa đúng điều kiện tham gia.');
        }
    }

    /** One renter's confirmed bookings host a doubles knockout; the representative keys the roster and the results. */
    private function internalDoubles(): void
    {
        $uuid = $this->uuid('cau-long-doi-noi-bo');
        $rentals = Tournament::where('uuid', $uuid)->exists() ? null : $this->rentals();
        if (! $rentals) {
            return;
        }
        $venueId = (int) $rentals->first()->slots->first()->court->venue_id;
        $owner = collect([$this->users[self::OWNER], $this->users[self::PARTNER_OWNER]])->first(fn (User $user): bool => $user->canManageVenue($venueId));
        $representative = $rentals->first()->user;
        $slots = $rentals->flatMap->slots->sortBy('starts_at')->values();
        $setupAt = $slots->first()->starts_at->copy()->subDays(3)->setTimezone(config('app.timezone'))->setTime(20, 0)->min($this->now->copy()->subHours(3));
        $tournament = $this->atClock($setupAt, fn () => app(CreateInternalTournament::class)->execute($owner, $this->setup([
            'uuid' => $uuid, 'name' => 'Giải Cầu Lông Đôi Nội Bộ Nhóm '.$representative->name, 'sport' => 'badminton', 'entry_type' => 'double',
            'rules' => 'Đánh 2 ván thắng, mỗi ván 21 điểm; các cặp tự bắt cặp, ban tổ chức là trưởng nhóm.',
        ]), $rentals->pluck('code')->all(), null));
        foreach (range(0, 3) as $i) {
            $name = $this->person((30 + $i) * 10).' / '.$this->person((30 + $i) * 10 + 1);
            $this->atClock($setupAt->copy()->addMinutes(15), fn () => app(AddRosterEntry::class)->execute($tournament, $representative, $this->entryData($tournament, $name, 30 + $i)));
        }
        $this->lockAndDraw($tournament, $representative, $representative, $setupAt->copy()->addHour(), $setupAt->copy()->addMinutes(70));
        $fixtures = $tournament->fixtures()->orderBy('round')->orderBy('position')->get();
        $this->schedule($representative, $fixtures, $slots, 30, $setupAt->copy()->addMinutes(80));
        $tournament->fixtures()->where('round', 1)->where('status', TournamentFixture::STATUS_PENDING)->orderBy('position')->get()
            ->filter(fn (TournamentFixture $fixture): bool => $fixture->ends_at?->lt($this->now) === true && $fixture->ends_at->gt($setupAt))
            ->each(fn (TournamentFixture $fixture, int $i) => $this->play($representative, $fixture, $i + 1));
    }

    /** A four-team football knockout at the partner venue, played out with a shoot-out semifinal. */
    private function finishedCup(): void
    {
        $owner = $this->users[self::PARTNER_OWNER];
        $tournament = $this->publish('cup-bong-da-phu-my-hung', 'hoang-gia-phu-my-hung', $owner, [
            'name' => 'Cúp Bóng Đá Mini Phú Mỹ Hưng '.$this->day(-20, '08:00')->year,
            'sport' => 'football', 'entry_type' => 'team', 'team_main' => 5, 'team_reserve' => 2,
            'max_entries' => 4, 'fee' => 1_000_000,
            'eligibility' => 'Đội cư dân và nhân viên văn phòng khu Phú Mỹ Hưng, cầu thủ từ 18 tuổi.',
            'prizes' => 'Vô địch 6.000.000đ và cúp, Á quân 3.000.000đ.',
            'registration_deadline' => $this->day(-28, '23:59'), 'payment_deadline' => $this->day(-24, '23:59'),
            'starts_at' => $this->day(-20, '08:00'), 'ends_at' => $this->day(-20, '11:00'),
        ], [[-20, '08:00', '11:00', 1]], $this->day(-45, '09:00'));
        if (! $tournament) {
            return;
        }
        foreach (['Nam Sài Gòn FC', 'Phú Mỹ Hưng United', 'Tân Phong FC', 'Sunrise City FC'] as $i => $name) {
            $this->enter($tournament, $owner, $this->customer(15 + $i), $name, 20 + $i, $this->day(-44 + 2 * $i, '20:00'), TournamentEntry::STATUS_PAID);
        }
        $this->lockAndDraw($tournament, $owner, $owner, $this->day(-23, '09:00'), $this->day(-22, '20:00'));
        $fixtures = $tournament->fixtures()->orderBy('round')->orderBy('position')->get();
        $this->schedule($owner, $fixtures, $tournament->allocations()->get(), 60, $this->day(-22, '20:30'));
        $fixtures->each(fn (TournamentFixture $fixture, int $i) => $this->play($owner, $fixture, $i));
    }

    /**
     * A public tournament created at $at with its court time reserved and then published; null when seeded before.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{0: int, 1: string, 2: string, 3: int}>  $windows  [day offset, from, to, courts]
     */
    private function publish(string $key, string $venueSlug, User $owner, array $attributes, array $windows, Carbon $at): ?Tournament
    {
        $uuid = $this->uuid($key);
        $venue = Venue::where('slug', $venueSlug)->first();
        if (! $venue || Tournament::where('uuid', $uuid)->exists()) {
            return null;
        }

        return $this->atClock($at, function () use ($uuid, $venue, $owner, $attributes, $windows): Tournament {
            $tournament = app(CreatePublicTournament::class)->execute($owner, $venue->id, $this->setup(['uuid' => $uuid] + $attributes));
            foreach ($windows as [$day, $from, $to, $courts]) {
                $this->allocate($tournament, $owner, $this->day($day, $from), $this->day($day, $to), $courts);
            }

            return app(PublishTournament::class)->execute($tournament, $owner);
        });
    }

    /** @param  array<string, mixed>  $attributes */
    private function setup(array $attributes): TournamentSetupData
    {
        $badminton = $attributes['sport'] === 'badminton';

        return new TournamentSetupData($attributes + [
            'entry_type' => 'single', 'team_main' => null, 'team_reserve' => null, 'format' => Tournament::FORMAT_KNOCKOUT,
            'group_count' => null, 'advance_per_group' => null, 'games_to_win' => $badminton ? 2 : null,
            'points' => ['win' => 3, 'draw' => 1, 'loss' => 0], 'tiebreakers' => ['head_to_head', 'score_diff', 'score_for'],
            'rules' => $badminton
                ? 'Thi đấu theo luật BWF: mỗi trận 3 ván 21 điểm, thắng 2 ván. Cầu lông vũ do ban tổ chức cấp.'
                : 'Thi đấu 2 hiệp, mỗi hiệp 20 phút. Vòng bảng thắng 3 điểm, hoà 1 điểm; loại trực tiếp hoà thì đá luân lưu 5 lượt.',
            'eligibility' => null, 'athlete_fields' => $badminton ? [] : ['Số áo'], 'prizes' => null,
            'forfeit_rule' => 'Đơn vị có mặt trễ quá 15 phút so với giờ thi đấu bị xử thua.',
        ]);
    }

    /**
     * One customer's confirmed badminton bookings at one venue not yet in a tournament, preferring a customer with
     * both played and upcoming ones: up to two played and the next upcoming one.
     *
     * @return Collection<int, Reservation>|null
     */
    private function rentals(): ?Collection
    {
        $past = fn (Reservation $reservation): bool => $reservation->slots->max('ends_at')->lt($this->now);
        $group = Reservation::withoutGlobalScope('tenant_isolation')
            ->where('status', 'confirmed')->whereNotNull('user_id')
            ->whereNotIn('id', TournamentRental::query()->select('reservation_id'))
            ->with(['slots.court', 'user'])->orderBy('id')->get()
            ->filter(fn (Reservation $reservation): bool => $reservation->slots->isNotEmpty()
                && $reservation->slots->every(fn (ReservationSlot $slot): bool => $slot->status === 'confirmed' && $slot->court?->sport_type === 'badminton'))
            ->groupBy(fn (Reservation $reservation): string => $reservation->user_id.':'.$reservation->slots->first()->court->venue_id)
            ->filter(fn (Collection $rows): bool => $rows->count() >= 2)
            ->sortByDesc(fn (Collection $rows): int => ($rows->contains($past) && ! $rows->every($past) ? 100 : 0) + $rows->count())
            ->first()?->sortBy(fn (Reservation $reservation) => $reservation->slots->min('starts_at'))->values();
        if (! $group) {
            return null;
        }
        $picked = $group->filter($past)->take(-2)->merge($group->reject($past)->take(1))->values();

        return $picked->count() >= 2 ? $picked : $group->take(2);
    }

    private function customer(int $number): User
    {
        return $this->users[$number === 0 ? 'user@example.com' : sprintf('khach%02d@demo.auracourt.vn', $number)];
    }

    private function day(int $offset, string $time): Carbon
    {
        return $this->now->copy()->startOfDay()->addDays($offset)->setTimeFromTimeString($time);
    }

    private function uuid(string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'auracourt-demo/tournament/'.$key)->toString();
    }
}
