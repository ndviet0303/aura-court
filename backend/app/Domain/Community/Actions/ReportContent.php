<?php

namespace App\Domain\Community\Actions;

use App\Contracts\Repositories\ContentModerationRepositoryInterface;
use App\Domain\Community\Rules\CommunityAccess;
use App\Models\FeedReport;
use App\Models\User;

/** FR-132, BR-160: a report names one post, comment or review and why; it never hides anything by itself. */
class ReportContent
{
    public function __construct(private readonly ContentModerationRepositoryInterface $moderation) {}

    public function execute(User $reporter, string $type, int $id, string $reason): FeedReport
    {
        $class = FeedReport::TYPES[$type];
        $target = $this->moderation->findTarget($class, $id);
        if ($target === null) {
            abort(404, 'Không tìm thấy nội dung cần báo cáo.');
        }
        if ((int) $target->getAttribute('user_id') === (int) $reporter->id) {
            CommunityAccess::fail('REPORT_OWN_CONTENT', 'Bạn không thể báo cáo nội dung của chính mình.');
        }
        if ($this->moderation->hasPendingReport($reporter->id, $class, $id)) {
            CommunityAccess::fail('REPORT_DUPLICATE', 'Bạn đã báo cáo nội dung này và đang chờ quản trị viên kiểm tra.', 409);
        }

        return $this->moderation->createReport([
            'reporter_id' => $reporter->id,
            'reportable_type' => $class,
            'reportable_id' => $id,
            'reason' => $reason,
            'status' => FeedReport::STATUS_PENDING,
        ]);
    }
}
