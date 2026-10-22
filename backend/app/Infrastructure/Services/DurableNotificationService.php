<?php

namespace App\Infrastructure\Services;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\HostKyc;
use App\Models\OrganizationMember;
use App\Models\OutboxMessage;
use App\Models\SuspensionRemediation;
use App\Models\User;
use App\Models\VenueSuspension;
use App\Services\Infrastructure\OutboxService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class DurableNotificationService implements DurableNotificationServiceInterface
{
    public function __construct(
        private readonly OutboxService $outbox,
        private readonly AuditLogRepositoryInterface $auditRepo,
    ) {}

    public function recordOutboxNotification(
        string $key,
        int $userId,
        string $eventType,
        array $payload,
        ?int $orgId = null,
        array $channels = ['database']
    ): OutboxMessage {
        return $this->outbox->enqueue([
            'message_key' => $key,
            'channel' => 'notification',
            'event_type' => $eventType,
            'organization_id' => $orgId,
            'aggregate_type' => $payload['aggregate_type'] ?? null,
            'aggregate_id' => isset($payload['aggregate_id']) ? (int) $payload['aggregate_id'] : null,
        ], array_merge($payload, ['user_id' => $userId, 'channels' => $channels]));
    }

    public function recordAudit(
        ?int $userId,
        string $action,
        Model $auditable,
        array $metadata = []
    ): AuditLog {
        $ip = request()?->ip() ?? '127.0.0.1';
        $ua = request()?->userAgent() ?? 'System';

        return $this->auditRepo->create([
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => get_class($auditable),
            'auditable_id' => (int) $auditable->getKey(),
            'ip_address' => $ip,
            'user_agent' => $ua,
            'metadata' => $metadata,
        ]);
    }

    public function notifyApplicationSubmitted(HostKyc $kyc): OutboxMessage
    {
        $key = "kyc.submitted.{$kyc->id}.v{$kyc->version}";
        $userId = (int) $kyc->user_id;

        return $this->recordOutboxNotification(
            key: $key,
            userId: $userId,
            eventType: 'partner.application.submitted',
            payload: [
                'title' => 'Đã tiếp nhận hồ sơ đăng ký đối tác',
                'body' => "Hồ sơ {$kyc->kyc_code} đã được nộp thành công và đang chờ xét duyệt.",
                'kyc_code' => $kyc->kyc_code,
                'version' => $kyc->version,
                'aggregate_type' => HostKyc::class,
                'aggregate_id' => $kyc->id,
            ],
            orgId: $kyc->organization_id
        );
    }

    public function notifyApplicationReviewed(HostKyc $kyc, string $decision, ?string $reason = null): OutboxMessage
    {
        $key = "kyc.reviewed.{$kyc->id}.v{$kyc->version}.{$decision}";
        $userId = (int) $kyc->user_id;

        $title = match ($decision) {
            'approve' => 'Hồ sơ đối tác đã được phê duyệt',
            'revision_required' => 'Hồ sơ đối tác cần bổ sung thông tin',
            'reject' => 'Hồ sơ đối tác bị từ chối',
            default => 'Cập nhật trạng thái hồ sơ đối tác',
        };

        return $this->recordOutboxNotification(
            key: $key,
            userId: $userId,
            eventType: "partner.application.{$decision}",
            payload: [
                'title' => $title,
                // BR-15: the applicant reads the decision and its reason in the notification itself.
                'body' => $reason ? "Hồ sơ {$kyc->kyc_code}: {$reason}" : "Hồ sơ {$kyc->kyc_code} đã có kết quả xét duyệt.",
                'action_url' => '/partner/application-status',
                'decision' => $decision,
                'reason' => $reason,
                'kyc_code' => $kyc->kyc_code,
                'version' => $kyc->version,
                'aggregate_type' => HostKyc::class,
                'aggregate_id' => $kyc->id,
            ],
            orgId: $kyc->organization_id
        );
    }

    public function notifyBooking(Booking $booking, string $event, string $title, string $body, ?string $actionUrl = '/booking'): OutboxMessage
    {
        $booking->loadMissing(['court.venue']);
        $key = "booking.notification.{$booking->id}.{$event}";
        $orgId = $booking->court?->venue?->organization_id;

        try {
            return $this->recordOutboxNotification(
                key: $key,
                userId: (int) $booking->user_id,
                eventType: 'booking.'.$event,
                payload: [
                    'user_id' => (int) $booking->user_id,
                    'booking_id' => $booking->id,
                    'booking_code' => $booking->code,
                    'event' => $event,
                    'title' => $title,
                    'body' => $body,
                    'action_url' => $actionUrl,
                    'delivered_channels' => [],
                    'mail_view' => 'emails.booking-notification',
                    'aggregate_type' => Booking::class,
                    'aggregate_id' => $booking->id,
                ],
                orgId: $orgId,
                channels: ['database', 'mail']
            );
        } catch (\Throwable $secondaryException) {
            Log::warning("Secondary booking notification delivery failed immediately for booking {$booking->code}: {$secondaryException->getMessage()}", [
                'booking_code' => $booking->code,
                'exception' => $secondaryException,
            ]);

            return OutboxMessage::where('message_key', $key)->firstOrFail();
        }
    }

    public function notifyVenueSuspended(VenueSuspension $suspension): void
    {
        $venue = $suspension->venue;
        $this->fanOut($this->ownerIds((int) $venue->organization_id), "venue.suspended.{$suspension->id}", 'venue.suspended', [
            'title' => 'Cơ sở bị đình chỉ',
            'body' => "Cơ sở {$venue->name} bị đình chỉ nhận đặt sân. Lý do: {$suspension->reason}",
            'action_url' => $this->suspensionUrl($suspension),
            'aggregate_type' => VenueSuspension::class,
            'aggregate_id' => $suspension->id,
        ], (int) $venue->organization_id);
    }

    public function notifyRemediationSubmitted(SuspensionRemediation $remediation): void
    {
        $venue = $remediation->suspension->venue;
        $adminIds = User::query()->whereIn('role', ['platform_admin', 'admin'])->where('status', '!=', 'locked')->pluck('id')->all();
        $this->fanOut($adminIds, "remediation.submitted.{$remediation->id}", 'venue.remediation.submitted', [
            'title' => 'Có yêu cầu khắc phục đình chỉ mới',
            'body' => "Chủ cơ sở {$venue->name} đã gửi hồ sơ khắc phục và chờ xem xét.",
            'action_url' => "/admin/venue-remediations/{$remediation->id}",
            'aggregate_type' => SuspensionRemediation::class,
            'aggregate_id' => $remediation->id,
        ], (int) $venue->organization_id);
    }

    public function notifyRemediationReviewed(SuspensionRemediation $remediation): void
    {
        $suspension = $remediation->suspension;
        $venue = $suspension->venue;
        $title = match ($remediation->status) {
            'approved' => 'Yêu cầu khắc phục được chấp thuận, đình chỉ đã được gỡ',
            'rejected' => 'Yêu cầu khắc phục bị từ chối',
            default => 'Yêu cầu khắc phục cần bổ sung thông tin',
        };
        $this->fanOut($this->ownerIds((int) $venue->organization_id), "remediation.reviewed.{$remediation->id}", 'venue.remediation.'.$remediation->status, [
            'title' => $title,
            'body' => "Cơ sở {$venue->name}: {$remediation->review_note}",
            'action_url' => $this->suspensionUrl($suspension),
            'aggregate_type' => SuspensionRemediation::class,
            'aggregate_id' => $remediation->id,
        ], (int) $venue->organization_id);
    }

    /** @return list<int> active owners only: staff and customers never receive suspension notifications */
    private function ownerIds(int $organizationId): array
    {
        return OrganizationMember::query()->where('organization_id', $organizationId)
            ->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)->where('status', 'active')
            ->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @param  list<int>  $userIds */
    private function fanOut(array $userIds, string $key, string $eventType, array $payload, int $orgId): void
    {
        foreach (array_unique($userIds) as $userId) {
            $this->recordOutboxNotification("{$key}.user.{$userId}", (int) $userId, $eventType, $payload, $orgId);
        }
    }

    private function suspensionUrl(VenueSuspension $suspension): string
    {
        $venue = $suspension->venue;

        return "/admin/organizations/{$venue->organization_id}/venues/{$venue->id}/suspensions/{$suspension->id}";
    }
}
