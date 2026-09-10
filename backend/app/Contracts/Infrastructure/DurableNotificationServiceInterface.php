<?php

namespace App\Contracts\Infrastructure;

use App\Models\AuditLog;
use App\Models\HostKyc;
use App\Models\OutboxMessage;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformSubscription;
use App\Models\VenuePublicationRevision;
use Illuminate\Database\Eloquent\Model;

interface DurableNotificationServiceInterface
{
    /**
     * Enqueue a durable, deduplicated outbox notification intent.
     */
    public function recordOutboxNotification(
        string $key,
        int $userId,
        string $eventType,
        array $payload,
        ?int $orgId = null
    ): OutboxMessage;

    /**
     * Record an audit log entry.
     */
    public function recordAudit(
        ?int $userId,
        string $action,
        Model $auditable,
        array $metadata = []
    ): AuditLog;

    public function notifyApplicationSubmitted(HostKyc $kyc): OutboxMessage;

    public function notifyApplicationReviewed(HostKyc $kyc, string $decision, ?string $reason = null): OutboxMessage;

    public function notifySubscriptionActivated(PlatformSubscription $subscription): OutboxMessage;

    public function notifyPaymentFailed(PlatformPaymentAttempt $attempt): OutboxMessage;

    public function notifyPublicationReviewed(VenuePublicationRevision $revision, string $decision, ?string $reason = null): OutboxMessage;
}
