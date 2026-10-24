<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * FR-124, UC-7.24: the owner approves an application, which may then pay, or rejects it with a reason. The
 * representative is told either way (D11).
 */
class ReviewEntry
{
    public function __construct(
        private readonly TournamentPlayRepositoryInterface $play,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

    public function execute(TournamentEntry $entry, User $owner, bool $approve, ?string $reason = null): TournamentEntry
    {
        $tournament = $entry->tournament;
        TournamentAccess::authorize($tournament, $owner, 'review');

        return DB::transaction(function () use ($entry, $tournament, $approve, $reason): TournamentEntry {
            $locked = $this->play->lockEntry((int) $entry->id);
            if ($locked?->status !== TournamentEntry::STATUS_SUBMITTED || $tournament->status !== Tournament::STATUS_OPEN) {
                TournamentAccess::fail('ENTRY_NOT_PENDING', 'Hồ sơ không còn chờ duyệt.', 409);
            }
            $reviewed = $this->play->updateEntry($locked, $approve
                ? ['status' => TournamentEntry::STATUS_APPROVED, 'approved_at' => now()]
                : ['status' => TournamentEntry::STATUS_REJECTED, 'reject_reason' => $reason]);
            $this->notifications->recordOutboxNotification(
                key: "tournament.entry_reviewed.{$reviewed->id}",
                userId: (int) $reviewed->user_id,
                eventType: $approve ? 'tournament.entry_approved' : 'tournament.entry_rejected',
                payload: [
                    'title' => $approve ? 'Hồ sơ giải đấu đã được duyệt' : 'Hồ sơ giải đấu bị từ chối',
                    'body' => $approve
                        ? "{$tournament->name}: hồ sơ \"{$reviewed->name}\" đã được duyệt, vui lòng thanh toán lệ phí trước hạn."
                        : "{$tournament->name}: hồ sơ \"{$reviewed->name}\" bị từ chối. Lý do: {$reason}",
                    'action_url' => '/tournaments/me',
                    'aggregate_type' => TournamentEntry::class,
                    'aggregate_id' => $reviewed->id,
                ],
            );

            return $reviewed;
        });
    }
}
