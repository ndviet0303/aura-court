<?php

namespace App\Infrastructure\Services;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\HostKyc;
use App\Models\OutboxMessage;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\VenuePublicationRevision;
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
        ?int $orgId = null
    ): OutboxMessage {
        return $this->outbox->enqueue([
            'message_key' => $key,
            'channel' => 'notification',
            'event_type' => $eventType,
            'organization_id' => $orgId,
            'aggregate_type' => $payload['aggregate_type'] ?? null,
            'aggregate_id' => isset($payload['aggregate_id']) ? (int) $payload['aggregate_id'] : null,
        ], array_merge($payload, ['user_id' => $userId]));
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

    public function notifySubscriptionActivated(PlatformSubscription $subscription): OutboxMessage
    {
        $key = "subscription.activated.{$subscription->id}.v{$subscription->plan_version}";
        $org = $subscription->organization;
        $ownerMember = $org?->members()->where('role', 'organization_owner')->first() ?? $org?->members()->first();
        $userId = (int) ($ownerMember?->user_id ?? $org?->sourceKyc?->user_id ?? User::query()->value('id') ?? 1);

        return $this->recordOutboxNotification(
            key: $key,
            userId: $userId,
            eventType: 'platform.subscription.activated',
            payload: [
                'title' => 'Gói dịch vụ nền tảng đã được kích hoạt',
                'status' => $subscription->status,
                'plan_id' => $subscription->plan_id,
                'aggregate_type' => PlatformSubscription::class,
                'aggregate_id' => $subscription->id,
            ],
            orgId: $subscription->organization_id
        );
    }

    public function notifyPaymentFailed(PlatformPaymentAttempt $attempt): OutboxMessage
    {
        $key = "payment.failed.{$attempt->reference_code}";
        $org = $attempt->organization;
        $ownerMember = $org?->members()->where('role', 'organization_owner')->first() ?? $org?->members()->first();
        $userId = (int) ($ownerMember?->user_id ?? $org?->sourceKyc?->user_id ?? User::query()->value('id') ?? 1);

        return $this->recordOutboxNotification(
            key: $key,
            userId: $userId,
            eventType: 'platform.payment.failed',
            payload: [
                'title' => 'Thanh toán gói dịch vụ thất bại',
                'reference_code' => $attempt->reference_code,
                'amount' => $attempt->amount,
                'reason' => $attempt->failure_reason,
                'aggregate_type' => PlatformPaymentAttempt::class,
                'aggregate_id' => $attempt->id,
            ],
            orgId: $attempt->organization_id
        );
    }

    public function notifyPublicationReviewed(
        VenuePublicationRevision $revision,
        string $decision,
        ?string $reason = null
    ): OutboxMessage {
        $key = "venue.publication.{$revision->id}.{$decision}";
        $userId = (int) $revision->submitted_by;
        $venue = $revision->venue;

        $title = match ($decision) {
            'approved' => "Cơ sở {$venue?->name} đã được phê duyệt xuất bản",
            'revision_required' => "Cơ sở {$venue?->name} cần chỉnh sửa trước khi xuất bản",
            default => "Kết quả xét duyệt xuất bản cơ sở {$venue?->name}",
        };

        return $this->recordOutboxNotification(
            key: $key,
            userId: $userId,
            eventType: "venue.publication.{$decision}",
            payload: [
                'title' => $title,
                'decision' => $decision,
                'reason' => $reason,
                'revision_number' => $revision->revision_number,
                'venue_id' => $revision->venue_id,
                'aggregate_type' => VenuePublicationRevision::class,
                'aggregate_id' => $revision->id,
            ],
            orgId: $venue?->organization_id
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
                    'channels' => ['database', 'mail'],
                    'delivered_channels' => [],
                    'mail_view' => 'emails.booking-notification',
                    'aggregate_type' => Booking::class,
                    'aggregate_id' => $booking->id,
                ],
                orgId: $orgId
            );
        } catch (\Throwable $secondaryException) {
            Log::warning("Secondary booking notification delivery failed immediately for booking {$booking->code}: {$secondaryException->getMessage()}", [
                'booking_code' => $booking->code,
                'exception' => $secondaryException,
            ]);

            return OutboxMessage::where('message_key', $key)->firstOrFail();
        }
    }
}
