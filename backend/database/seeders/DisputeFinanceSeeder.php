<?php

namespace Database\Seeders;

use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\CourtPriceSlot;
use App\Models\DisputeCase;
use App\Models\FinanceException;
use App\Models\HostKyc;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerRefundObligation;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\ProviderEvent;
use App\Models\ReconciliationRun;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\Settlement;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Finance\FinancialPostingService;
use App\Services\Finance\PayoutService;
use App\Services\Finance\ReconciliationService;
use App\Services\Finance\SettlementService;
use Carbon\Carbon;
use Database\Seeders\Concerns\KeepsWakingHours;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Disputes on real past reservations, the finance trail derived from the seeded payments and refunds
 * (provider events, journals, weekly settlements, payouts, reconciliation runs and their exceptions),
 * and a few audit log entries on real records.
 */
class DisputeFinanceSeeder extends Seeder
{
    use KeepsWakingHours;

    // Documentation-range addresses (RFC 5737).
    private const ADMIN_IP = '203.0.113.15';

    private const ADMIN_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Safari/605.1.15';

    /** Bank-transfer providers whose payments reach the platform account. */
    private const TRANSFER_PROVIDERS = ['sepay', 'payos'];

    private Carbon $now;

    private ?User $arbitrator = null;

    /** Rejected transfers whose money arrived and is still owed back to the customer. */
    private Collection $held;

    public function run(): void
    {
        $this->now = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'))->startOfMinute();
        $this->arbitrator = User::where('email', 'admin@auracourt.vn')->first();

        $this->seedDisputes();
        $this->seedFinance();
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
        $paidBookingIds = Payment::where('status', 'paid')->where('received_at', '<=', $this->now)->pluck('booking_id')->filter();
        // The app accepts a dispute until 30 days after the reservation ended.
        $candidates = Reservation::query()
            ->with(['court.venue.organization', 'user', 'slots', 'legacyMapping'])
            ->whereIn('user_id', $customerIds)
            ->whereHas('legacyMapping', fn ($q) => $q->whereIn('legacy_status', ['confirmed', 'completed'])->whereIn('legacy_booking_id', $paidBookingIds))
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
            // Decided yesterday, so the owner's 7-day refund deadline stays ahead through the demo.
            'resolve_and_refund' => $this->awake($this->now->copy()->subDay(), $filedAt),
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
        $refundAmount = isset($spec['refund_ratio']) ? (int) (floor($total * $spec['refund_ratio'] / 1000) * 1000) : null;
        $dueAt = $decision === 'resolve_and_refund' ? $arbitratedAt->copy()->addDays(7) : null;
        $membershipPlan = $this->membershipPlanName($org->id, $customer->id, $filedAt);

        $vars = [
            '{court}' => $court->getTranslation('name', 'vi'),
            '{venue}' => $venue->getTranslation('name', 'vi'),
            '{date}' => $startsAt->format('d/m'),
            '{slot}' => $startsAt->format('H:i').'-'.$endsAt->format('H:i'),
            '{customer}' => $customer->name,
            '{operator}' => $operator?->name ?? $org->name,
            '{refund}' => number_format((int) $refundAmount, 0, ',', '.').'₫',
            '{due}' => $dueAt?->format('d/m/Y') ?? '',
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

        $obligation = $decision === 'resolve_and_refund'
            ? $this->refundObligation($dispute, $reservation, $respondentId, (int) $refundAmount, $arbitratedAt, $text($spec['arbitrator_notes']))
            : null;

        $events = [];
        foreach ($spec['timeline'] as [$when, $code, $title, $description, $type, $actor, $extra]) {
            $time = $when === 'arbitration' ? $arbitratedAt : ($when === 0.0 ? $filedAt : $at($when));
            $actorUser = $actor ? $actors[$actor] : null;
            $extra = match ($code) {
                'dispute_arbitrated' => ['decision' => $decision, 'version' => 1, 'refund_obligation_id' => $obligation?->id],
                'refund_task_created' => [
                    'task_type' => 'owner_refund_execution',
                    'obligation_id' => $obligation?->id,
                    'status' => 'pending_financial_evidence',
                    'responsible_actor_id' => $obligation?->payer_id,
                    'amount' => $refundAmount,
                    'due_at' => $dueAt?->toIso8601String(),
                    'payment_completed' => false,
                ],
                default => $extra,
            };
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
            'refund_amount' => $refundAmount,
            'compensation_type' => $spec['compensation_type'] ?? null,
            'compensation_value' => $spec['compensation_value'] ?? null,
            'penalty_notes' => isset($spec['penalty_notes']) ? $text($spec['penalty_notes']) : null,
            'penalty_points' => $spec['penalty_points'] ?? null,
            'arbitrated_by' => $decision ? $this->arbitrator?->id : null,
            'arbitrated_at' => $arbitratedAt,
            'arbitrator_notes' => $decision ? $text($spec['arbitrator_notes']) : null,
            'refund_obligation_id' => $obligation?->id,
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
                    'refund_amount' => $refundAmount,
                    'refund_method' => $dispute->refund_method,
                    'refund_obligation_id' => $obligation?->id,
                ],
                'reason' => $dispute->arbitrator_notes,
            ]);
        }
    }

    /** The refund task ArbitrateDispute opens on resolve_and_refund: the owner still has to transfer the money. */
    private function refundObligation(DisputeCase $dispute, Reservation $reservation, ?int $respondentId, int $amount, Carbon $arbitratedAt, string $reason): ?OwnerRefundObligation
    {
        if (! $this->arbitrator || $amount <= 0) {
            return null;
        }

        $key = "dispute_refund_{$dispute->id}_v1";
        $obligation = OwnerRefundObligation::firstOrNew(['organization_id' => $dispute->organization_id, 'idempotency_key' => $key]);
        $obligation->forceFill([
            'venue_id' => $dispute->venue_id,
            'booking_id' => $dispute->booking_id,
            'reservation_id' => $reservation->id,
            'payer_id' => $respondentId ?? $this->arbitrator->id,
            'reference_code' => 'REF-'.$dispute->dispute_code,
            'request_fingerprint' => hash('sha256', $key),
            'slot_ids' => $reservation->slots->pluck('id')->all(),
            'gross_amount' => $amount,
            'fee_amount' => 0,
            'amount' => $amount,
            'currency' => 'VND',
            'status' => OwnerRefundObligation::STATUS_APPROVED,
            'reason' => $reason,
            'policy_snapshot' => ['dispute_code' => $dispute->dispute_code, 'arbitrated_at' => $arbitratedAt->toIso8601String()],
            'recipient_ciphertext' => Crypt::encryptString(json_encode([
                'name' => $dispute->complainant_name,
                'user_id' => $dispute->complainant_id,
                'phone' => $dispute->complainant_phone,
            ], JSON_THROW_ON_ERROR)),
            'due_at' => $arbitratedAt->copy()->addDays(7),
            'requested_at' => $arbitratedAt,
            'approved_at' => $arbitratedAt,
            'requested_by' => $this->arbitrator->id,
            'approved_by' => $this->arbitrator->id,
            'created_at' => $arbitratedAt,
            'updated_at' => $arbitratedAt,
        ])->save();

        return $obligation;
    }

    /** The manager scoped to this venue, else one covering every venue of the organization. */
    private function venueOperator(int $organizationId, int $venueId): ?User
    {
        return OrganizationMember::with('user')
            ->where('organization_id', $organizationId)
            ->where('role', OrganizationMember::ROLE_VENUE_MANAGER)
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
            ->where('sport_zone_id', $reservation->court->sport_zone_id)
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
                'recommendation' => 'Yêu cầu {venue} gửi nhật ký bảo trì đèn. Nếu xác nhận lỗi thiết bị, chấp nhận hoàn 30% tiền sân.',
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
                'recommendation' => 'Đối chiếu nhật ký check-in tại quầy với lịch đặt; nếu xác nhận lỗi xếp sân, hoàn tiền tương ứng thời gian chờ và nhắc nhở ca trực.',
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
                'decision' => 'resolve_and_refund',
                'refund_method' => 'payos_bank',
                'refund_ratio' => 0.5,
                'compensation_type' => 'none',
                'compensation_value' => 'Không bồi thường thêm',
                'penalty_notes' => 'Nhắc nhở {venue} kiểm tra máy phát dự phòng định kỳ hằng tháng.',
                'penalty_points' => 1,
                'type' => 'court_quality',
                'severity' => 'high',
                'age' => [5, 25],
                'file_after_hours' => 2,
                'amount' => 'total',
                'title' => 'Mất điện 40 phút trong ca chơi tại {court}',
                'summary' => 'Cụm sân mất điện khoảng 40 phút trong ca {slot} ngày {date}. Khách đề nghị hoàn tiền phần thời gian không sử dụng được.',
                'map_status' => 'available',
                'map_label' => 'Đã khắc phục',
                'fault_facility' => 100,
                'root_cause' => 'Sự cố aptomat tổng tại {venue}, máy phát dự phòng khởi động chậm.',
                'recommendation' => 'Chấp nhận hoàn 50% tiền sân; yêu cầu cơ sở kiểm tra định kỳ máy phát dự phòng.',
                'arbitrator_notes' => 'Nhật ký vận hành của {venue} xác nhận mất điện 40 phút. Chấp nhận hoàn 50% tiền sân ({refund}); cơ sở chuyển khoản cho khách và gửi chứng từ trước {due}.',
                'timeline' => [
                    [0.0, 'dispute_filed', 'Khách gửi khiếu nại', '{customer} báo mất điện trong ca {slot}.', 'primary', 'customer', ['status' => 'pending']],
                    [0.4, 'evidence_submitted', 'Cơ sở xác nhận sự cố', '{operator} gửi nhật ký vận hành xác nhận mất điện 40 phút.', 'warning', 'operator', ['is_private' => false, 'has_explanation' => true]],
                    ['arbitration', 'dispute_arbitrated', 'Phán quyết hoàn tiền', 'Aura chấp nhận khiếu nại, cơ sở hoàn 50% tiền sân ({refund}) cho khách.', 'success', 'arbitrator', []],
                    ['arbitration', 'refund_task_created', 'Chờ cơ sở chuyển hoàn', '{venue} cần chuyển {refund} cho khách và gửi chứng từ trước {due}.', 'warning', null, []],
                ],
                'chat' => [
                    [0.0, 'complainant', 'Sân mất điện gần 40 phút, cả nhóm phải ngồi chờ. Mong được hoàn lại phần tiền này.'],
                    [0.4, 'facility', 'Cơ sở xác nhận sự cố aptomat tổng và sẽ hoàn tiền theo phán quyết.'],
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

    /**
     * The finance trail of the seeded payments, written through the finance services so every total
     * is computed the way the app computes it. Platform commission is retired and the provider fee
     * comes from config (0 bps in the demo), so journals and settlements carry neither.
     */
    private function seedFinance(): void
    {
        $posting = app(FinancialPostingService::class);
        $pendingRefundBookings = Refund::where('status', 'pending')->pluck('booking_id');
        $received = Payment::with(['booking.court.venue', 'booking.user'])
            ->whereIn('provider', self::TRANSFER_PROVIDERS)
            ->whereNotNull('received_at')
            ->where('received_at', '<=', $this->now)
            ->where(fn ($q) => $q->where('status', 'paid')
                ->orWhere(fn ($q) => $q->where('status', 'rejected')->whereIn('booking_id', $pendingRefundBookings)))
            ->orderBy('received_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Payment $payment): bool => $payment->booking?->court?->venue !== null)
            ->values();
        // Rejected on review after the transfer arrived: the money stays unbooked until the refund goes out.
        $this->held = $received->where('status', 'rejected')->values();

        foreach ($received as $payment) {
            $this->providerEvent($payment);
            if ($payment->status === 'paid') {
                $this->at($payment->received_at, fn () => $posting->postLegacyBookingPayment($payment->booking, $payment));
            }
        }

        $refunds = Refund::where('status', 'processed')
            ->whereNotNull('processed_at')
            ->where('processed_at', '<=', $this->now)
            ->orderBy('processed_at')
            ->orderBy('id')
            ->get();
        foreach ($refunds as $refund) {
            // Only money the ledger collected can go back out through it.
            $payment = $received->first(fn (Payment $p): bool => (int) $p->booking_id === (int) $refund->booking_id
                && $p->status === 'paid'
                && $p->received_at->lte($refund->processed_at));
            $organizationId = $payment?->booking->court->venue->organization_id;
            if (! $payment || Journal::where('organization_id', $organizationId)->where('event_type', 'refund')->where('event_id', "refund:booking-refund:{$refund->id}")->exists()) {
                continue;
            }
            $this->at($refund->processed_at, fn () => $posting->postRefund($organizationId, $payment->booking->code, (int) $refund->amount, "booking-refund:{$refund->id}", [
                'booking_id' => $refund->booking_id,
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
            ]));
        }

        foreach (Organization::where('status', 'active')->orderBy('id')->get() as $org) {
            $this->settleAndReconcile($org, $this->beneficiary($org));
            $this->describeMismatches($org);
            $this->verifyFinance($org, $received->filter(fn (Payment $p): bool => (int) $p->booking->court->venue->organization_id === $org->id));
        }
    }

    /** The verified provider notification of a transfer that reached the platform account. */
    private function providerEvent(Payment $payment): void
    {
        $at = $payment->received_at;
        ProviderEvent::firstOrNew([
            'provider' => $payment->provider,
            'event_id' => $payment->external_transaction_id ?: "payment-{$payment->id}",
        ])->forceFill([
            'organization_id' => $payment->booking->court->venue->organization_id,
            'type' => 'payment.status',
            'status' => 'PAID',
            'reference' => $payment->booking->code,
            'provider_payment_id' => $payment->external_transaction_id,
            'amount' => (int) $payment->amount,
            'currency' => 'VND',
            // The SePay webhook fields ProcessQrPayment reads; no account numbers.
            'payload' => [
                'transactionDate' => $at->format('Y-m-d H:i:s'),
                'transferType' => 'in',
                'transferAmount' => (int) $payment->amount,
                'code' => $payment->transfer_code,
                'content' => trim($payment->transfer_code.' thanh toan dat san'),
                'referenceCode' => $payment->external_transaction_id,
            ],
            // Marked processed so the provider-event recovery job leaves it alone.
            'verified_at' => $at,
            'processed_at' => $at,
            'attempts' => 1,
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();
    }

    /** The payout account from the organization's approved KYC, stored the way BeneficiaryService keeps a PayOS-verified one. */
    private function beneficiary(Organization $org): ?Beneficiary
    {
        $kyc = HostKyc::where('organization_id', $org->id)
            ->where('status', 'approved')
            ->whereNotNull('reviewed_at')
            ->where('reviewed_at', '<=', $this->now)
            ->where('bank_code', '<>', '')
            ->orderByDesc('reviewed_at')
            ->first();
        $account = preg_replace('/\D/', '', (string) $kyc?->bank_account_number);
        if (! $kyc || $account === '') {
            $this->command?->warn("DisputeFinanceSeeder: {$org->slug} has no approved KYC bank account, payouts skipped.");

            return null;
        }

        $verifiedAt = $this->clamp($kyc->reviewed_at->copy()->addDay()->setTime(10, 15), $kyc->reviewed_at, $this->now);
        $beneficiaryId = 'bnf_'.substr(hash('sha256', "{$org->id}:{$kyc->bank_code}:{$account}"), 0, 20);
        $holder = $kyc->bank_account_holder;
        $beneficiary = Beneficiary::firstOrNew([
            'organization_id' => $org->id,
            'provider' => 'payos',
            'bank_code' => $kyc->bank_code,
            'account_number' => $account,
        ]);
        $beneficiary->forceFill([
            'provider_beneficiary_id' => $beneficiaryId,
            'account_holder' => $holder,
            'tax_id' => $kyc->tax_code ?: $org->tax_code,
            'status' => 'verified',
            'verified_at' => $verifiedAt,
            'verification_payload' => [
                'provider' => 'payos',
                'verified' => true,
                'bankCode' => $kyc->bank_code,
                'accountNumber' => $account,
                'accountHolder' => $holder,
                'providerBeneficiaryId' => $beneficiaryId,
                'failureCode' => null,
                'raw' => ['code' => '00', 'desc' => 'success', 'data' => ['verified' => true, 'accountName' => $holder, 'beneficiaryId' => $beneficiaryId]],
            ],
            'created_at' => $verifiedAt,
            'updated_at' => $verifiedAt,
        ])->save();

        return $beneficiary;
    }

    /**
     * Weekly settlements, payouts and the nightly reconciliation runs, replayed in time order so each
     * step only sees what existed when it ran. A week is snapshotted after it closes, so no nightly
     * run includes a settlement (runs match settlements on period_start).
     */
    private function settleAndReconcile(Organization $org, ?Beneficiary $beneficiary): void
    {
        $settlements = app(SettlementService::class);
        $reconciliation = app(ReconciliationService::class);
        $today = $this->now->copy()->startOfDay();
        $delay = (int) config('finance.settlement_delay_days', 2);

        // Closed Monday-Sunday weeks that have journals.
        $weeks = [];
        $first = Journal::where('organization_id', $org->id)->min('posted_at');
        for ($start = $first ? Carbon::parse($first)->startOfWeek(Carbon::MONDAY) : $today->copy(); $start->copy()->addDays(6)->lt($today); $start->addWeek()) {
            $end = $start->copy()->addDays(6);
            if (Journal::where('organization_id', $org->id)->whereBetween('posted_at', [$start, $end->copy()->endOfDay()])->exists()) {
                $weeks[] = [$start->copy(), $end];
            }
        }
        $eligible = array_keys(array_filter($weeks, fn (array $week): bool => $week[1]->copy()->endOfDay()->addDays($delay)->lt($this->now)));
        // The owner has not asked for the latest eligible week yet, so it stays open for a payout.
        $waiting = $eligible === [] ? null : max($eligible);

        $steps = [];
        foreach ($weeks as $i => [$start, $end]) {
            // Finance closes the week on Monday morning.
            $steps[] = [$this->notAfterNow($end->copy()->addDay()->setTime(9, 0)), 0, fn () => $settlements->snapshot($org, $start, $end)];
            if (in_array($i, $eligible, true)) {
                // Payout requested the morning after the D+2 hold ends; settled within the morning.
                $requestAt = $end->copy()->addDays($delay + 1)->setTime(9, 0);
                $pay = $i !== $waiting && $requestAt->copy()->addHours(3)->lte($this->now);
                $steps[] = [$this->notAfterNow($requestAt), 1, fn () => $this->payout($org, $start, $end, $pay ? $beneficiary : null, $requestAt)];
            }
        }
        for ($day = $today->copy()->subDays(30); $day->lte($today); $day->addDay()) {
            $runDate = $day->copy();
            // The nightly run checks the finished day at 01:00; today's run is the one an admin just triggered.
            $runAt = $runDate->equalTo($today) ? $this->now->copy() : $this->notAfterNow($runDate->copy()->addDay()->setTime(1, 0));
            $steps[] = [$runAt, 2, fn () => ReconciliationRun::where('organization_id', $org->id)->whereDate('run_date', $runDate)->exists()
                ? null
                : $reconciliation->run($org, $runDate)];
        }

        usort($steps, fn (array $a, array $b): int => [$a[0]->getTimestamp(), $a[1]] <=> [$b[0]->getTimestamp(), $b[1]]);
        foreach ($steps as [$at, , $step]) {
            $this->at($at, $step);
        }
    }

    /** Pays an eligible week out through PayoutService, or only refreshes its eligibility when no payout is due. */
    private function payout(Organization $org, Carbon $start, Carbon $end, ?Beneficiary $beneficiary, Carbon $requestAt): void
    {
        $settlement = Settlement::where('organization_id', $org->id)->whereDate('period_start', $start)->whereDate('period_end', $end)->firstOrFail();
        if (! $beneficiary || $settlement->tenant_payable < (int) config('finance.minimum_payout', 500000) || $settlement->payouts()->exists()) {
            app(SettlementService::class)->refreshEligibility($settlement);

            return;
        }

        $payouts = app(PayoutService::class);
        $payout = $payouts->createDraft($settlement, $beneficiary);
        $payout = $this->at($requestAt->copy()->addMinutes(40), fn () => $payouts->approve($payout));
        // Submit and status sync call the live provider, so the transfer's outcome is recorded directly.
        $submittedAt = $requestAt->copy()->addHour();
        $paidAt = $submittedAt->copy()->addHours(2);
        $this->at($paidAt, fn () => $payout->forceFill([
            'provider' => 'payos',
            'provider_payout_id' => 'po_'.substr(hash('sha256', "payout:{$payout->idempotency_key}"), 0, 20),
            'status' => 'paid',
            'submitted_at' => $submittedAt,
            'paid_at' => $paidAt,
        ])->save());
    }

    /**
     * The exceptions screen reads its display fields (shift, customer, court, gateway, expected and
     * actual amounts) from the metadata, so the run details the service stored there gain the held
     * transfers behind the mismatch.
     */
    private function describeMismatches(Organization $org): void
    {
        $exceptions = FinanceException::where('organization_id', $org->id)
            ->where('type', 'reconciliation_mismatch')
            ->where('status', 'open')
            ->orderBy('id')
            ->get();
        foreach ($exceptions as $exception) {
            $day = Carbon::parse(substr((string) $exception->reference, strlen('reconciliation:')));
            $held = $this->held
                ->filter(fn (Payment $p): bool => (int) $p->booking->court->venue->organization_id === $org->id && $p->received_at->isSameDay($day))
                ->sortBy([['amount', 'desc'], ['id', 'asc']])
                ->values();
            $main = $held->first();
            if (! $main) {
                continue;
            }
            $run = ReconciliationRun::find($exception->links['reconciliation_run_id'] ?? 0);
            $customer = $main->booking->user;
            $court = $main->booking->court;
            $total = (int) $held->sum('amount');

            $exception->timestamps = false;
            $exception->forceFill(['metadata' => [
                ...($exception->metadata ?? []),
                'shift' => $this->shiftLabel($main->received_at),
                'user' => [
                    'name' => $customer?->name,
                    'id' => $customer ? "UID-{$customer->id}" : null,
                    'tier' => ($customer ? $this->membershipPlanName($org->id, $customer->id, $main->received_at) : null) ?? 'Khách hàng',
                ],
                'court' => $court->venue->getTranslation('name', 'vi').' - '.$court->getTranslation('name', 'vi'),
                'gateway' => 'SePay (VietQR)',
                'expected_amount' => (int) $run?->journal_total,
                'actual_amount' => (int) $run?->provider_total,
                'reason' => $held->count() === 1
                    ? "Đã nhận {$this->money($total)} chuyển khoản cho {$main->booking->code} nhưng thanh toán bị từ chối khi duyệt; khoản hoàn cho khách đang chờ xử lý nên chưa ghi sổ."
                    : "{$held->count()} khoản chuyển khoản ({$this->money($total)}) đã về tài khoản nhưng thanh toán bị từ chối khi duyệt; các khoản hoàn cho khách đang chờ xử lý nên chưa ghi sổ.",
                'held_payments' => $held->map(fn (Payment $p): array => [
                    'payment_id' => $p->id,
                    'booking_code' => $p->booking->code,
                    'amount' => (int) $p->amount,
                    'received_at' => $p->received_at->toIso8601String(),
                ])->all(),
            ]])->save();
        }
    }

    /** Fails the seed when the finance trail stops adding up. */
    private function verifyFinance(Organization $org, Collection $received): void
    {
        $fail = fn (string $what) => throw new RuntimeException("DisputeFinanceSeeder self-check failed for {$org->slug}: {$what}");

        $paid = (int) $received->where('status', 'paid')->sum('amount');
        $collected = (int) Journal::where('organization_id', $org->id)->where('event_type', 'booking.payment')->sum('total_credit');
        if ($collected !== $paid) {
            $fail("booking.payment journals total {$collected}, paid payments {$paid}");
        }
        if ((int) ProviderEvent::where('organization_id', $org->id)->sum('amount') !== (int) $received->sum('amount')) {
            $fail('provider events do not match the received transfers');
        }
        foreach (Settlement::where('organization_id', $org->id)->get() as $settlement) {
            if ($settlement->commission_amount !== 0
                || $settlement->tenant_payable !== $settlement->gross_amount - $settlement->refund_amount - $settlement->provider_fee_amount
                || ($settlement->snapshot['tenant_payable'] ?? null) !== $settlement->tenant_payable
                || $settlement->snapshotted_at->gt($this->now)) {
                $fail("settlement {$settlement->period_start->toDateString()}");
            }
        }
        foreach (Payout::with('settlement')->where('organization_id', $org->id)->get() as $payout) {
            if ($payout->status !== 'paid'
                || $payout->amount !== $payout->settlement->tenant_payable
                || $payout->created_at->lt($payout->settlement->eligible_at)
                || ! ($payout->approved_at->lte($payout->submitted_at) && $payout->submitted_at->lte($payout->paid_at) && $payout->paid_at->lte($this->now))) {
                $fail("payout {$payout->id}");
            }
        }
        $held = $this->held->filter(fn (Payment $p): bool => (int) $p->booking->court->venue->organization_id === $org->id);
        $flagged = 0;
        foreach (ReconciliationRun::where('organization_id', $org->id)->get() as $run) {
            $heldThatDay = (int) $held->filter(fn (Payment $p): bool => $p->received_at->isSameDay($run->run_date))->sum('amount');
            $flagged += $heldThatDay;
            if ($run->mismatch_amount !== $heldThatDay || $run->provider_total - $run->journal_total !== $heldThatDay || $run->completed_at->gt($this->now)) {
                $fail("reconciliation run {$run->run_date->toDateString()}");
            }
        }
        if ((int) FinanceException::where('organization_id', $org->id)->where('type', 'reconciliation_mismatch')->where('status', 'open')->sum('amount') !== $flagged) {
            $fail('open reconciliation exceptions do not match the held transfers');
        }
    }

    /** A few admin and owner actions for the audit log screens, each on a real record. */
    private function seedAuditTrail(): void
    {
        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');

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

        // The manual-review rejections behind the refunds still owed (AdminController::reviewPayment).
        foreach ($this->held as $payment) {
            $payload = $payment->payload ?? [];
            $reviewedAt = isset($payload['reviewed_at']) ? Carbon::parse($payload['reviewed_at'])->setTimezone($tz) : $this->officeHours($payment->received_at->copy()->addMinutes(20));
            $reviewer = isset($payload['reviewed_by']) ? User::find($payload['reviewed_by']) : null;
            $this->audit($reviewer ?? $this->arbitrator, 'payment.reviewed', $payment, $this->clamp($reviewedAt, $payment->received_at, $this->now), ['status' => 'rejected']);
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

    /** The counter shift a moment falls in, worded the way the exceptions screen matches it. */
    private function shiftLabel(Carbon $at): string
    {
        return match (true) {
            $at->hour >= 6 && $at->hour < 12 => 'Sáng (06:00 - 12:00)',
            $at->hour >= 12 && $at->hour < 18 => 'Chiều (12:00 - 18:00)',
            default => 'Tối (18:00 - 06:00)',
        };
    }

    /** The first moment at or after $time when the back office works (08:00-22:00). */
    private function officeHours(Carbon $time): Carbon
    {
        return match (true) {
            $time->hour < 8 => $time->copy()->setTime(8, $time->minute),
            $time->hour >= 22 => $time->copy()->addDay()->setTime(8, $time->minute),
            default => $time->copy(),
        };
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', '.').'₫';
    }

    /** Runs $callback with the clock at $at so services and Eloquent timestamps record that moment. */
    private function at(Carbon $at, callable $callback): mixed
    {
        $previous = Carbon::getTestNow();
        Carbon::setTestNow($at);
        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);
        }
    }

    /** A copy of $time moved inside [$min, $max] (Carbon's min/max return one of the operands, not a copy). */
    private function clamp(Carbon $time, Carbon $min, Carbon $max): Carbon
    {
        return ($time->lt($min) ? $min : ($time->gt($max) ? $max : $time))->copy();
    }

    private function notAfterNow(Carbon $time): Carbon
    {
        return $time->lte($this->now) ? $time : $this->now->copy();
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
