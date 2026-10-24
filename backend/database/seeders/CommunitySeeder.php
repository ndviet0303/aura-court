<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Court;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReport;
use App\Models\ModerationRecord;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Review;
use App\Models\Tournament;
use App\Models\TournamentEntry;
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
 * The platform-wide feed (D12) with share and matchmaking posts, a pending and a resolved report, owner replies to
 * reviews, and the in-app notifications the app would have stored for the seeded bookings and comments (tournament
 * notices come from the outbox rows TournamentSeeder's actions wrote).
 * Everything is read back from what earlier seeders created. Notification rows are inserted in the database-channel
 * shape, so nothing is sent.
 */
class CommunitySeeder extends Seeder
{
    use KeepsWakingHours;

    private const REPLIES = [
        'Cảm ơn bạn đã ghé sân và góp ý. Cơ sở sẽ tiếp tục giữ sân sạch và đèn đủ sáng, hẹn gặp lại bạn!',
        'Cảm ơn bạn! Bên mình đã ghi nhận phần nhà vệ sinh và sẽ bố trí dọn thường xuyên hơn vào giờ cao điểm.',
        'Rất vui vì bạn hài lòng. Lần tới đặt sớm để giữ khung giờ đẹp nhé!',
    ];

    private Carbon $now;

    private Organization $organization;

    private User $owner;

    private User $primary;

    private User $admin;

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
        $admin = User::where('email', 'admin@auracourt.vn')->first();
        $emails = array_merge(['user@example.com'], array_map(fn (int $n) => sprintf('khach%02d@demo.auracourt.vn', $n), range(1, 24)));
        $users = User::whereIn('email', $emails)->get()->keyBy('email');
        $this->customers = collect($emails)->map(fn (string $email) => $users->get($email))->filter()->values();
        if (! $organization || ! $owner || ! $admin || $this->customers->count() < 12) {
            return;
        }
        [$this->organization, $this->owner, $this->admin, $this->primary] = [$organization, $owner, $admin, $this->customers->first()];

        $this->tournamentPost($organization, $owner, true);
        $hoangGia = Organization::where('slug', 'hoang-gia-sports')->first();
        $hoangGiaOwner = User::where('email', 'owner.hoanggia@demo.auracourt.vn')->first();
        if ($hoangGia && $hoangGiaOwner) {
            $this->tournamentPost($hoangGia, $hoangGiaOwner, false);
        }
        $this->primaryPosts();
        $this->memberPosts();
        $this->persistPosts();
        $this->reviewReplies();

        $this->customerNotifications();
        $this->ownerNotifications();
        $this->persistNotifications();
    }

    /** Organizer announcement for the tenant's next public tournament whose registration is still running. */
    private function tournamentPost(Organization $organization, User $author, bool $withComments): void
    {
        $tournament = Tournament::with('venue')
            ->where('organization_id', $organization->id)
            ->where('type', Tournament::TYPE_PUBLIC)
            ->where('status', Tournament::STATUS_OPEN)
            ->where('registration_deadline', '>', $this->now->copy()->utc())
            ->orderBy('starts_at')
            ->orderBy('id')
            ->first();
        if (! $tournament) {
            return;
        }

        $tz = config('app.timezone');
        $days = collect([$tournament->starts_at, $tournament->ends_at])->filter()
            ->map(fn (Carbon $day) => $day->copy()->setTimezone($tz)->format('d/m'))->unique()->implode(' - ');
        $at = $this->now->copy()->subDays(4)->setTime(9, 0)->max($tournament->published_at ?? $tournament->created_at);
        $content = sprintf(
            '%s đang mở đăng ký đến hết %s. Thi đấu %s tại %s, lệ phí %sđ mỗi đơn vị. Đăng ký trong mục Giải đấu nhé, số suất có hạn!',
            $tournament->name, $tournament->registration_deadline->copy()->setTimezone($tz)->format('d/m'), $days,
            $tournament->venue->getTranslation('name', 'vi'), number_format($tournament->fee, 0, ',', '.')
        );

        $comments = [];
        if ($withComments) {
            $entrant = TournamentEntry::where('tournament_id', $tournament->id)->where('status', TournamentEntry::STATUS_PAID)->orderBy('paid_at')->orderBy('id')->get()
                ->first(fn (TournamentEntry $e) => $e->user_id !== $this->primary->id && $this->customer((int) $e->user_id));
            if ($entrant) {
                $comments[] = $this->comment($this->customer($entrant->user_id), "{$entrant->name} đã đóng lệ phí rồi, hẹn gặp các đội ở giải nhé!",
                    $at->copy()->addMinutes(90)->max($entrant->paid_at->copy()->addMinutes(5)));
            }
            $asker = $this->customers->get(7);
            $comments[] = $this->comment($asker, 'Giải có giới hạn độ tuổi cầu thủ không ạ?', $at->copy()->addHours(3));
            $comments[] = $this->comment($author, 'Bạn xem điều kiện tham gia và thể lệ trong mục Giải đấu nhé, cần hỗ trợ cứ nhắn CLB.',
                $at->copy()->addHours(3)->addMinutes(40), ['likes' => [$asker]]);
        }

        $this->addPost($author, $content, $at, $comments);
    }

    /** user@example.com looks for players for a booked court (one reply is rude and reported) and posts their entry. */
    private function primaryPosts(): void
    {
        $primary = $this->primary;
        $venue = Venue::where('organization_id', $this->organization->id)->orderBy('id')->first();
        if ($venue) {
            $playAt = $this->now->copy()->addDays(2)->setTime(19, 0);
            $at = $this->now->copy()->subDay()->setTime(20, 15);
            $rude = $this->customers->reverse()->first(fn (User $user) => ! in_array($user->id, [$primary->id, $this->spammer()->id], true));
            $comments = [
                $this->comment($this->customers->get(3), 'Mình đánh đôi nam được, tối đó gặp nhé!', $at->copy()->addMinutes(20)),
                $this->comment($this->customers->get(8), 'Cho mình hỏi trình trung bình là khoảng nào bạn?', $at->copy()->addMinutes(55)),
                $this->comment($rude, 'Đánh dở mà cũng đòi kén trình, về tập thêm rồi hẵng rủ kèo.', $at->copy()->addMinutes(80), [
                    'report' => ['reporter' => $primary, 'reason' => 'Bình luận chê bai, thiếu tôn trọng người chơi khác.'],
                ]),
            ];
            $this->addPost($primary, sprintf(
                'Tối %s mình đặt sân ở %s, %s-%s, còn thiếu 2 bạn đánh đôi trình trung bình. Ai rảnh nhắn mình nhé!',
                $playAt->format('d/m'), $venue->getTranslation('name', 'vi'), $playAt->format('H:i'), $playAt->copy()->addHours(2)->format('H:i')
            ), $at, $comments, [
                'type' => FeedPost::TYPE_MATCHMAKING,
                'sport' => 'badminton',
                'venue_id' => $venue->id,
                'court_code' => Court::where('venue_id', $venue->id)->orderBy('code')->value('code'),
                'play_at' => $playAt,
            ]);
        }

        $entry = TournamentEntry::with('tournament')->where('user_id', $primary->id)->orderBy('created_at')->orderBy('id')->get()
            ->first(fn (TournamentEntry $e) => $e->tournament?->status === Tournament::STATUS_OPEN
                && $e->tournament->organization_id === $this->organization->id && $e->created_at->lte($this->now));
        if ($entry) {
            $at = $entry->created_at->copy()->addMinutes(20);
            $mates = TournamentEntry::where('tournament_id', $entry->tournament_id)->where('id', '!=', $entry->id)
                ->whereIn('status', [TournamentEntry::STATUS_APPROVED, TournamentEntry::STATUS_PAID])
                ->orderBy('created_at')->orderBy('id')->get()
                ->filter(fn (TournamentEntry $e) => $this->customer((int) $e->user_id))
                ->take(2)->values();
            $comments = $mates->map(fn (TournamentEntry $e, int $i) => $this->comment(
                $this->customer($e->user_id),
                $i === 0 ? "{$e->name} cũng đăng ký rồi, trước giải đá giao hữu một trận không?" : "{$e->name} nhận lời giao hữu nhé, chốt sân thì nhắn mình.",
                $at->copy()->addMinutes(60 * ($i + 1))->max($e->created_at->copy()->addMinutes(10))
            ))->all();
            $this->addPost($primary, sprintf('Đội %s vừa đăng ký %s! Đội nào cùng giải thì giao lưu trước cho quen sân nhé.', $entry->name, $entry->tournament->name), $at, $comments);
        }
    }

    /** Everyday member posts across sports, two of them matchmaking, and one spam post the platform admin hid after a report. */
    private function memberPosts(): void
    {
        $c = fn (int $i) => $this->customers->get($i % $this->customers->count());
        $saigonVenue = Venue::where('slug', 'aura-central-saigon')->first();
        $saigon = $saigonVenue?->getTranslation('name', 'vi') ?? 'Aura Sài Gòn';
        $thursday = $this->now->copy()->next(Carbon::THURSDAY)->setTime(19, 0);
        $matchmaking = [
            2 => ['type' => FeedPost::TYPE_MATCHMAKING, 'sport' => 'football_5v5', 'venue_id' => $saigonVenue?->id, 'address' => $saigonVenue ? null : 'Quận 1, TP. Hồ Chí Minh', 'play_at' => $thursday],
            3 => ['type' => FeedPost::TYPE_MATCHMAKING, 'sport' => 'basketball', 'address' => 'Landmark 72, Phạm Hùng, Nam Từ Liêm, Hà Nội', 'play_at' => $this->now->copy()->addDays(3)->setTime(19, 30)],
        ];
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
        foreach ($posts as $k => [$author, $days, $time, $content, $replies]) {
            $at = $this->now->copy()->subDays($days)->setTimeFromTimeString($time);
            $this->addPost($c($author), $content, $at, array_map(
                fn (array $reply, int $i) => $this->comment($c($reply[0]), $reply[1], $at->copy()->addMinutes(45 * ($i + 1))),
                $replies, array_keys($replies)
            ), $matchmaking[$k] ?? []);
        }

        $this->addPost($this->spammer(), 'THANH LÝ VỢT, GIÀY XÁCH TAY GIÁ RẺ!!! Nhắn tin riêng để nhận mã giảm thêm 50%, số lượng có hạn.',
            $this->now->copy()->subDays(2)->setTime(23, 10), [], ['hide' => [
                'reporter' => $c(11),
                'report_reason' => 'Spam quảng cáo bán hàng trên bảng tin.',
                'reason' => 'Quảng cáo bán hàng không liên quan đến thể thao.',
            ]]);
    }

    /** Inserts posts oldest first (the feed lists newest first), then their comments and reports. */
    private function persistPosts(): void
    {
        usort($this->posts, fn (array $a, array $b) => $a['at'] <=> $b['at']);

        foreach ($this->posts as $spec) {
            $at = $spec['at'];
            $post = FeedPost::firstOrNew(['user_id' => $spec['author']->id, 'content' => $spec['content']]);
            $post->forceFill([
                'type' => $spec['type'] ?? FeedPost::TYPE_SHARE,
                'sport' => $spec['sport'] ?? null,
                'venue_id' => $spec['venue_id'] ?? null,
                'address' => $spec['address'] ?? null,
                'court_code' => $spec['court_code'] ?? null,
                'play_at' => $spec['play_at'] ?? null,
                'created_at' => $at,
                'updated_at' => $at,
            ])->save();

            if ($spec['hide']) {
                $this->hide($post, $spec['hide']);

                continue;
            }

            foreach ($spec['comments'] as $reply) {
                if (! $reply['user']) {
                    continue;
                }
                $commentAt = $this->awake($reply['at']->copy()->min($this->cap())->max($at), $at);
                $comment = FeedComment::firstOrNew(['feed_post_id' => $post->id, 'user_id' => $reply['user']->id, 'content' => $reply['content']]);
                $comment->forceFill(['created_at' => $commentAt, 'updated_at' => $commentAt])->save();

                // CreateComment notifies the post's author (D11).
                if ($spec['author']->id === $this->primary->id && $reply['user']->id !== $this->primary->id) {
                    $this->addNotification($this->primary, "community.comment.{$comment->id}", OutboxNotification::class, [
                        'event' => 'community.comment',
                        'title' => 'Bình luận mới',
                        'body' => "{$reply['user']->name} đã bình luận: ".mb_strimwidth($reply['content'], 0, 80, '...'),
                        'action_url' => "/community/posts/{$post->id}",
                    ], $commentAt, null);
                }

                if ($reply['report']) {
                    $reportedAt = $this->awake($commentAt->copy()->addMinutes(25)->min($this->cap())->max($commentAt), $commentAt);
                    FeedReport::firstOrNew([
                        'reporter_id' => $reply['report']['reporter']->id,
                        'reportable_type' => FeedComment::class,
                        'reportable_id' => $comment->id,
                    ])->forceFill([
                        'reason' => $reply['report']['reason'],
                        'status' => FeedReport::STATUS_PENDING,
                        'created_at' => $reportedAt,
                        'updated_at' => $reportedAt,
                    ])->save();
                }
            }
        }
    }

    /** Same records ModerateReport writes when the platform admin hides a reported post (D14). */
    private function hide(FeedPost $post, array $hide): void
    {
        $reportedAt = $this->awake($post->created_at->copy()->addMinutes(35)->min($this->cap()), $post->created_at);
        $hiddenAt = $this->awake($post->created_at->copy()->addMinutes(110)->min($this->cap())->max($reportedAt), $reportedAt);

        $post->forceFill([
            'is_hidden' => true,
            'hidden_at' => $hiddenAt,
            'hidden_by' => $this->admin->id,
            'hidden_reason' => $hide['reason'],
            'updated_at' => $hiddenAt,
        ])->save();

        $report = FeedReport::firstOrNew([
            'reporter_id' => $hide['reporter']->id,
            'reportable_type' => FeedPost::class,
            'reportable_id' => $post->id,
        ]);
        $report->forceFill([
            'reason' => $hide['report_reason'],
            'status' => FeedReport::STATUS_RESOLVED,
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => $hiddenAt,
            'action_taken' => FeedReport::ACTION_HIDDEN,
            'created_at' => $reportedAt,
            'updated_at' => $hiddenAt,
        ])->save();

        // Moderation records are immutable, so only create the missing one.
        $record = ModerationRecord::firstOrNew(['target_type' => FeedPost::class, 'target_id' => $post->id, 'action' => 'hide']);
        if (! $record->exists) {
            $record->forceFill([
                'actor_id' => $this->admin->id,
                'reason' => $hide['reason'],
                'metadata' => ['report_id' => $report->id],
                'created_at' => $hiddenAt,
            ])->save();
        }
    }

    /** FR-117: the owner answers the club's three latest reviews a few hours after each was posted. */
    private function reviewReplies(): void
    {
        $venueIds = Venue::where('organization_id', $this->organization->id)->pluck('id');
        $reviews = Review::whereIn('venue_id', $venueIds)->where('is_published', true)->where('created_at', '<=', $this->cap())
            ->orderByDesc('created_at')->orderByDesc('id')->take(count(self::REPLIES))->get();
        foreach ($reviews->values() as $i => $review) {
            $repliedAt = $this->awake($review->created_at->copy()->addHours(3)->min($this->cap()), $review->created_at);
            $review->forceFill(['reply_body' => self::REPLIES[$i], 'replied_by' => $this->owner->id, 'replied_at' => $repliedAt])->save();
        }
    }

    /** What the app stored for user@example.com: checkout and expiry notices. */
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
                'body' => "Booking {$booking->code} tại {$booking->court->code} đã được thanh toán thành công.",
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
    }

    /** What the app stored for owner@: staff who joined the club. */
    private function ownerNotifications(): void
    {
        $staff = OrganizationMember::with('user')
            ->where('organization_id', $this->organization->id)
            ->where('status', 'active')
            ->whereIn('role', [OrganizationMember::ROLE_STAFF])
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

    /** @param list<array<string, mixed>> $comments */
    private function addPost(User $author, string $content, Carbon $at, array $comments = [], array $extra = []): void
    {
        $this->posts[] = $extra + [
            'author' => $author,
            'content' => $content,
            'at' => $at->copy()->min($this->cap()),
            'comments' => $comments,
            'hide' => null,
        ];
    }

    private function comment(?User $user, string $content, Carbon $at, array $extra = []): array
    {
        return $extra + ['user' => $user, 'content' => $content, 'at' => $at, 'report' => null];
    }

    private function spammer(): User
    {
        return $this->customers->get(22 % $this->customers->count());
    }

    private function customer(?int $id): ?User
    {
        return $id ? $this->customers->firstWhere('id', $id) : null;
    }

    private function cap(): Carbon
    {
        return $this->now->copy()->subMinute();
    }
}
