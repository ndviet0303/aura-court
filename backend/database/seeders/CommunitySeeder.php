<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReaction;
use App\Models\FeedReport;
use App\Models\Friendship;
use App\Models\MatchBill;
use App\Models\MatchInvitation;
use App\Models\MatchParticipation;
use App\Models\MatchPost;
use App\Models\ModerationRecord;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\AuraBookingNotification;
use App\Notifications\OutboxNotification;
use Carbon\Carbon;
use Database\Seeders\Concerns\KeepsWakingHours;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Club feed with moderation history, friendships around user@example.com, and the in-app notifications the app would
 * have stored for the seeded bookings, registrations, match invitations and friendships. Everything is read back from
 * what earlier seeders created. Notification rows are inserted in the database-channel shape, so nothing is sent.
 */
class CommunitySeeder extends Seeder
{
    use KeepsWakingHours;

    private const REACTIONS = ['like', 'love', 'like', 'celebrate', 'like', 'fire'];

    private const CROWD_SIZES = [6, 3, 8, 4, 5, 2, 7, 4];

    private Carbon $now;

    private Organization $organization;

    private User $owner;

    private User $primary;

    /** @var Collection<int, User> */
    private Collection $customers;

    /** @var list<array<string, mixed>> */
    private array $posts = [];

    /** @var list<array<string, mixed>> */
    private array $notifications = [];

    public function run(): void
    {
        $this->now = now()->setSecond(0);
        $organization = Organization::where('slug', 'aura-badminton-club')->first();
        $owner = User::where('email', 'owner@demo.auracourt.vn')->first();
        $emails = array_merge(['user@example.com'], array_map(fn (int $n) => sprintf('khach%02d@demo.auracourt.vn', $n), range(1, 24)));
        $users = User::whereIn('email', $emails)->get()->keyBy('email');
        $this->customers = collect($emails)->map(fn (string $email) => $users->get($email))->filter()->values();
        if (! $organization || ! $owner || $this->customers->count() < 12) {
            return;
        }
        [$this->organization, $this->owner, $this->primary] = [$organization, $owner, $this->customers->first()];

        $this->friendships();

        $this->tournamentPost($organization, $owner, true);
        $hoangGia = Organization::where('slug', 'hoang-gia-sports')->first();
        $hoangGiaOwner = User::where('email', 'owner.hoanggia@demo.auracourt.vn')->first();
        if ($hoangGia && $hoangGiaOwner) {
            $this->tournamentPost($hoangGia, $hoangGiaOwner, false);
        }
        $this->ownerSessionPosts();
        $this->primaryPosts();
        $this->matchRecaps();
        $this->memberPosts();
        $this->persistPosts();

        $this->customerNotifications();
        $this->ownerNotifications();
        $this->persistNotifications();
    }

    /**
     * user@example.com befriends people from their finished matches (falling back to the customer list), has two
     * incoming and one outgoing pending request; other players who shared a finished match become friends in pairs.
     */
    private function friendships(): void
    {
        $primary = $this->primary;
        $pairs = [];
        $befriend = function (?User $from, ?User $to, Carbon $askedAt, ?Carbon $acceptedAt) use (&$pairs): void {
            $key = $from && $to ? min($from->id, $to->id).'-'.max($from->id, $to->id) : null;
            if (! $key || $from->id === $to->id || isset($pairs[$key])) {
                return;
            }
            $pairs[$key] = true;
            Friendship::firstOrNew(['requester_id' => $from->id, 'addressee_id' => $to->id])->forceFill([
                'status' => $acceptedAt ? Friendship::STATUS_ACCEPTED : Friendship::STATUS_PENDING,
                'created_at' => $askedAt,
                'updated_at' => $acceptedAt ?? $askedAt,
            ])->save();
        };

        $finished = MatchPost::query()
            ->with(['participations' => fn ($q) => $q->where('status', 'joined')->orderBy('joined_at')->orderBy('id')])
            ->where('organization_id', $this->organization->id)
            ->whereIn('status', ['completed', 'ended'])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            // Friend requests follow a few hours after the match, so only matches that ended half a day ago count.
            ->filter(fn (MatchPost $post) => $post->ends_at->lte($this->now->copy()->subHours(12)));

        $met = [];
        foreach ($finished as $post) {
            $ids = $post->participations->pluck('user_id');
            foreach ($ids->contains($primary->id) ? $ids : [] as $id) {
                if ($id !== $primary->id && $this->customer($id)) {
                    $met[$id] ??= $this->local($post->ends_at);
                }
            }
        }

        $friends = collect(array_keys($met))->concat($this->customers->slice(1)->pluck('id'))->unique()->take(4)->values();
        foreach ($friends as $i => $id) {
            $askedAt = isset($met[$id])
                ? $this->awake($met[$id]->copy()->addHours(2 + $i), $met[$id])
                : $this->now->copy()->subDays(20 - 4 * $i)->setTime(21, 0);
            $acceptedAt = $this->awake($askedAt->copy()->addHours(1 + $i), $askedAt);
            // Alternate who asked, so both the request and the "accepted" notification appear for user@example.com.
            [$from, $to] = $i % 2 === 0 ? [$primary, $this->customer($id)] : [$this->customer($id), $primary];
            $befriend($from, $to, $askedAt, $acceptedAt);
        }

        $others = $this->customers->slice(1)->reject(fn (User $user) => $friends->contains($user->id))->values();
        $befriend($others->get(2), $primary, $this->now->copy()->subDay()->setTime(21, 40), null);
        $befriend($others->get(5), $primary, $this->awake($this->now->copy()->subHours(5)), null);
        $befriend($primary, $others->get(8), $this->now->copy()->subDays(2)->setTime(8, 30), null);

        foreach ($finished as $post) {
            $ids = $post->participations->pluck('user_id')
                ->reject(fn (int $id) => $id === $primary->id || ! $this->customer($id))
                ->values();
            for ($i = 0; $i + 1 < min($ids->count(), 6); $i += 2) {
                $askedAt = $this->awake($this->local($post->ends_at)->addHours(3 + $i), $post->ends_at);
                $befriend($this->customer($ids[$i]), $this->customer($ids[$i + 1]), $askedAt, $this->awake($askedAt->copy()->addHours(2), $askedAt));
            }
        }
    }

    /** Organizer announcement for the tenant's next open tournament whose registration is still running. */
    private function tournamentPost(Organization $organization, User $author, bool $withComments): void
    {
        $tournament = Tournament::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'open')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->first(fn (Tournament $t) => isset($t->settings['registration_deadline'], $t->settings['start_date'], $t->settings['venue'], $t->settings['entry_fee'])
                && Carbon::parse($t->settings['registration_deadline'])->gt($this->now));
        if (! $tournament) {
            return;
        }

        $settings = $tournament->settings;
        $deadline = Carbon::parse($settings['registration_deadline'])->setTimezone(config('app.timezone'));
        $days = collect([$settings['start_date'], $settings['end_date'] ?? null])->filter()->unique()
            ->map(fn (string $day) => Carbon::parse($day)->format('d/m'))->implode(' - ');
        $at = $this->now->copy()->subDays(4)->setTime(9, 0)->max($tournament->created_at->copy()->addHours(2));
        $content = sprintf(
            '%s đang mở đăng ký đến hết %s. Thi đấu %s tại %s, lệ phí %s. Anh chị em đăng ký trong mục Giải đấu nhé, số suất có hạn!',
            $tournament->name, $deadline->format('d/m'), $days, $settings['venue'], $settings['entry_fee']
        );

        $comments = [];
        if ($withComments) {
            $entrant = TournamentParticipant::where('tournament_id', $tournament->id)->orderBy('created_at')->orderBy('id')->get()
                ->first(fn (TournamentParticipant $p) => $p->user_id !== $this->primary->id && $this->customer((int) $p->user_id));
            if ($entrant) {
                $comments[] = $this->comment($this->customer($entrant->user_id), 'Mình đăng ký rồi, hẹn gặp mọi người ở giải nhé!',
                    $at->copy()->addMinutes(90)->max($entrant->created_at->copy()->addMinutes(5)));
            }
            $asker = $this->customers->get(7);
            $comments[] = $this->comment($asker, 'Giải có giới hạn trình độ không ạ?', $at->copy()->addHours(3));
            $comments[] = $this->comment($author, 'Bạn xem điều lệ và nội dung thi đấu trong mục Giải đấu nhé, cần hỗ trợ cứ nhắn CLB.',
                $at->copy()->addHours(3)->addMinutes(40), ['likes' => [$asker]]);
        }

        $this->addPost($author, $content, $at, $comments, ['organization_id' => $organization->id]);
    }

    /** The owner announces the upcoming drop-in session and recaps the last finished one with its bill. */
    private function ownerSessionPosts(): void
    {
        $sessions = MatchPost::query()
            ->with(['court.venue', 'bill', 'participations' => fn ($q) => $q->where('status', 'joined')->orderBy('joined_at')->orderBy('id')])
            ->where('organization_id', $this->organization->id)
            ->where('creator_id', $this->owner->id)
            ->where('is_owner_hosted', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (MatchPost $post) => $post->court?->venue)
            ->sortBy(fn (MatchPost $post) => $post->starts_at->getTimestamp());

        $open = $sessions->first(fn (MatchPost $post) => $post->status === 'open' && $post->starts_at->gt($this->now));
        if ($open) {
            $start = $this->local($open->starts_at);
            $at = $open->created_at->copy()->addMinutes(15);
            $comments = $open->participations->take(3)->values()->map(fn (MatchParticipation $p, int $i) => $this->comment(
                $this->customer($p->user_id),
                $p->user_id === $this->primary->id
                    ? 'Mình đã nhận lời mời của CLB, tối đó gặp mọi người nhé!'
                    : ['Cho mình một suất nhé, lâu rồi chưa giao lưu.', 'Mình đăng ký rồi, hẹn gặp cả nhà!', 'Tính mình một chân nhé!'][$i],
                $p->joined_at->copy()->addMinutes(3)->max($at->copy()->addMinutes(5 + $i))
            ))->filter(fn (array $c) => $c['user'])->all();
            $this->addPost($this->owner, sprintf(
                '%s %s, CLB mở buổi giao lưu cầu lông tại %s (%s), %s-%s. Có sẵn cầu thi đấu, tiền sân và cầu chia đều theo số người đến chơi. Ai rảnh vào mục Ghép trận để giữ chỗ nhé!',
                ucfirst(MatchmakingSeeder::when($start)), $start->format('d/m'), $open->court->venue->getTranslation('name', 'vi'),
                $open->court->getTranslation('name', 'vi'), $start->format('H:i'), $this->local($open->ends_at)->format('H:i')
            ), $at, $comments);
        }

        $ended = $sessions->reverse()->first(fn (MatchPost $post) => $post->status === 'ended'
            && $post->bill?->status === MatchBill::STATUS_FINALIZED && $post->bill->finalized_at?->lte($this->now));
        if ($ended) {
            $bill = $ended->bill;
            $start = $this->local($ended->starts_at);
            $at = $bill->finalized_at->copy()->addMinutes(10);
            $late = $ended->participations->firstWhere('attendance_status', 'late');
            $present = $ended->participations->first(fn (MatchParticipation $p) => $p->attendance_status === 'present');
            $comments = array_values(array_filter([
                $late ? $this->comment($this->customer($late->user_id), 'Hôm đó mình đến muộn, cảm ơn mọi người vẫn chờ!', $at->copy()->addMinutes(40)) : null,
                $present ? $this->comment($this->customer($present->user_id), 'Buổi giao lưu vui quá, lần sau CLB mở tiếp nhé!', $at->copy()->addMinutes(75), ['likes' => [$this->owner]]) : null,
            ], fn (?array $c) => $c && $c['user']));
            $this->addPost($this->owner, sprintf(
                'Cảm ơn %d bạn đã đến giao lưu cầu lông %s %s tại %s. CLB đã chốt hóa đơn %s, mỗi người %s%s, mọi người xem chi tiết trong mục Ghép trận nhé.',
                $bill->actual_headcount, MatchmakingSeeder::when($start), $start->format('d/m'), $ended->court->venue->getTranslation('name', 'vi'),
                $this->money($bill->total_amount), $bill->is_approximate ? 'khoảng ' : '', $this->money($bill->per_person_amount)
            ), $at, $comments);
        }
    }

    /** user@example.com looks for a player for their open match (one reply is rude and reported) and posts their entry. */
    private function primaryPosts(): void
    {
        $primary = $this->primary;
        $hosted = MatchPost::query()
            ->with(['court', 'participations' => fn ($q) => $q->where('status', 'joined')->orderBy('joined_at')->orderBy('id')])
            ->where('organization_id', $this->organization->id)
            ->where('creator_id', $primary->id)
            ->where('status', 'open')
            ->whereNotNull('court_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (MatchPost $post) => $post->starts_at->gt($this->now) && $post->joined_count < $post->required_participants)
            ->sortBy(fn (MatchPost $post) => $post->starts_at->getTimestamp())
            ->first();

        if ($hosted) {
            [$start, $end] = [$this->local($hosted->starts_at), $this->local($hosted->ends_at)];
            $at = $hosted->created_at->copy()->addMinutes(5);
            $joiners = $hosted->participations->reject(fn (MatchParticipation $p) => $p->user_id === $primary->id)->values();
            $comments = $joiners->take(2)->map(fn (MatchParticipation $p, int $i) => $this->comment(
                $this->customer($p->user_id),
                $i === 0 ? sprintf('Mình vào kèo rồi nha, hẹn gặp %s!', MatchmakingSeeder::when($start)) : 'Mình cũng vào rồi, đến sớm khởi động cùng nhé!',
                $p->joined_at->copy()->addMinutes(2)->max($at->copy()->addMinute())
            ))->filter(fn (array $c) => $c['user'])->all();

            $avoid = Friendship::where('requester_id', $primary->id)->pluck('addressee_id')
                ->concat(Friendship::where('addressee_id', $primary->id)->pluck('requester_id'))
                ->concat($hosted->participations->pluck('user_id'))
                ->push($this->spammer()->id)
                ->all();
            $rude = $this->customers->reverse()->first(fn (User $user) => ! in_array($user->id, $avoid, true));
            if ($rude) {
                $comments[] = $this->comment($rude, 'Đánh dở mà cũng đòi kén trình, về tập thêm rồi hẵng rủ kèo.', $at->copy()->addMinutes(50), [
                    'report' => ['reporter' => $primary, 'reason' => 'Bình luận chê bai, thiếu tôn trọng người chơi khác.'],
                ]);
            }

            $skill = $hosted->min_skill && $hosted->max_skill ? sprintf(' trình %d-%d', $hosted->min_skill, $hosted->max_skill) : '';
            $this->addPost($primary, sprintf(
                'Kèo %s (%s, %s-%s, %s) còn thiếu %d bạn%s. Ai rảnh vào mục Ghép trận tham gia cùng mình nhé!',
                lcfirst($hosted->title), $start->format('d/m'), $start->format('H:i'), $end->format('H:i'),
                $hosted->court->getTranslation('name', 'vi'), $hosted->required_participants - $hosted->joined_count, $skill
            ), $at, $comments);
        }

        $entry = TournamentParticipant::with('tournament')->where('user_id', $primary->id)->orderBy('created_at')->orderBy('id')->get()
            ->first(fn (TournamentParticipant $p) => $p->tournament?->status === 'open'
                && $p->tournament->organization_id === $this->organization->id && $p->created_at->lte($this->now));
        if ($entry) {
            $at = $entry->created_at->copy()->addMinutes(20);
            $mates = TournamentParticipant::where('tournament_id', $entry->tournament_id)->where('id', '!=', $entry->id)
                ->orderBy('created_at')->orderBy('id')->get()
                ->filter(fn (TournamentParticipant $p) => $this->customer((int) $p->user_id))
                ->take(2)->values();
            $comments = $mates->map(fn (TournamentParticipant $p, int $i) => $this->comment(
                $this->customer($p->user_id),
                $i === 0 ? 'Mình cũng đăng ký rồi, tối nào tập chung thì nhắn mình nhé.' : 'Tính mình vào nhóm tập với nhé, mình cũng có tên trong danh sách rồi.',
                $at->copy()->addMinutes(60 * ($i + 1))->max($p->created_at->copy()->addMinutes(10))
            ))->all();
            $this->addPost($primary, sprintf('Vừa đăng ký %s! Còn ai trong CLB tham gia không, lập nhóm tập chung trước giải nhé.', $entry->tournament->name), $at, $comments);
        }
    }

    /** Hosts of the two latest finished matches thank their players; replies follow the recorded attendance. */
    private function matchRecaps(): void
    {
        $matches = MatchPost::query()
            ->with(['participations' => fn ($q) => $q->where('status', 'joined')->orderBy('joined_at')->orderBy('id')])
            ->where('organization_id', $this->organization->id)
            ->where('status', 'completed')
            ->where('is_owner_hosted', false)
            ->orderBy('id')
            ->get()
            ->filter(fn (MatchPost $post) => $this->customer($post->creator_id) && isset($post->metadata['sport_name'], $post->metadata['venue_name'])
                && $post->ends_at->lte($this->now->copy()->subHours(4)))
            ->sortByDesc(fn (MatchPost $post) => $post->ends_at->getTimestamp())
            ->take(2);

        foreach ($matches as $match) {
            $host = $this->customer($match->creator_id);
            // Hosts post the recap once home, the next morning when the match ran late.
            $at = $this->awake($this->local($match->ends_at)->addHours(2), $match->ends_at->copy()->addMinutes(30));
            $replies = ['Đánh vui quá, hẹn kèo sau!', 'Trận hay thật, lần sau cho mình đánh cặp với bạn nhé.', 'Cảm ơn chủ kèo, sân đẹp mà giá cũng hợp lý.'];
            $comments = $match->participations->reject(fn (MatchParticipation $p) => $p->user_id === $host->id)->take(3)->values()
                ->map(fn (MatchParticipation $p, int $i) => $this->comment($this->customer($p->user_id), match ($p->attendance_status) {
                    'late' => 'Mình đến muộn chút, cảm ơn cả nhà đã chờ!',
                    'absent' => 'Xin lỗi mọi người, mình bận đột xuất nên không đến được.',
                    default => $replies[$i],
                }, $at->copy()->addMinutes(35 * ($i + 1)), ['likes' => [$host]]))
                ->filter(fn (array $c) => $c['user'])->all();

            $this->addPost($host, sprintf(
                'Cảm ơn cả nhà đã ra sân %s %s vừa rồi ở %s. Trận rất vui, tuần sau mình lại làm kèo nhé!',
                mb_strtolower($match->metadata['sport_name']), MatchmakingSeeder::when($this->local($match->starts_at)), $match->metadata['venue_name']
            ), $at, $comments);
        }
    }

    /** Everyday member posts across sports, a friends-only post and one spam post the owner hid after a report. */
    private function memberPosts(): void
    {
        $c = fn (int $i) => $this->customers->get($i % $this->customers->count());
        $saigon = Venue::where('slug', 'aura-central-saigon')->first()?->getTranslation('name', 'vi') ?? 'Aura Sài Gòn';
        $posts = [
            [3, 6, '19:40', 'Có ai biết chỗ căng vợt cầu lông uy tín gần Cầu Giấy không ạ? Vợt mình đứt dây mà cuối tuần có kèo rồi.', [
                [8, 'Mình hay căng ở tiệm gần cổng Đại học Quốc gia, khoảng 30 phút là lấy được.'],
                [15, 'Căng tầm 10-11kg thôi bạn, căng quá dễ đứt dây lại.'],
            ]],
            [5, 5, '07:20', 'Mẹo cho người mới chơi pickleball: đứng sau vạch kitchen khi bóng chưa nảy, ưu tiên dink thấp qua lưới thay vì cố đập mạnh. Mình chơi được 2 tháng thấy tiến bộ hẳn!', [
                [10, 'Chuẩn luôn, hồi mới tập mình toàn đập hỏng.'],
                [2, 'Sân pickleball ở Tây Hồ buổi sáng mát lắm, ai tập sáng thì rủ mình nhé.'],
            ]],
            [9, 4, '12:15', "Đội bóng văn phòng của mình còn thiếu 2 người đá cố định tối Thứ Năm hằng tuần ở {$saigon}. Ai ở gần thì bình luận nhé!", [
                [19, 'Mình ở Phú Nhuận, tối Thứ Năm đá được, nhắn mình nhé.'],
            ]],
            [12, 3, '20:05', 'Ai chơi bóng rổ 3x3 buổi tối ở Landmark 72 không? Mình cần thêm 2 bạn ghép đội tập hằng tuần, trình phong trào là được.', [
                [6, 'Cho mình một suất, mình chơi được hậu vệ.'],
            ]],
            [14, 1, '08:10', 'Tối qua đánh bóng bàn ở Quần Ngựa, bàn mới nảy đều hẳn. Có ai muốn tập giao bóng xoáy cùng mình không?', [
                [21, 'Mình tập được tối Thứ Ba và Thứ Năm, rủ mình nhé.'],
            ]],
            [17, 7, '22:00', 'Sáng cuối tuần sân tennis ở Landmark 72 kín lịch rất nhanh, mọi người nhớ đặt trước 2-3 ngày nhé.', []],
            [20, 8, '18:30', 'Công ty mình đang lập đội bóng chuyền tập tối Thứ Ba ở Landmark 72, còn thiếu 2 chủ công. Ai quan tâm bình luận nhé!', [
                [4, 'Mình từng chơi chủ công cho đội trường, cho mình thử nhé.'],
            ]],
        ];
        foreach ($posts as [$author, $days, $time, $content, $replies]) {
            $at = $this->now->copy()->subDays($days)->setTimeFromTimeString($time);
            $this->addPost($c($author), $content, $at, array_map(
                fn (array $reply, int $i) => $this->comment($c($reply[0]), $reply[1], $at->copy()->addMinutes(45 * ($i + 1))),
                $replies, array_keys($replies)
            ));
        }

        // Friends-only: user@example.com's first friend; only that friend's friends see and react to it.
        $friendship = Friendship::where('status', Friendship::STATUS_ACCEPTED)
            ->where(fn ($q) => $q->where('requester_id', $this->primary->id)->orWhere('addressee_id', $this->primary->id))
            ->orderBy('created_at')->orderBy('id')->first();
        $friend = $friendship ? $this->customer($friendship->requester_id === $this->primary->id ? $friendship->addressee_id : $friendship->requester_id) : null;
        if ($friend) {
            $circle = Friendship::where('status', Friendship::STATUS_ACCEPTED)
                ->where(fn ($q) => $q->where('requester_id', $friend->id)->orWhere('addressee_id', $friend->id))
                ->get()
                ->map(fn (Friendship $f) => $f->requester_id === $friend->id ? $f->addressee_id : $f->requester_id);
            $at = $this->now->copy()->subDays(2)->setTime(21, 30);
            $this->addPost($friend, 'Cuối tuần này nhà mình có việc nên nghỉ kèo, các bạn chơi vui nhé. Tuần sau mình mời nước cả hội!', $at,
                [$this->comment($this->primary, 'Ok bạn, tuần sau gặp nhé!', $at->copy()->addMinutes(25))],
                ['audience' => FeedPost::AUDIENCE_FRIENDS, 'reactors' => $this->customers->whereIn('id', $circle)->values()]);
        }

        $this->addPost($this->spammer(), 'THANH LÝ VỢT, GIÀY XÁCH TAY GIÁ RẺ!!! Nhắn tin riêng để nhận mã giảm thêm 50%, số lượng có hạn.',
            $this->now->copy()->subDays(2)->setTime(23, 10), [], ['hide' => [
                'reporter' => $c(11),
                'report_reason' => 'Spam quảng cáo bán hàng trong nhóm CLB.',
                'reason' => 'Quảng cáo bán hàng không liên quan đến hoạt động câu lạc bộ.',
            ]]);
    }

    /** Inserts posts oldest first (the feed lists by id), then their comments, reactions, reports and moderation. */
    private function persistPosts(): void
    {
        usort($this->posts, fn (array $a, array $b) => $a['at'] <=> $b['at']);

        foreach ($this->posts as $k => $spec) {
            $at = $spec['at'];
            $post = FeedPost::firstOrNew(['organization_id' => $spec['organization_id'], 'user_id' => $spec['author']->id, 'content' => $spec['content']]);
            $post->forceFill(['audience' => $spec['audience'], 'created_at' => $at, 'updated_at' => $at])->save();

            if ($spec['hide']) {
                $this->hide($post, $spec['hide']);

                continue;
            }
            $reactors = $spec['reactors'] ?? ($spec['organization_id'] === $this->organization->id
                ? $this->crowd($k * 5, self::CROWD_SIZES[$k % count(self::CROWD_SIZES)], [$spec['author']->id])
                : collect());
            $this->react(FeedPost::class, $post->id, $at, $reactors->reject(fn (User $user) => $user->id === $spec['author']->id), $k);

            foreach ($spec['comments'] as $j => $reply) {
                $commentAt = $this->awake($reply['at']->copy()->min($this->cap())->max($at), $at);
                $comment = FeedComment::firstOrNew(['feed_post_id' => $post->id, 'user_id' => $reply['user']->id, 'content' => $reply['content']]);
                $comment->forceFill(['created_at' => $commentAt, 'updated_at' => $commentAt])->save();
                $this->react(FeedComment::class, $comment->id, $commentAt, collect($reply['likes'])->filter(), $k + $j);

                if ($reply['report']) {
                    $reportedAt = $this->awake($commentAt->copy()->addMinutes(25)->min($this->cap())->max($commentAt), $commentAt);
                    FeedReport::firstOrNew([
                        'reporter_id' => $reply['report']['reporter']->id,
                        'reportable_type' => FeedComment::class,
                        'reportable_id' => $comment->id,
                    ])->forceFill([
                        'organization_id' => $spec['organization_id'],
                        'reason' => $reply['report']['reason'],
                        'status' => FeedReport::STATUS_PENDING,
                        'created_at' => $reportedAt,
                        'updated_at' => $reportedAt,
                    ])->save();
                }
            }
        }
    }

    /** Same records CommunityFeedService::hideContent writes, with the report filed before the owner acted. */
    private function hide(FeedPost $post, array $hide): void
    {
        $reportedAt = $this->awake($post->created_at->copy()->addMinutes(35)->min($this->cap()), $post->created_at);
        $hiddenAt = $this->awake($post->created_at->copy()->addMinutes(110)->min($this->cap())->max($reportedAt), $reportedAt);

        $post->forceFill([
            'is_hidden' => true,
            'hidden_at' => $hiddenAt,
            'hidden_by' => $this->owner->id,
            'hidden_reason' => $hide['reason'],
            'updated_at' => $hiddenAt,
        ])->save();

        FeedReport::firstOrNew([
            'reporter_id' => $hide['reporter']->id,
            'reportable_type' => FeedPost::class,
            'reportable_id' => $post->id,
        ])->forceFill([
            'organization_id' => $post->organization_id,
            'reason' => $hide['report_reason'],
            'status' => FeedReport::STATUS_RESOLVED,
            'reviewed_by' => $this->owner->id,
            'reviewed_at' => $hiddenAt,
            'action_taken' => 'hidden',
            'created_at' => $reportedAt,
            'updated_at' => $hiddenAt,
        ])->save();

        // Moderation records are immutable, so only create the missing one.
        $record = ModerationRecord::firstOrNew([
            'organization_id' => $post->organization_id,
            'target_type' => FeedPost::class,
            'target_id' => $post->id,
            'action' => 'content_hidden',
        ]);
        if (! $record->exists) {
            $record->forceFill([
                'actor_id' => $this->owner->id,
                'reason' => $hide['reason'],
                'metadata' => ['hidden_by' => $this->owner->name, 'hidden_at' => $hiddenAt->toIso8601String()],
                'created_at' => $hiddenAt,
            ])->save();
        }
    }

    private function react(string $type, int $id, Carbon $from, Collection $users, int $offset): void
    {
        foreach ($users->values() as $j => $user) {
            $at = $this->awake($from->copy()->addMinutes(17 * ($j + 1))->min($this->cap())->max($from), $from);
            FeedReaction::firstOrNew([
                'reactable_type' => $type,
                'reactable_id' => $id,
                'user_id' => $user->id,
                'reaction_type' => self::REACTIONS[($offset + $j) % count(self::REACTIONS)],
            ])->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        }
    }

    /** What the app stored for user@example.com: checkout and expiry notices, registrations, invitations, friendships. */
    private function customerNotifications(): void
    {
        $primary = $this->primary;

        // OrdinaryCheckoutService -> DurableNotificationService::notifyBooking (outbox fallback payload).
        $paid = Booking::with('court')->whereHas('court')->where('user_id', $primary->id)->whereIn('status', ['confirmed', 'completed'])
            ->where('created_at', '<=', $this->now)->orderByDesc('created_at')->orderByDesc('id')->take(2)->get();
        foreach ($paid as $i => $booking) {
            $this->addNotification($primary, "booking/{$booking->id}/payment_confirmed", OutboxNotification::class, [
                'event' => 'payment_confirmed',
                'title' => 'Đặt sân thành công',
                'body' => "Booking {$booking->code} tại {$booking->court->name} đã được thanh toán thành công.",
                'action_url' => '/booking',
                'booking_id' => $booking->id,
                'booking_code' => $booking->code,
            ], $booking->created_at, $i === 0 ? null : $booking->created_at->copy()->addMinutes(12));
        }

        // ExpirePendingBookings (runs every minute).
        $expired = Booking::where('user_id', $primary->id)->where('status', 'expired')->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', $this->cap())->orderByDesc('payment_expires_at')->orderByDesc('id')->first();
        if ($expired) {
            $at = $expired->payment_expires_at->copy()->addMinute();
            $this->addNotification($primary, "booking/{$expired->id}/booking_expired", AuraBookingNotification::class, [
                'event' => 'booking_expired',
                'title' => 'Phiên giữ sân đã hết hạn',
                'body' => "Booking {$expired->code} đã hết thời gian thanh toán và được giải phóng.",
                'action_url' => '/booking',
            ], $at, $at->copy()->addMinutes(40));
        }

        // Path-A outbox payloads copy the service's array; "event" (the outbox event type) is added so the
        // notification page files them under its social tab.
        $entries = TournamentParticipant::with('tournament')->where('user_id', $primary->id)
            ->where('created_at', '<=', $this->now)->orderBy('created_at')->orderBy('id')->get();
        foreach ($entries->filter(fn (TournamentParticipant $p) => $p->tournament) as $entry) {
            $tournament = $entry->tournament;
            $this->addNotification($primary, "tournament/{$entry->id}", OutboxNotification::class, [
                'title' => 'Đăng ký giải đấu thành công',
                'body' => "Bạn đã được ghi danh tham gia giải đấu: {$tournament->name}",
                'type' => 'tournament_update',
                'source_id' => $tournament->id,
                'tournament_id' => $tournament->id,
                'event' => 'tournament.participant_registered',
            ], $entry->created_at, $entry->created_at->copy()->addMinutes(9));
        }

        foreach ($this->invitations()->where('invitee_id', $primary->id) as $invitation) {
            $post = $invitation->post;
            $this->addNotification($primary, "match_invitation/{$invitation->id}", OutboxNotification::class, [
                'title' => 'Lời mời tham gia trận đấu',
                'body' => "{$post->creator->name} đã mời bạn tham gia trận đấu: {$post->title}",
                'type' => 'match_invitation',
                'source_id' => $post->id,
                'invitation_id' => $invitation->id,
                'match_id' => $post->id,
                'event' => 'social.match_invitation',
            ], $invitation->created_at, $invitation->responded_at);
        }

        $friendships = Friendship::with(['requester', 'addressee'])
            ->where(fn ($q) => $q->where('requester_id', $primary->id)->orWhere('addressee_id', $primary->id))
            ->orderBy('created_at')->orderBy('id')->get();
        foreach ($friendships as $friendship) {
            if ($friendship->addressee_id === $primary->id) {
                $this->addNotification($primary, "friendship_request/{$friendship->id}", OutboxNotification::class, [
                    'title' => 'Lời mời kết bạn mới',
                    'body' => "{$friendship->requester->name} đã gửi lời mời kết bạn cho bạn.",
                    'type' => 'friendship_request',
                    'source_id' => $friendship->id,
                    'requester_id' => $friendship->requester_id,
                    'event' => 'social.friendship_request',
                ], $friendship->created_at, $friendship->status === Friendship::STATUS_PENDING ? null : $friendship->updated_at);
            } elseif ($friendship->status === Friendship::STATUS_ACCEPTED) {
                $this->addNotification($primary, "friendship_accepted/{$friendship->id}", OutboxNotification::class, [
                    'title' => 'Lời mời kết bạn được chấp nhận',
                    'body' => "{$friendship->addressee->name} đã đồng ý lời mời kết bạn của bạn.",
                    'type' => 'friendship_accepted',
                    'source_id' => $friendship->id,
                    'friend_id' => $friendship->addressee_id,
                    'event' => 'social.friendship_accepted',
                ], $friendship->updated_at, $friendship->updated_at->copy()->addMinutes(30));
            }
        }
    }

    /** What the app stored for owner@: staff who joined the club, and replies to the owner's match invitations. */
    private function ownerNotifications(): void
    {
        $staff = OrganizationMember::with('user')
            ->where('organization_id', $this->organization->id)
            ->where('status', 'active')
            ->whereIn('role', [OrganizationMember::ROLE_VENUE_MANAGER, OrganizationMember::ROLE_STAFF, OrganizationMember::ROLE_CASHIER])
            ->orderBy('id')
            ->get()
            ->filter(fn (OrganizationMember $member) => $member->user)
            ->values();
        foreach ($staff as $i => $member) {
            $at = $this->awake($member->created_at->copy()->addMinutes(20 + 7 * $i), $member->created_at);
            $this->addNotification($this->owner, "staff_invitation_accepted/{$member->id}", AuraBookingNotification::class, [
                'event' => 'staff_invitation_accepted',
                'title' => 'Lời mời được chấp nhận',
                'body' => "{$member->user->name} ({$member->user->email}) đã chấp nhận lời mời tham gia tổ chức.",
                'action_url' => '/admin/staff',
            ], $at, $at->copy()->addMinutes(45));
        }

        $replies = $this->invitations()->filter(fn (MatchInvitation $invitation) => $invitation->responded_at
            && in_array($invitation->status, ['accepted', 'declined'], true)
            && $invitation->post->creator_id === $this->owner->id);
        foreach ($replies as $invitation) {
            $post = $invitation->post;
            $this->addNotification($this->owner, "match_invitation_response/{$invitation->id}/{$invitation->status}", OutboxNotification::class, [
                'title' => 'Phản hồi lời mời trận đấu',
                'body' => "{$invitation->invitee->name} đã ".($invitation->status === 'accepted' ? 'chấp nhận' : 'từ chối')." lời mời tham gia trận đấu: {$post->title}",
                'type' => 'match_invitation_response',
                'source_id' => $post->id,
                'invitation_id' => $invitation->id,
                'status' => $invitation->status,
                'event' => 'social.match_invitation_response',
            ], $invitation->responded_at, $invitation->invitee_id === $this->primary->id ? null : $invitation->responded_at->copy()->addHour());
        }
    }

    private function persistNotifications(): void
    {
        $rows = collect($this->notifications)->groupBy('notifiable_id')->flatMap(function (Collection $rows) {
            $next = null;

            // The page orders by created_at only, so walk newest first and keep each row a second before the next.
            return $rows->sortByDesc(fn (array $row) => $row['created_at']->getTimestamp())->values()->map(function (array $row) use (&$next) {
                if ($next && $row['created_at']->gte($next)) {
                    $row['created_at'] = $next->copy()->subSecond();
                }
                $next = $row['created_at'];
                $row['read_at'] = $row['read_at']?->copy()->max($row['created_at']->copy()->addMinute())->min($this->now);
                $row['updated_at'] = $row['read_at'] ?? $row['created_at'];

                return $row;
            });
        })->values();

        DB::table('notifications')->whereIn('id', $rows->pluck('id'))->delete();
        DB::table('notifications')->insert($rows->all());
    }

    private function addNotification(User $user, string $key, string $type, array $data, Carbon $at, ?Carbon $readAt): void
    {
        $this->notifications[] = [
            'id' => Uuid::uuid5(Uuid::NAMESPACE_URL, "auracourt-demo/notification/{$user->id}/{$key}")->toString(),
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($data),
            'read_at' => $readAt,
            'created_at' => $at->copy()->min($this->now),
        ];
    }

    /** @return Collection<int, MatchInvitation> */
    private function invitations(): Collection
    {
        return MatchInvitation::with(['post.creator', 'invitee'])
            ->where('organization_id', $this->organization->id)
            ->where('created_at', '<=', $this->now)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (MatchInvitation $invitation) => $invitation->post?->creator && $invitation->invitee);
    }

    /** @param list<array<string, mixed>> $comments */
    private function addPost(User $author, string $content, Carbon $at, array $comments = [], array $extra = []): void
    {
        $this->posts[] = $extra + [
            'organization_id' => $this->organization->id,
            'author' => $author,
            'content' => $content,
            'at' => $at->copy()->min($this->cap()),
            'audience' => FeedPost::AUDIENCE_ORGANIZATION,
            'comments' => $comments,
            'reactors' => null,
            'hide' => null,
        ];
    }

    private function comment(?User $user, string $content, Carbon $at, array $extra = []): array
    {
        return $extra + ['user' => $user, 'content' => $content, 'at' => $at, 'likes' => [], 'report' => null];
    }

    /** Customers from a rotating offset, so each post draws a different crowd. */
    private function crowd(int $offset, int $count, array $exclude): Collection
    {
        $offset %= $this->customers->count();

        return $this->customers->slice($offset)->concat($this->customers->take($offset))
            ->reject(fn (User $user) => in_array($user->id, $exclude, true))
            ->take($count)
            ->values();
    }

    private function spammer(): User
    {
        return $this->customers->get(22 % $this->customers->count());
    }

    private function customer(?int $id): ?User
    {
        return $id ? $this->customers->firstWhere('id', $id) : null;
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', '.').' ₫';
    }

    private function cap(): Carbon
    {
        return $this->now->copy()->subMinute();
    }

    private function local(Carbon $at): Carbon
    {
        return $at->copy()->setTimezone(config('app.timezone'));
    }
}
