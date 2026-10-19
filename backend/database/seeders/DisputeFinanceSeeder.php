<?php

namespace Database\Seeders;

use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Models\AuditLog;
use App\Models\CourtPriceSlot;
use App\Models\DisputeCase;
use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\TenantMembership;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Concerns\KeepsWakingHours;
use Illuminate\Database\Seeder;

/**
 * Disputes on real past paid reservations and a few audit log entries on real records.
 * The money trail is the owner receipts and journals the seeders write when customers pay venue owners directly;
 * there are no platform payments, refunds, settlements or payouts to derive anything else from.
 */
class DisputeFinanceSeeder extends Seeder
{
    use KeepsWakingHours;

    // Documentation-range addresses (RFC 5737).
    private const ADMIN_IP = '203.0.113.15';

    private const ADMIN_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Safari/605.1.15';

    private Carbon $now;

    private ?User $arbitrator = null;

    public function run(): void
    {
        $this->now = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'))->startOfMinute();
        $this->arbitrator = User::where('email', 'admin@auracourt.vn')->first();

        $this->seedDisputes();
        $this->seedAuditTrail();
    }

    /**
     * Disputes filed on real past paid reservations, shaped like AdminDisputeController::store and
     * ArbitrateDispute write them: complainant = the reservation's customer, respondent = the
     * organization's active non-admin owner.
     */
    private function seedDisputes(): void
    {
        $customerIds = User::whereIn('email', $this->customerEmails())->pluck('id');
        // The app accepts a dispute until 30 days after the reservation ended.
        $candidates = Reservation::query()
            ->with(['court.venue.organization', 'user', 'slots', 'legacyMapping'])
            ->whereIn('user_id', $customerIds)
            ->whereHas('legacyMapping', fn ($q) => $q->whereIn('legacy_status', ['confirmed', 'completed']))
            ->orderBy('id')
            ->get()
            ->filter(fn (Reservation $r): bool => $r->ends_at !== null && $r->ends_at->lt($this->now) && $r->ends_at->gt($this->now->copy()->subDays(29)))
            ->sortBy(fn (Reservation $r): string => sprintf('%012d-%08d', $this->now->getTimestamp() - $r->ends_at->getTimestamp(), $r->id))
            ->values();

        $usedReservations = [];
        $usedUsers = [];
        foreach ($this->disputeSpecs() as $spec) {
            $reservation = null;
            foreach ([[$spec['age'], $spec['org'] ?? null], [$spec['age'], null], [[0, 29], null]] as [[$minDays, $maxDays], $orgSlug]) {
                $reservation = $candidates->first(fn (Reservation $r): bool => ! in_array($r->id, $usedReservations, true)
                    && ! in_array($r->user_id, $usedUsers, true)
                    && ($orgSlug === null || $r->court->venue->organization->slug === $orgSlug)
                    && $r->ends_at->lte($this->now->copy()->subDays($minDays))
                    && $r->ends_at->gte($this->now->copy()->subDays($maxDays)));
                if ($reservation) {
                    break;
                }
            }
            if (! $reservation) {
                $this->command?->warn("DisputeFinanceSeeder: no past paid reservation left for {$spec['code']}, skipped.");

                continue;
            }
            $usedReservations[] = $reservation->id;
            $usedUsers[] = $reservation->user_id;
            $this->seedDispute($spec, $reservation);
        }
    }

    private function seedDispute(array $spec, Reservation $reservation): void
    {
        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $court = $reservation->court;
        $venue = $court->venue;
        $org = $venue->organization;
        $customer = $reservation->user;
        $respondentId = app(DisputeRepositoryInterface::class)->findRespondentIdForOrganization($org->id);
        $respondent = $respondentId ? User::find($respondentId) : null;
        $operator = $this->venueOperator($org->id, $venue->id) ?? $respondent;

        $startsAt = $reservation->starts_at->copy()->setTimezone($tz);
        $endsAt = $reservation->ends_at->copy()->setTimezone($tz);
        $total = (int) $reservation->total_amount;
        $decision = $spec['decision'] ?? null;

        // Every step happens between the end of play and now, in order, whatever the reservation's age.
        $filedAt = $endsAt->copy()->addSeconds((int) min($spec['file_after_hours'] * 3600, ($this->now->getTimestamp() - $endsAt->getTimestamp()) / 2))->startOfMinute();
        $filedAt = $this->awake($filedAt, $endsAt, $endsAt->copy()->addSeconds(intdiv($this->now->getTimestamp() - $endsAt->getTimestamp(), 2)));
        $arbitratedAt = match ($decision) {
            'resolve' => $this->awake($this->now->copy()->subDay(), $filedAt),
            'dismiss' => $this->awake($filedAt->copy()->addSeconds((int) (($this->now->getTimestamp() - $filedAt->getTimestamp()) * 0.6))->startOfMinute(), $filedAt),
            default => null,
        };
        $span = $arbitratedAt ?? $this->now;
        $at = fn (float $fraction): Carbon => $this->awake($filedAt->copy()->addSeconds((int) (($span->getTimestamp() - $filedAt->getTimestamp()) * $fraction))->startOfMinute(), $filedAt, $span);

        $amountDisputed = match ($spec['amount']) {
            'total' => $total,
            'quarter_hour' => (int) (round((int) $reservation->slots->first()->unit_price / 4 / 5000) * 5000),
            default => (int) (floor($total * $spec['amount'] / 1000) * 1000),
        };
        $membershipPlan = $this->membershipPlanName($org->id, $customer->id, $filedAt);

        $vars = [
            '{court}' => $court->getTranslation('name', 'vi'),
            '{venue}' => $venue->getTranslation('name', 'vi'),
            '{date}' => $startsAt->format('d/m'),
            '{slot}' => $startsAt->format('H:i').'-'.$endsAt->format('H:i'),
            '{customer}' => $customer->name,
            '{operator}' => $operator?->name ?? $org->name,
        ];
        $text = fn (string $value): string => strtr($value, $vars);
        $actors = ['customer' => $customer, 'operator' => $operator, 'arbitrator' => $this->arbitrator];

        $dispute = DisputeCase::firstOrNew(['dispute_code' => $spec['code']]);
        $dispute->forceFill([
            'organization_id' => $org->id,
            'venue_id' => $venue->id,
            'booking_id' => $reservation->legacyMapping->value('legacy_booking_id'),
            'reservation_id' => $reservation->id,
            'reference_type' => 'reservation',
            'reference_id' => $reservation->id,
            'court_name' => $vars['{court}'],
            'facility_name' => $vars['{venue}'],
            'facility_operator_name' => $org->name,
            'dispute_type' => $spec['type'],
            'severity' => $spec['severity'],
            'title' => $text($spec['title']),
            'summary' => $text($spec['summary']),
            'status' => $spec['status'],
            // Open cases keep a day-scale SLA so nothing turns overdue during a demo.
            'sla_deadline' => $decision ? $filedAt->copy()->addDays(3) : $this->now->copy()->addDays($spec['sla_days'])->startOfHour(),
            'amount_disputed' => $amountDisputed,
            'currency' => 'VND',
            'complainant_id' => $customer->id,
            'complainant_name' => $customer->name,
            'complainant_tier' => $membershipPlan ?? 'Khách hàng',
            'complainant_reputation_score' => 100,
            'complainant_phone' => $customer->phone,
            'respondent_id' => $respondentId,
            'respondent_name' => $org->name,
            'respondent_tier' => 'Đối tác Aura',
            'respondent_reputation_score' => 100,
            'respondent_phone' => $respondent?->phone,
            'requested_evidence' => $spec['requested_evidence'] ?? null,
            'court_map_context' => $this->courtMap($reservation, $spec),
            'ai_analysis' => [
                'fault_rate_facility' => $spec['fault_facility'],
                'fault_rate_user' => 100 - $spec['fault_facility'],
                'root_cause' => $text($spec['root_cause']),
                'recommendation_summary' => $text($spec['recommendation']),
            ],
            'decision_history' => [],
            'decision_version' => 1,
            'created_at' => $filedAt,
            'updated_at' => $filedAt,
        ])->save();

        $events = [];
        foreach ($spec['timeline'] as [$when, $code, $title, $description, $type, $actor, $extra]) {
            $time = $when === 'arbitration' ? $arbitratedAt : ($when === 0.0 ? $filedAt : $at($when));
            $actorUser = $actor ? $actors[$actor] : null;
            $extra = $code === 'dispute_arbitrated' ? ['decision' => $decision, 'version' => 1] : $extra;
            // App keys (event, actor, timestamp) plus the display keys the admin workbench renders.
            $events[] = [
                'event' => $code,
                'actor_id' => $actorUser?->id,
                'actor_name' => $actorUser?->name,
                ...$extra,
                'time' => $time->format('d/m H:i'),
                'title' => $title,
                'description' => $text($description),
                'type' => $type,
                'timestamp' => $time->toIso8601String(),
            ];
        }
        $chat = [];
        foreach ($spec['chat'] as [$when, $role, $message]) {
            $sender = $role === 'complainant' ? $customer->name : ($operator?->name ?? $org->name);
            $chat[] = [
                'sender' => $sender,
                'role' => $role,
                'avatar' => $this->initials($sender),
                'message' => $text($message),
                'time' => ($when === 0.0 ? $filedAt : $at($when))->format('H:i'),
            ];
        }
        $evidence = [];
        foreach ($spec['evidence'] as [$when, $by, $explanation, $items]) {
            $evidence[] = [
                'submitted_by' => $actors[$by]?->id,
                'submitted_by_name' => $actors[$by]?->name,
                'is_private' => false,
                'explanation' => $text($explanation),
                'items' => $items,
                'submitted_at' => ($when === 0.0 ? $filedAt : $at($when))->toIso8601String(),
            ];
        }

        $dispute->forceFill([
            'timeline_events' => $events,
            'chat_messages' => $chat,
            'evidence' => $evidence,
            'resolution_decision' => $decision,
            'refund_method' => $spec['refund_method'] ?? null,
            'refund_amount' => null,
            'compensation_type' => $spec['compensation_type'] ?? null,
            'compensation_value' => $spec['compensation_value'] ?? null,
            'penalty_notes' => isset($spec['penalty_notes']) ? $text($spec['penalty_notes']) : null,
            'penalty_points' => $spec['penalty_points'] ?? null,
            'arbitrated_by' => $decision ? $this->arbitrator?->id : null,
            'arbitrated_at' => $arbitratedAt,
            'arbitrator_notes' => $decision ? $text($spec['arbitrator_notes']) : null,
            'refund_obligation_id' => null,
            'updated_at' => Carbon::parse(end($events)['timestamp']),
        ])->save();

        if ($decision && $this->arbitrator) {
            $this->audit($this->arbitrator, "dispute.{$decision}", $dispute, $arbitratedAt, [
                'dispute_code' => $dispute->dispute_code,
                'reservation_code' => $reservation->code,
                'old_values' => ['status' => $spec['status_before_decision']],
                'new_values' => [
                    'status' => $spec['status'],
                    'decision' => $decision,
                    'refund_amount' => null,
                    'refund_method' => $dispute->refund_method,
                ],
                'reason' => $dispute->arbitrator_notes,
            ]);
        }
    }

    /** The staff member scoped to this venue, else one covering every venue of the organization. */
    private function venueOperator(int $organizationId, int $venueId): ?User
    {
        return OrganizationMember::with('user')
            ->where('organization_id', $organizationId)
            ->where('role', OrganizationMember::ROLE_STAFF)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->filter(fn (OrganizationMember $m): bool => $m->venue_ids === [] || in_array($venueId, $m->venue_ids, true))
            ->sortBy(fn (OrganizationMember $m): int => $m->venue_ids === [] ? 1 : 0)
            ->first()?->user;
    }

    /** @return array<int, array{name: string, status: string, label: string}> */
    private function courtMap(Reservation $reservation, array $spec): array
    {
        return $reservation->court->venue->courts()
            ->where('sport_type', $reservation->court->sport_type)
            ->orderBy('id')
            ->get()
            ->sortBy(fn ($court): int => $court->id === $reservation->court_id ? 0 : 1)
            ->take(3)
            ->sortBy('id')
            ->map(fn ($court): array => [
                'name' => $court->getTranslation('name', 'vi'),
                'status' => $court->id === $reservation->court_id ? $spec['map_status'] : 'available',
                'label' => $court->id === $reservation->court_id ? $spec['map_label'] : 'Hoạt động bình thường',
            ])
            ->values()
            ->all();
    }

    /**
     * Timeline rows are [when, event, title, description, UI type, actor, extra app keys]; `when` is a
     * fraction of the time between filing and the decision (or now), or 'arbitration'.
     *
     * @return array<int, array<string, mixed>>
     */
    private function disputeSpecs(): array
    {
        return [
            [
                'code' => 'DSP-7QK2M9XA4B',
                'status' => 'pending',
                'type' => 'court_quality',
                'severity' => 'medium',
                'age' => [0, 4],
                'file_after_hours' => 3,
                'sla_days' => 3,
                'amount' => 0.3,
                'title' => 'Đèn chiếu sáng chập chờn tại {court}',
                'summary' => 'Khách phản ánh dàn đèn LED giữa sân nhấp nháy liên tục trong ca {slot} ngày {date}, khó quan sát khi thi đấu. Khách đề nghị hoàn 30% tiền sân.',
                'map_status' => 'maintenance',
                'map_label' => 'Chờ kiểm tra đèn',
                'fault_facility' => 70,
                'root_cause' => 'Bộ nguồn đèn LED khu vực giữa {court} có dấu hiệu xuống cấp; chưa có biên bản bảo trì gần đây.',
                'recommendation' => 'Yêu cầu {venue} gửi nhật ký bảo trì đèn. Nếu xác nhận lỗi thiết bị, ghi nhận lỗi cho cơ sở và đề nghị cơ sở trực tiếp hỗ trợ khách.',
                'timeline' => [
                    [0.0, 'dispute_filed', 'Khách gửi khiếu nại', '{customer} gửi khiếu nại kèm ảnh và video dàn đèn {court}.', 'primary', 'customer', ['status' => 'pending']],
                ],
                'chat' => [
                    [0.0, 'complainant', 'Đèn giữa sân nhấp nháy suốt ca {slot}, cả nhóm phải dừng nhiều lần. Mong cơ sở xem lại và hỗ trợ một phần tiền sân.'],
                ],
                'evidence' => [
                    [0.0, 'customer', 'Ảnh và video quay lúc đèn nhấp nháy trong ca {slot}.', [
                        ['type' => 'photo', 'name' => 'den-san-nhap-nhay.jpg'],
                        ['type' => 'video', 'name' => 'clip-den-15-giay.mp4'],
                    ]],
                ],
            ],
            [
                'code' => 'DSP-3HX8RT2KQM',
                'status' => 'investigating',
                'type' => 'no_show_conflict',
                'severity' => 'high',
                'age' => [1, 7],
                'file_after_hours' => 2,
                'sla_days' => 2,
                'amount' => 'total',
                // Filed without evidence, so intake asked for it (awaiting_evidence) before the investigation.
                'requested_evidence' => ['photos_or_video', 'detailed_statement'],
                'title' => 'Đến sân nhưng {court} đang có nhóm khác sử dụng',
                'summary' => 'Khách đến đúng giờ {slot} ngày {date} nhưng sân đang có nhóm khách vãng lai do quầy xếp nhầm lịch. Khách chờ 25 phút mới được vào sân và yêu cầu hoàn toàn bộ tiền sân.',
                'map_status' => 'conflict',
                'map_label' => 'Trùng lịch',
                'fault_facility' => 80,
                'root_cause' => 'Lễ tân nhận khách vãng lai vào {court} mà không kiểm tra lịch đặt trực tuyến của khung giờ {slot}.',
                'recommendation' => 'Đối chiếu nhật ký check-in tại quầy với lịch đặt; nếu xác nhận lỗi xếp sân, đề nghị cơ sở trực tiếp hỗ trợ khách tương ứng thời gian chờ và nhắc nhở ca trực.',
                'timeline' => [
                    [0.0, 'dispute_filed', 'Khách gửi khiếu nại', '{customer} báo {court} đang có nhóm khác sử dụng khi đến nhận sân. Hệ thống yêu cầu bổ sung ảnh và tường trình.', 'primary', 'customer', ['status' => 'awaiting_evidence']],
                    [0.3, 'evidence_submitted', 'Khách bổ sung chứng cứ', 'Ảnh chụp sân lúc đến và mã QR nhận sân; hồ sơ chuyển sang điều tra.', 'primary', 'customer', ['is_private' => false, 'has_explanation' => true]],
                    [0.7, 'evidence_submitted', 'Cơ sở gửi giải trình', '{operator} gửi nhật ký check-in tại quầy ca {slot}.', 'warning', 'operator', ['is_private' => false, 'has_explanation' => true]],
                ],
                'chat' => [
                    [0.0, 'complainant', 'Tôi đến đúng giờ nhưng sân đã có nhóm khác, quầy bảo chờ họ đánh xong.'],
                    [0.7, 'facility', 'Ca trực xếp nhầm khách vãng lai vào sân. Cơ sở xin lỗi và chờ kết luận của Aura.'],
                ],
                'evidence' => [
                    [0.3, 'customer', 'Ảnh sân lúc đến và mã QR nhận sân.', [
                        ['type' => 'photo', 'name' => 'san-co-nguoi-choi.jpg'],
                        ['type' => 'screenshot', 'name' => 'ma-qr-nhan-san.png'],
                    ]],
                    [0.7, 'operator', 'Nhật ký check-in tại quầy ca {slot}.', [
                        ['type' => 'document', 'name' => 'nhat-ky-check-in.pdf'],
                    ]],
                ],
            ],
            [
                'code' => 'DSP-9TW4ZC7NHE',
                'status' => 'awaiting_response',
                'type' => 'payment_issue',
                'severity' => 'medium',
                'org' => 'hoang-gia-sports',
                'age' => [1, 10],
                'file_after_hours' => 5,
                'sla_days' => 2,
                'amount' => 'quarter_hour',
                'title' => 'Bị thu phụ phí 15 phút quá giờ tại {court}',
                'summary' => 'Khách cho biết nhóm rời sân đúng {slot} ngày {date} nhưng vẫn bị quầy thu phụ phí 15 phút. Aura đã yêu cầu cơ sở giải trình.',
                'map_status' => 'available',
                'map_label' => 'Hoạt động bình thường',
                'fault_facility' => 50,
                'root_cause' => 'Chưa xác định: cần đối chiếu giờ check-out thực tế với lịch đặt sân.',
                'recommendation' => 'Chờ {venue} gửi giờ check-out và hóa đơn phụ phí trước khi phán quyết.',
                'timeline' => [
                    [0.0, 'dispute_filed', 'Khách gửi khiếu nại', '{customer} khiếu nại khoản phụ phí 15 phút quá giờ.', 'primary', 'customer', ['status' => 'pending']],
                    [0.4, 'response_requested', 'Yêu cầu cơ sở phản hồi', 'Aura đề nghị {venue} gửi giờ check-out và hóa đơn phụ phí.', 'warning', 'arbitrator', []],
                ],
                'chat' => [
                    [0.0, 'complainant', 'Nhóm tôi rời sân đúng giờ nhưng vẫn bị thu thêm 15 phút. Nhờ Aura kiểm tra giúp.'],
                ],
                'evidence' => [
                    [0.0, 'customer', 'Ảnh hóa đơn phụ phí tại quầy.', [
                        ['type' => 'photo', 'name' => 'hoa-don-phu-phi.jpg'],
                    ]],
                ],
            ],
            [
                'code' => 'DSP-5MB8VJ3RDP',
                'status' => 'resolved',
                'status_before_decision' => 'pending',
                'decision' => 'resolve',
                'refund_method' => 'none',
                'compensation_type' => 'owner_direct',
                'compensation_value' => 'Cơ sở trực tiếp liên hệ hỗ trợ khách',
                'penalty_notes' => 'Nhắc nhở {venue} kiểm tra máy phát dự phòng định kỳ hằng tháng.',
                'penalty_points' => 1,
                'type' => 'court_quality',
                'severity' => 'high',
                'age' => [5, 25],
                'file_after_hours' => 2,
                'amount' => 'total',
                'title' => 'Mất điện 40 phút trong ca chơi tại {court}',
                'summary' => 'Cụm sân mất điện khoảng 40 phút trong ca {slot} ngày {date}. Khách đề nghị cơ sở bù phần thời gian không sử dụng được.',
                'map_status' => 'available',
                'map_label' => 'Đã khắc phục',
                'fault_facility' => 100,
                'root_cause' => 'Sự cố aptomat tổng tại {venue}, máy phát dự phòng khởi động chậm.',
                'recommendation' => 'Ghi nhận lỗi cho cơ sở; yêu cầu cơ sở kiểm tra định kỳ máy phát dự phòng và trực tiếp hỗ trợ khách.',
                'arbitrator_notes' => 'Nhật ký vận hành của {venue} xác nhận mất điện 40 phút. Aura ghi nhận lỗi cho cơ sở và trừ 1 điểm uy tín; tiền sân khách đã chuyển thẳng cho chủ sân nên cơ sở trực tiếp liên hệ hỗ trợ khách.',
                'timeline' => [
                    [0.0, 'dispute_filed', 'Khách gửi khiếu nại', '{customer} báo mất điện trong ca {slot}.', 'primary', 'customer', ['status' => 'pending']],
                    [0.4, 'evidence_submitted', 'Cơ sở xác nhận sự cố', '{operator} gửi nhật ký vận hành xác nhận mất điện 40 phút.', 'warning', 'operator', ['is_private' => false, 'has_explanation' => true]],
                    ['arbitration', 'dispute_arbitrated', 'Chấp nhận khiếu nại', 'Aura ghi nhận lỗi của {venue}; cơ sở trực tiếp liên hệ hỗ trợ khách.', 'success', 'arbitrator', []],
                ],
                'chat' => [
                    [0.0, 'complainant', 'Sân mất điện gần 40 phút, cả nhóm phải ngồi chờ. Mong cơ sở bù lại phần thời gian này.'],
                    [0.4, 'facility', 'Cơ sở xác nhận sự cố aptomat tổng và sẽ liên hệ hỗ trợ khách theo phán quyết.'],
                ],
                'evidence' => [
                    [0.0, 'customer', 'Ảnh sân tối đèn trong ca {slot}.', [
                        ['type' => 'photo', 'name' => 'san-mat-dien.jpg'],
                    ]],
                    [0.4, 'operator', 'Nhật ký vận hành và ảnh tủ điện sau sự cố.', [
                        ['type' => 'document', 'name' => 'nhat-ky-van-hanh.pdf'],
                        ['type' => 'photo', 'name' => 'tu-dien-tong.jpg'],
                    ]],
                ],
            ],
            [
                'code' => 'DSP-2LN6YF8GWS',
                'status' => 'dismissed',
                'status_before_decision' => 'pending',
                'decision' => 'dismiss',
                'type' => 'other',
                'severity' => 'low',
                'age' => [6, 28],
                'file_after_hours' => 4,
                'amount' => 0.5,
                'title' => 'Yêu cầu hoàn tiền 30 phút do đến muộn tại {court}',
                'summary' => 'Khách đến muộn 30 phút so với ca {slot} ngày {date} vì kẹt xe và đề nghị hoàn tiền phần thời gian không chơi.',
                'map_status' => 'available',
                'map_label' => 'Hoạt động bình thường',
                'fault_facility' => 0,
                'root_cause' => 'Khách đến muộn; sân vẫn được giữ trống cho khách trong suốt khung giờ đã đặt.',
                'recommendation' => 'Bác yêu cầu hoàn tiền theo chính sách đặt sân.',
                'arbitrator_notes' => 'Khung giờ {slot} đã được giữ riêng cho khách; việc đến muộn không thuộc trách nhiệm của cơ sở nên không hoàn tiền.',
                'timeline' => [
                    [0.0, 'dispute_filed', 'Khách gửi khiếu nại', '{customer} đề nghị hoàn tiền 30 phút đến muộn.', 'primary', 'customer', ['status' => 'pending']],
                    [0.4, 'evidence_submitted', 'Cơ sở gửi giải trình', '{operator} gửi giờ check-in thực tế của khách.', 'primary', 'operator', ['is_private' => false, 'has_explanation' => true]],
                    ['arbitration', 'dispute_arbitrated', 'Bác khiếu nại', 'Aura bác yêu cầu hoàn tiền theo chính sách đặt sân.', 'error', 'arbitrator', []],
                ],
                'chat' => [
                    [0.0, 'complainant', 'Tôi bị kẹt xe nên đến muộn 30 phút, mong sân hoàn lại phần thời gian không chơi.'],
                    [0.4, 'facility', 'Sân được giữ trống cho khách suốt khung giờ đã đặt, cơ sở không có lỗi trong trường hợp này.'],
                ],
                'evidence' => [
                    [0.4, 'operator', 'Giờ check-in thực tế tại quầy.', [
                        ['type' => 'document', 'name' => 'gio-check-in.pdf'],
                    ]],
                ],
            ],
        ];
    }

    /** A few admin and owner actions for the audit log screens, each on a real record. */
    private function seedAuditTrail(): void
    {
        // Partner KYC approvals, as ApprovePartnerApplication records them.
        $kycs = HostKyc::with('organization')
            ->where('status', 'approved')
            ->whereNotNull('organization_id')
            ->whereNotNull('reviewed_at')
            ->where('reviewed_at', '<=', $this->now)
            ->orderBy('reviewed_at')
            ->get();
        foreach ($kycs as $kyc) {
            $this->audit(User::find($kyc->reviewed_by) ?? $this->arbitrator, 'kyc.reviewed.approved', $kyc, $kyc->reviewed_at, [
                'organization_id' => $kyc->organization_id,
                'organization_name' => $kyc->organization?->name,
                'owner_user_id' => $kyc->user_id,
            ]);
        }

        // Owners re-pricing their dearest slot (AdminController::updatePrice), logged at the slot's last change.
        $owners = [
            ['aura-badminton-club', 'owner@demo.auracourt.vn', '198.51.100.24', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36'],
            ['hoang-gia-sports', 'owner.hoanggia@demo.auracourt.vn', '198.51.100.87', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1'],
        ];
        foreach ($owners as [$slug, $email, $ip, $userAgent]) {
            $owner = User::where('email', $email)->first();
            $organizationId = Organization::where('slug', $slug)->value('id');
            $slot = CourtPriceSlot::whereHas('court.venue', fn ($q) => $q->where('organization_id', $organizationId))
                ->where('is_active', true)
                ->orderByDesc('price')
                ->orderBy('id')
                ->first();
            if (! $owner || ! $slot) {
                continue;
            }
            $this->audit($owner, 'price.updated', $slot, $this->clamp($slot->updated_at, $slot->created_at, $this->now), [
                'court_id' => $slot->court_id,
                'fields' => ['price'],
            ], $ip, $userAgent);
        }
    }

    private function audit(?User $user, string $action, $model, Carbon $at, array $metadata, string $ip = self::ADMIN_IP, string $userAgent = self::ADMIN_UA): void
    {
        AuditLog::firstOrNew([
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
        ])->forceFill([
            'user_id' => $user?->id,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'metadata' => $metadata,
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();
    }

    private function membershipPlanName(int $organizationId, int $userId, Carbon $at): ?string
    {
        return TenantMembership::with('plan')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at)
            ->orderByDesc('ends_at')
            ->first()?->plan?->name;
    }

    /** A copy of $time moved inside [$min, $max] (Carbon's min/max return one of the operands, not a copy). */
    private function clamp(Carbon $time, Carbon $min, Carbon $max): Carbon
    {
        return ($time->lt($min) ? $min : ($time->gt($max) ? $max : $time))->copy();
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        return mb_strtoupper(mb_substr((string) reset($parts), 0, 1).mb_substr((string) end($parts), 0, 1));
    }

    /** @return array<int, string> */
    private function customerEmails(): array
    {
        return ['user@example.com', ...array_map(fn (int $n): string => sprintf('khach%02d@demo.auracourt.vn', $n), range(1, 24))];
    }
}
