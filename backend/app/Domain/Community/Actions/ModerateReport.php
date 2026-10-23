<?php

namespace App\Domain\Community\Actions;

use App\Contracts\Repositories\ContentModerationRepositoryInterface;
use App\Domain\Community\Rules\CommunityAccess;
use App\Models\FeedReport;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * FR-132, BR-160, D14: the platform admin hides, restores or keeps reported content after reading it in context, and
 * the decision with its note is recorded. Every pending report of that content closes with the same decision.
 */
class ModerateReport
{
    public const HIDE = 'hide';

    public const RESTORE = 'restore';

    public const KEEP = 'keep';

    private const OUTCOMES = [self::HIDE => FeedReport::ACTION_HIDDEN, self::RESTORE => FeedReport::ACTION_RESTORED, self::KEEP => FeedReport::ACTION_KEPT];

    public function __construct(private readonly ContentModerationRepositoryInterface $moderation) {}

    public function execute(FeedReport $report, User $admin, string $decision, string $note): FeedReport
    {
        return DB::transaction(function () use ($report, $admin, $decision, $note) {
            $target = $this->moderation->findTarget($report->reportable_type, (int) $report->reportable_id);
            if ($target === null && $decision !== self::KEEP) {
                CommunityAccess::fail('CONTENT_GONE', 'Nội dung đã bị tác giả xoá.');
            }
            $hidden = $target !== null && $this->isHidden($target);
            if ($decision === self::RESTORE && ! $hidden) {
                CommunityAccess::fail('CONTENT_NOT_HIDDEN', 'Nội dung đang hiển thị, không cần khôi phục.');
            }
            if ($decision === self::KEEP && $hidden) {
                CommunityAccess::fail('CONTENT_HIDDEN', 'Nội dung đang bị ẩn; dùng Khôi phục nếu không vi phạm.');
            }
            if ($target !== null && $decision !== self::KEEP) {
                $this->moderation->setHidden($target, $decision === self::HIDE, (int) $admin->id, $note);
            }
            $this->moderation->resolve($report, self::OUTCOMES[$decision], (int) $admin->id);
            $this->moderation->record([
                'actor_id' => $admin->id,
                'target_type' => $report->reportable_type,
                'target_id' => $report->reportable_id,
                'action' => $decision,
                'reason' => $note,
                'metadata' => ['report_id' => $report->id],
            ]);

            return $report->refresh();
        });
    }

    private function isHidden(Model $target): bool
    {
        return $target instanceof Review ? ! $target->is_published : (bool) $target->getAttribute('is_hidden');
    }
}
