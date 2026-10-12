<?php

namespace App\Domain\Admin\Actions;

use App\Models\DisputeCase;
use App\Services\Infrastructure\OutboxService;

class NotifyDisputeFiled
{
    public function __construct(
        private readonly OutboxService $outbox,
    ) {}

    public function execute(DisputeCase $dispute): void
    {
        if ($dispute->status === 'awaiting_evidence') {
            $this->outbox->enqueue([
                'organization_id' => $dispute->organization_id,
                'message_key' => "dispute_evidence_requested:{$dispute->id}:".now()->timestamp,
                'channel' => 'notification',
                'event_type' => 'dispute.evidence_requested',
                'aggregate_type' => DisputeCase::class,
                'aggregate_id' => $dispute->id,
            ], [
                'user_id' => $dispute->complainant_id,
                'recipient_id' => $dispute->complainant_id,
                'dispute_id' => $dispute->id,
                'dispute_code' => $dispute->dispute_code,
                'requested_evidence' => $dispute->requested_evidence,
                'sla_deadline' => $dispute->sla_deadline?->toIso8601String(),
                'title' => 'Yêu cầu bổ sung chứng cứ',
                'body' => 'Hồ sơ tranh chấp của bạn cần bổ sung chứng cứ xác thực.',
                'message' => 'Hồ sơ tranh chấp cần bổ sung tài liệu minh chứng trước thời hạn.',
            ]);
        }

        // The respondent must learn of the case to answer it before any decision.
        if ($dispute->respondent_id) {
            $this->outbox->enqueue([
                'organization_id' => $dispute->organization_id,
                'message_key' => "dispute_filed_respondent:{$dispute->id}",
                'channel' => 'notification',
                'event_type' => 'dispute.filed',
                'aggregate_type' => DisputeCase::class,
                'aggregate_id' => $dispute->id,
            ], [
                'user_id' => $dispute->respondent_id,
                'recipient_id' => $dispute->respondent_id,
                'dispute_id' => $dispute->id,
                'dispute_code' => $dispute->dispute_code,
                'title' => 'Có khiếu nại mới về cơ sở của bạn',
                'body' => "Khách hàng đã gửi khiếu nại {$dispute->dispute_code} về {$dispute->facility_name}. Vui lòng phản hồi và bổ sung chứng cứ.",
            ]);
        }
    }
}
