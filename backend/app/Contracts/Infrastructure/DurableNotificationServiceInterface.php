<?php

namespace App\Contracts\Infrastructure;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\HostKyc;
use App\Models\OutboxMessage;
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
}
