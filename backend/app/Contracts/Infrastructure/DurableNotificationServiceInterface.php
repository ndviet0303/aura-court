<?php

namespace App\Contracts\Infrastructure;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\HostKyc;
use App\Models\OutboxMessage;
use App\Models\SuspensionRemediation;
use App\Models\VenueSuspension;
use Illuminate\Database\Eloquent\Model;

interface DurableNotificationServiceInterface
{
    /**
     * Enqueue a durable, deduplicated outbox notification intent.
     *
     * @param  list<string>  $channels  delivery channels the dispatcher sends: database (in-app) and/or mail
     */
    public function recordOutboxNotification(
        string $key,
        int $userId,
        string $eventType,
        array $payload,
        ?int $orgId = null,
        array $channels = ['database']
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

    public function notifyBooking(Booking $booking, string $event, string $title, string $body, ?string $actionUrl = '/booking'): OutboxMessage;

    /** D13/D14 notifications below go to every active owner of the venue's organization, or every platform admin. */
    public function notifyVenueSuspended(VenueSuspension $suspension): void;

    public function notifyRemediationSubmitted(SuspensionRemediation $remediation): void;

    public function notifyRemediationReviewed(SuspensionRemediation $remediation): void;
}
