<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\MatchBill;
use App\Models\MatchInvitation;
use App\Models\MatchParticipation;
use App\Models\MatchPost;
use App\Models\Organization;
use App\Models\PlayerProfile;
use App\Models\QualifyingActivity;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Social\MatchService;
use App\Services\Social\ReputationService;
use Carbon\Carbon;
use Database\Seeders\Concerns\KeepsWakingHours;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Match posts built on the demo customers' own confirmed reservations (the host is the reservation's customer and the
 * court, sport and time come from it), plus owner-hosted sessions on free court time. Skill levels, attendance and
 * reputation follow the posts, so MatchService's join gate and ReputationService::recalculate agree with the seed.
 */
class MatchmakingSeeder extends Seeder
{
    use KeepsWakingHours;

    private const ORGANIZATION = 'aura-badminton-club';

    // Rounded to 2 decimals like MatchService stores request coordinates.
    private const VENUE_COORDINATES = [
        'aura-court' => [21.03, 105.78],
        'aura-arena-quan-ngua' => [21.04, 105.82],
        'aura-complex-tay-ho' => [21.07, 105.83],
        'aura-landmark-72' => [21.02, 105.78],
        'aura-central-saigon' => [10.80, 106.71],
        'hoang-gia-phu-my-hung' => [10.73, 106.72],
    ];

    // ui: the value MatchCard's detectSport() reads from metadata.sport; it matches "tennis" before "bóng bàn".
    private const SPORTS = [
        'badminton' => ['name' => 'Cầu lông', 'ui' => 'badminton', 'format' => 'Đánh đôi', 'players' => 4, 'title' => 'Cầu lông đôi %s ở %s'],
        'pickleball' => ['name' => 'Pickleball', 'ui' => 'pickleball', 'format' => 'Đánh đôi', 'players' => 4, 'title' => 'Pickleball đôi %s ở %s'],
        'tennis' => ['name' => 'Tennis', 'ui' => 'tennis', 'format' => 'Đánh đôi', 'players' => 4, 'title' => 'Tennis đôi %s ở %s'],
        'table_tennis' => ['name' => 'Bóng bàn', 'ui' => 'bóng bàn', 'format' => 'Đánh đơn xoay vòng', 'players' => 4, 'title' => 'Bóng bàn giao lưu %s ở %s'],
        'football_5v5' => ['name' => 'Bóng đá', 'ui' => 'football', 'format' => '5v5', 'players' => 10, 'title' => 'Bóng đá 5 người %s ở %s'],
        'football_7v7' => ['name' => 'Bóng đá', 'ui' => 'football', 'format' => '7v7', 'players' => 14, 'title' => 'Bóng đá 7 người %s ở %s'],
        'basketball' => ['name' => 'Bóng rổ', 'ui' => 'basketball', 'format' => '3x3', 'players' => 6, 'title' => 'Bóng rổ 3x3 %s ở %s'],
        'volleyball' => ['name' => 'Bóng chuyền', 'ui' => 'volleyball', 'format' => '6v6', 'players' => 12, 'title' => 'Bóng chuyền 6 người %s ở %s'],
    ];

    private const WEEKDAYS = [1 => 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy', 'Chủ Nhật'];

    /** Skill level (1-5) by position in the customer list; position 0 is user@example.com. */
    private const SKILLS = [3, 2, 4, 3, 5, 2, 4, 3];

    // Mirrors ReputationService::DELTAS for the activity types seeded here.
    private const REPUTATION_DELTAS = ['attendance' => 2, 'no_show' => -15];

    private Carbon $now;

    private int $orgId;

    private int $rotation = 0;

    /** @var Collection<int, User> */
    private Collection $customers;

    /** @var array<int, int> */
    private array $skill = [];

    /** @var array<int, array{city: string, slug: string}> */
    private array $home = [];

    /** @var array<int, list<array{0: Carbon, 1: Carbon}>> */
    private array $busy = [];

    public function run(MatchService $matches, ReputationService $reputation): void
    {
        $this->now = now()->setSecond(0);
        $organization = Organization::where('slug', self::ORGANIZATION)->first();
        $owner = User::where('email', 'owner@demo.auracourt.vn')->first();
        $this->customers = $this->customers();
        if (! $organization || $this->customers->isEmpty()) {
            return;
        }
        $this->orgId = $organization->id;
        $this->forgetPreviousRun();

        $bookings = $this->bookings();
        $this->prepareCustomers($bookings);

        $own = $bookings->where('organization_id', $this->orgId);
        $used = [];
        // Upcoming: prefer sessions from tomorrow on so the open list stays populated through the demo days.
        $windows = [
            'upcoming' => [
                fn (array $b) => $b['start']->between($this->now->copy()->addHours(20), $this->now->copy()->addDays(14)),
                fn (array $b) => $b['start']->between($this->now->copy()->addHours(2), $this->now->copy()->addDays(14)),
            ],
            'past' => [fn (array $b) => $b['start']->gte($this->now->copy()->subDays(30)) && $b['end']->lte($this->now->copy()->subHour())],
        ];
        $pick = function (callable $filter, string $when) use ($own, $windows, &$used): ?array {
            foreach ($windows[$when] as $window) {
                $pool = $own->filter(fn (array $b) => $window($b) && ! in_array($b['id'], $used, true));
                $booking = ($when === 'past' ? $pool->reverse() : $pool)->first(fn (array $b) => $filter($b));
                if ($booking) {
                    $used[] = $booking['id'];

                    return $booking;
                }
            }

            return null;
        };

        $primary = $this->customers->first();
        $open = [];
        if ($booking = $pick(fn (array $b) => $b['user_id'] === $primary->id, 'upcoming')) {
            $open[] = $this->reservationPost('demo-match-open-primary', $booking, 'open', 1);
        }
        foreach (array_keys(self::SPORTS) as $i => $sport) {
            if ($booking = $pick(fn (array $b) => $b['sport'] === $sport, 'upcoming')) {
                $open[] = $this->reservationPost("demo-match-open-{$sport}", $booking, 'open', 1 + $i % 3);
            }
        }
        if ($booking = $pick(fn (array $b) => self::SPORTS[$b['sport']]['players'] === 4, 'upcoming')) {
            $this->reservationPost('demo-match-full', $booking, 'full', 0, [$primary]);
        }

        $pastSports = [];
        $pastFilter = function (array $b) use (&$pastSports): bool {
            return ! in_array($b['sport'], $pastSports, true);
        };
        if ($booking = $pick(fn (array $b) => $b['user_id'] === $primary->id, 'past')) {
            $pastSports[] = $booking['sport'];
            $this->reservationPost('demo-match-completed-primary', $booking, 'completed', 0);
        }
        foreach (['demo-match-completed-1', 'demo-match-completed-2'] as $key) {
            if ($booking = $pick($pastFilter, 'past')) {
                $pastSports[] = $booking['sport'];
                $this->reservationPost($key, $booking, 'completed', 0, [$primary]);
            }
        }
        if ($booking = $pick($pastFilter, 'past')) {
            $this->reservationPost('demo-match-cancelled', $booking, 'cancelled', 0);
        }

        if ($owner) {
            $session = $this->ownerSession($matches, $owner, 'demo-match-owner-ended', range(-3, -14), ['aura-court', 'aura-arena-quan-ngua'], 'ended');
            if ($session && $session->joined_count > 0) {
                $this->bill($matches, $session, $owner);
            }
            // Open sessions hold court time without a reservation slot; start late in the evening and a few days out
            // to stay clear of the demo bookings later seeders add for the next couple of days.
            $this->ownerSession($matches, $owner, 'demo-match-owner-open', range(3, 9), ['aura-arena-quan-ngua', 'aura-court'], 'open', $primary);
        }

        $invitable = collect($open)->first(fn (MatchPost $post) => $post->creator_id !== $primary->id
            && $this->skill[$primary->id] >= $post->min_skill && $this->skill[$primary->id] <= $post->max_skill
            && $this->isFree($primary->id, $this->local($post->starts_at), $this->local($post->ends_at)));
        if ($invitable) {
            $this->invite($invitable, $primary, $invitable->created_at->copy()->addMinutes(40)->min($this->now), 'pending');
        }

        $this->profiles($bookings, $reputation);
    }

    private function customers(): Collection
    {
        $emails = array_merge(['user@example.com'], array_map(fn (int $n) => sprintf('khach%02d@demo.auracourt.vn', $n), range(1, 24)));
        $users = User::whereIn('email', $emails)->get()->keyBy('email');

        return collect($emails)->map(fn (string $email) => $users->get($email))->filter()->values();
    }

    private function forgetPreviousRun(): void
    {
        $ids = MatchPost::query()
            ->where('organization_id', $this->orgId)
            ->where('metadata->seed_key', 'like', 'demo-match-%')
            ->pluck('id');

        // match_bills restricts post deletes; participations and invitations cascade.
        MatchBill::whereIn('match_post_id', $ids)->delete();
        QualifyingActivity::where('source_type', 'match_post')->whereIn('source_id', $ids)->delete();
        MatchPost::whereIn('id', $ids)->delete();
    }

    /**
     * Confirmed reservations of the demo customers (every org, for availability and home venue), oldest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function bookings(): Collection
    {
        $users = $this->customers->keyBy('id');

        return Reservation::query()
            ->with(['court.venue', 'court.sportZone', 'slots'])
            ->where('status', 'confirmed')
            ->whereIn('user_id', $users->keys())
            ->get()
            ->filter(fn (Reservation $r) => $r->slots->isNotEmpty() && $r->court?->venue
                && isset(self::SPORTS[$r->court->sportZone?->sport_type], self::VENUE_COORDINATES[$r->court->venue->slug]))
            ->map(fn (Reservation $r) => [
                'id' => $r->id,
                'reservation' => $r,
                'organization_id' => $r->organization_id,
                'user_id' => $r->user_id,
                'host' => $users[$r->user_id],
                'court' => $r->court,
                'sport' => $r->court->sportZone->sport_type,
                'start' => $this->local($r->slots->min('starts_at')),
                'end' => $this->local($r->slots->max('ends_at')),
            ])
            ->sortBy(fn (array $b) => sprintf('%012d-%012d', $b['start']->getTimestamp(), $b['id']))
            ->values();
    }

    private function prepareCustomers(Collection $bookings): void
    {
        foreach ($this->customers as $index => $user) {
            $mine = $bookings->where('user_id', $user->id);
            // Home venue: the one the customer books most (ties go to the one booked first).
            $slug = $mine->countBy(fn (array $b) => $b['court']->venue->slug)->sortDesc()->keys()->first() ?? 'aura-court';
            $venue = $mine->map(fn (array $b) => $b['court']->venue)->firstWhere('slug', $slug);

            $this->skill[$user->id] = self::SKILLS[$index % count(self::SKILLS)];
            $this->home[$user->id] = ['city' => $venue?->city ?? 'Hà Nội', 'slug' => $slug];
            $this->busy[$user->id] = $mine->map(fn (array $b) => [$b['start'], $b['end']])->values()->all();
        }
    }

    /** @param list<User> $prefer */
    private function reservationPost(string $key, array $booking, string $status, int $missing, array $prefer = []): MatchPost
    {
        $sport = self::SPORTS[$booking['sport']];
        $court = $booking['court'];
        $venue = $court->venue;
        [$start, $end] = [$booking['start'], $booking['end']];
        $hostSkill = $this->skill[$booking['user_id']];
        [$min, $max] = [max(1, $hostSkill - 1), min(5, $hostSkill + 1)];
        $courtName = $court->getTranslation('name', 'vi');
        $details = [
            'sport' => $sport['ui'],
            'sport_name' => $sport['name'],
            'format' => $sport['format'],
            'split_cost' => false,
            'cost_per_person' => (int) ceil($booking['reservation']->total_amount / $sport['players'] / 1000) * 1000,
            'court_id' => $court->id,
            'court_name' => $courtName,
            'venue_name' => $venue->getTranslation('name', 'vi'),
        ];

        $bookedAt = $booking['reservation']->created_at ?? $start->copy()->subDays(5);
        $createdAt = $start->copy()->subDays(3)->setTime(21, 15)
            ->max($bookedAt->copy()->addMinutes(30))
            ->min($this->now->copy()->subMinutes(90))
            ->min($start->copy()->subHour())
            ->max($bookedAt)
            ->min($this->now)
            ->copy();
        $createdAt = $this->awake($createdAt, $bookedAt, $start->copy()->subHour()->min($this->now->copy()->subMinutes(90)));

        $joiners = $status === 'cancelled'
            ? collect()
            : $this->joiners($booking['user_id'], $venue->city, $start, $end, $sport['players'] - 1 - $missing, [$min, $max], $prefer);
        $until = $start->copy()->subMinutes(30)->min($this->now);
        $joinedAt = $joiners->keys()->map(fn (int $i) => $this->spread($createdAt, $until, $i, $joiners->count()));
        $joined = 1 + $joiners->count();
        $recordedAt = $end->copy()->addMinutes(10);

        $post = MatchPost::forceCreate([
            'organization_id' => $this->orgId,
            'creator_id' => $booking['user_id'],
            'court_id' => $court->id,
            'reservation_id' => $booking['id'],
            'entitlement_id' => $booking['reservation']->entitlements()->where('user_id', $booking['user_id'])
                ->whereIn('status', ['active', 'confirmed'])->orderBy('id')->value('id'),
            'title' => sprintf($sport['title'], $this->when($start), $venue->district),
            'visibility' => 'public',
            'starts_at' => $start,
            'ends_at' => $end,
            'city' => $venue->city,
            'approximate_latitude' => self::VENUE_COORDINATES[$venue->slug][0],
            'approximate_longitude' => self::VENUE_COORDINATES[$venue->slug][1],
            'min_skill' => $min,
            'max_skill' => $max,
            'required_participants' => $sport['players'],
            'joined_count' => $joined,
            'status' => $status === 'full' && $joined < $sport['players'] ? 'open' : $status,
            'availability' => $details + ['description' => sprintf(
                'Đã đặt %s, %s-%s %s %s. Ưu tiên bạn trình độ %d-%d, đến sớm 10 phút khởi động, tiền sân chia đều.',
                $courtName, $start->format('H:i'), $end->format('H:i'), self::WEEKDAYS[$start->dayOfWeekIso], $start->format('d/m'), $min, $max
            )],
            'metadata' => $details + ['seed_key' => $key]
                + ($status === 'cancelled' ? ['cancel_reason' => 'Không đủ người, chủ kèo hủy và giữ sân đánh tập.'] : []),
            'created_at' => $createdAt,
            'updated_at' => match ($status) {
                'completed' => $recordedAt,
                'cancelled' => $createdAt->copy()->addDay()->min($start->copy()->subHours(3))->max($createdAt),
                default => $joinedAt->last() ?? $createdAt,
            },
        ]);

        $completed = $status === 'completed';
        $this->join($post, $booking['host'], $createdAt, $completed ? $this->attendance('present', $booking['user_id'], $recordedAt) : []);
        foreach ($joiners as $i => $user) {
            $attendance = match (true) {
                ! $completed => [],
                $key === 'demo-match-completed-1' && $i === 0 => $this->attendance('late', $booking['user_id'], $recordedAt),
                $key === 'demo-match-completed-2' && $i === $joiners->count() - 1 => $this->attendance('absent', $booking['user_id'], $recordedAt),
                default => $this->attendance('present', $booking['user_id'], $recordedAt),
            };
            $this->join($post, $user, $joinedAt[$i], $attendance);
        }

        return $post;
    }

    /** @param list<string> $venues venue slugs in order of preference */
    private function ownerSession(MatchService $matches, User $owner, string $key, array $days, array $venues, string $status, ?User $invitee = null): ?MatchPost
    {
        $courts = Court::query()
            ->with('venue')
            ->whereHas('venue', fn ($q) => $q->where('organization_id', $this->orgId)->whereIn('slug', $venues))
            ->whereHas('sportZone', fn ($q) => $q->where('sport_type', 'badminton'))
            ->orderBy('slug')
            ->get()
            ->sortBy(fn (Court $court) => array_search($court->venue->slug, $venues, true))
            ->values();

        // Try each candidate time on every court (MatchService::checkCourtAvailability rules) before moving on.
        foreach ($days as $day) {
            foreach ([20, 19] as $hour) {
                $start = $this->now->copy()->startOfDay()->addDays($day)->setTime($hour, 0);
                $end = $start->copy()->addHours(2);
                if ($invitee && ! $this->isFree($invitee->id, $start, $end)) {
                    continue;
                }
                foreach ($courts as $court) {
                    try {
                        $matches->checkCourtAvailability($court, $start, $end);
                    } catch (HttpException) {
                        continue;
                    }

                    return $this->createOwnerSession($owner, $court, $key, $start, $end, $status, $invitee);
                }
            }
        }

        return null;
    }

    private function createOwnerSession(User $owner, Court $court, string $key, Carbon $start, Carbon $end, string $status, ?User $invitee): MatchPost
    {
        $venue = $court->venue;
        $venueName = $venue->getTranslation('name', 'vi');
        $ended = $status === 'ended';
        [$category, $labels] = $ended ? ['đánh vãng lai', ['Y', 'TBY']] : ['giao lưu', ['TBY', 'TB']];
        $createdAt = $this->awake($start->copy()->subDays(4)->setTime(10, 0)->min($this->now->copy()->subHours(2)), before: $this->now->copy()->subHours(2));
        $recordedAt = $end->copy()->addMinutes(5);
        $until = $start->copy()->subMinutes(30)->min($this->now);

        // The host is not a player on owner-hosted sessions; on the open one a third candidate declines the invite.
        $candidates = $this->joiners($owner->id, $venue->city, $start, $end, $ended ? 6 : 3, null, [], $invitee ? [$invitee->id] : []);
        $joiners = $ended ? $candidates : $candidates->take(2);
        $decliner = $ended ? null : $candidates->get(2);
        $joinedAt = $joiners->keys()->map(fn (int $i) => $this->spread($createdAt, $until, $i, $joiners->count()));
        $acceptedAt = $createdAt->copy()->addMinutes(150)->min($this->now);

        $post = MatchPost::forceCreate([
            'organization_id' => $this->orgId,
            'creator_id' => $owner->id,
            'court_id' => $court->id,
            'title' => sprintf('Giao lưu cầu lông %s tại %s', $this->when($start), $venueName),
            'visibility' => 'public',
            'is_owner_hosted' => true,
            'category' => $category,
            'skill_levels' => $labels,
            'starts_at' => $start,
            'ends_at' => $end,
            'city' => $venue->city,
            'approximate_latitude' => self::VENUE_COORDINATES[$venue->slug][0],
            'approximate_longitude' => self::VENUE_COORDINATES[$venue->slug][1],
            'min_skill' => null,
            'max_skill' => null,
            'required_participants' => 8,
            'joined_count' => $joiners->count() + ($invitee ? 1 : 0),
            'status' => $status,
            'availability' => ['description' => sprintf(
                'Chủ sân mở %s từ %s đến %s, %s %s. Có sẵn cầu thi đấu, tiền sân và cầu chia đều theo số người đến chơi.',
                $court->getTranslation('name', 'vi'), $start->format('H:i'), $end->format('H:i'), self::WEEKDAYS[$start->dayOfWeekIso], $start->format('d/m')
            )],
            'metadata' => [
                'is_owner_hosted' => true,
                'court_id' => $court->id,
                'category' => $category,
                'skill_levels' => $labels,
                'venue_name' => $venueName,
                'sport' => 'badminton',
                'sport_name' => 'Cầu lông',
                'cost_per_person' => 0,
                'seed_key' => $key,
            ],
            'created_at' => $createdAt,
            'updated_at' => $ended ? $recordedAt : collect([$joinedAt->last(), $invitee ? $acceptedAt : null, $createdAt])->filter()->max(),
        ]);

        if ($invitee) {
            $this->invite($post, $invitee, $createdAt->copy()->addMinutes(30), 'accepted');
            $this->join($post, $invitee, $acceptedAt, []);
        }
        foreach ($joiners as $i => $user) {
            $attendance = $ended ? $this->attendance($i === 2 ? 'late' : 'present', $owner->id, $recordedAt) : [];
            $this->join($post, $user, $joinedAt[$i], $attendance);
        }
        if ($decliner) {
            $this->invite($post, $decliner, $createdAt->copy()->addMinutes(35), 'declined');
        }

        return $post;
    }

    private function bill(MatchService $matches, MatchPost $post, User $owner): void
    {
        $start = $this->local($post->starts_at);
        $minutes = (int) $start->diffInMinutes($this->local($post->ends_at));
        $rate = (int) ($matches->getDefaultRates($post->court, $start)['court_hourly_rate'] ?? 0);
        $headcount = $post->joined_count;
        [$shuttles, $shuttlePrice] = [10, 25000];
        $calc = $matches->calculateBill($headcount, $minutes, $rate, $shuttles, $shuttlePrice);
        $finalizedAt = $this->local($post->ends_at)->addMinutes(25);

        MatchBill::forceCreate([
            'organization_id' => $post->organization_id,
            'match_post_id' => $post->id,
            'actual_headcount' => $headcount,
            'court_duration_minutes' => $minutes,
            'court_hourly_rate' => $rate,
            'shuttlecock_count' => $shuttles,
            'shuttlecock_unit_price' => $shuttlePrice,
            'court_subtotal' => $calc['court_subtotal'],
            'shuttlecock_subtotal' => $calc['shuttlecock_subtotal'],
            'total_amount' => $calc['total_amount'],
            'per_person_amount' => $calc['per_person_amount'],
            'is_approximate' => $calc['is_approximate'],
            'status' => MatchBill::STATUS_FINALIZED,
            'version' => 1,
            'finalized_by' => $owner->id,
            'finalized_at' => $finalizedAt,
            'snapshot' => [
                'inputs' => [
                    'actual_headcount' => $headcount,
                    'court_duration_minutes' => $minutes,
                    'court_hourly_rate' => $rate,
                    'shuttlecock_count' => $shuttles,
                    'shuttlecock_unit_price' => $shuttlePrice,
                ],
                'breakdown' => [
                    'court_subtotal' => $calc['court_subtotal'],
                    'shuttlecock_subtotal' => $calc['shuttlecock_subtotal'],
                    'total_amount' => $calc['total_amount'],
                    'divisor' => $headcount,
                    'per_person_amount' => $calc['per_person_amount'],
                    'is_approximate' => $calc['is_approximate'],
                ],
                'host' => ['id' => $owner->id, 'name' => $owner->name],
                'finalized_at' => $finalizedAt->toIso8601String(),
            ],
            'created_at' => $finalizedAt,
            'updated_at' => $finalizedAt,
        ]);
    }

    private function invite(MatchPost $post, User $invitee, Carbon $at, string $status): MatchInvitation
    {
        $at = $this->awake($at, $post->created_at);
        $respondedAt = $status === 'pending' ? null : $this->awake($at->copy()->addHours(2)->min($this->now), $at);

        return MatchInvitation::forceCreate([
            'organization_id' => $post->organization_id,
            'match_post_id' => $post->id,
            'invitee_id' => $invitee->id,
            'invited_by' => $post->creator_id,
            'status' => $status,
            'responded_at' => $respondedAt,
            'created_at' => $at,
            'updated_at' => $respondedAt ?? $at,
        ]);
    }

    /**
     * Free customers in the skill range, same home city first, rotated so each post draws a different crowd.
     *
     * @param  list<User>  $prefer
     * @param  list<int>  $exclude
     */
    private function joiners(int $hostId, string $city, Carbon $start, Carbon $end, int $count, ?array $range, array $prefer, array $exclude = []): Collection
    {
        $offset = ($this->rotation++ * 7) % $this->customers->count();
        $rotated = $this->customers->slice($offset)->concat($this->customers->take($offset));

        return collect($prefer)
            ->concat($rotated->sortBy(fn (User $user) => $this->home[$user->id]['city'] === $city ? 0 : 1))
            ->unique('id')
            ->reject(fn (User $user) => $user->id === $hostId || in_array($user->id, $exclude, true))
            ->filter(fn (User $user) => ! $range || ($this->skill[$user->id] >= $range[0] && $this->skill[$user->id] <= $range[1]))
            ->filter(fn (User $user) => $this->isFree($user->id, $start, $end))
            ->take(max(0, $count))
            ->values();
    }

    private function join(MatchPost $post, User $user, Carbon $at, array $attendance): void
    {
        $at = $this->awake($at, $post->created_at, $post->starts_at);
        MatchParticipation::forceCreate([
            'organization_id' => $post->organization_id,
            'match_post_id' => $post->id,
            'user_id' => $user->id,
            'status' => 'joined',
            'joined_at' => $at,
            'created_at' => $at,
            'updated_at' => $attendance['attendance_recorded_at'] ?? $at,
        ] + $attendance);
        $this->busy[$user->id][] = [$this->local($post->starts_at), $this->local($post->ends_at)];

        if ($attendance !== []) {
            $type = $attendance['attendance_status'] === 'absent' ? 'no_show' : 'attendance';
            QualifyingActivity::forceCreate([
                'organization_id' => $post->organization_id,
                'user_id' => $user->id,
                'type' => $type,
                'score_delta' => self::REPUTATION_DELTAS[$type],
                'source_type' => 'match_post',
                'source_id' => $post->id,
                'verified_by' => $attendance['attendance_recorded_by'],
                'qualifies_at' => $attendance['attendance_recorded_at'],
                'created_at' => $attendance['attendance_recorded_at'],
                'updated_at' => $attendance['attendance_recorded_at'],
            ]);
        }
    }

    private function attendance(string $status, int $recordedBy, Carbon $at): array
    {
        return [
            'attendance_status' => $status,
            'attendance_recorded_by' => $recordedBy,
            'attendance_recorded_at' => $at,
            'verified_at' => $status === 'absent' ? null : $at,
        ];
    }

    private function profiles(Collection $bookings, ReputationService $reputation): void
    {
        foreach ($this->customers as $index => $user) {
            $home = $this->home[$user->id];
            $availability = $bookings->where('user_id', $user->id)
                ->map(fn (array $b) => ($b['start']->isWeekend() ? 'weekend' : 'weekday').'_'.match (true) {
                    $b['start']->hour < 12 => 'mornings',
                    $b['start']->hour < 17 => 'afternoons',
                    default => 'evenings',
                })
                ->unique()->sort()->values()->all();
            $createdAt = $this->awake($this->now->copy()->subDays(45)->setTime(20, 0)->addHours($index * 5));

            PlayerProfile::updateOrCreate(
                ['organization_id' => $this->orgId, 'user_id' => $user->id],
                [
                    'skill_level' => $this->skill[$user->id],
                    'city' => $home['city'],
                    'approximate_latitude' => self::VENUE_COORDINATES[$home['slug']][0],
                    'approximate_longitude' => self::VENUE_COORDINATES[$home['slug']][1],
                    'location_precision_km' => 5,
                    'availability' => $availability ?: ['weekday_evenings'],
                    'preferred_positions' => [],
                    'visibility' => 'organization',
                ]
            )->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

            // 60 plus the seeded attendance deltas, exactly as the app recomputes it.
            $reputation->recalculate($user, $this->orgId);
        }
    }

    private function isFree(int $userId, Carbon $start, Carbon $end): bool
    {
        foreach ($this->busy[$userId] ?? [] as [$from, $to]) {
            if ($from->lt($end) && $start->lt($to)) {
                return false;
            }
        }

        return true;
    }

    private function spread(Carbon $from, Carbon $to, int $index, int $count): Carbon
    {
        $span = max(0, $to->getTimestamp() - $from->getTimestamp());

        return $from->copy()->addSeconds(intdiv($span * ($index + 1), $count + 1));
    }

    public static function when(Carbon $at): string
    {
        $part = match (true) {
            $at->hour < 11 => 'sáng',
            $at->hour < 13 => 'trưa',
            $at->hour < 18 => 'chiều',
            default => 'tối',
        };

        return $part.' '.self::WEEKDAYS[$at->dayOfWeekIso];
    }

    private function local(Carbon $at): Carbon
    {
        return $at->copy()->setTimezone(config('app.timezone'));
    }
}
