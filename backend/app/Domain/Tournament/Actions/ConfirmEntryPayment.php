<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use Carbon\CarbonInterface;

/**
 * FR-125, BR-151, UC-7.26: a verified transfer for an approved application takes a place while one is left. Receipt
 * processing calls this under its own transaction; a transfer that finds no place becomes an exception for the owner.
 */
class ConfirmEntryPayment
{
    public const PURPOSE = 'tournament_registration';

    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

    /** Receipt processing has locked the attempt; the entry and then the tournament follow. */
    public function hasConfirmableRegistration(OwnerPaymentAttempt $attempt): bool
    {
        $entry = $this->play->lockEntry((int) $attempt->purpose_id);
        if ($entry === null || $entry->status !== TournamentEntry::STATUS_APPROVED || (int) $entry->user_id !== (int) $attempt->user_id) {
            return false;
        }
        $tournament = $this->tournaments->lock($entry->tournament);

        return $tournament->status === Tournament::STATUS_OPEN
            && (int) $attempt->amount === (int) $tournament->fee
            && $this->play->acceptedCount($tournament) < (int) $tournament->max_entries;
    }

    public function confirmPaidAttempt(OwnerPaymentAllocation $allocation, CarbonInterface $paidAt): void
    {
        $entry = $this->play->updateEntry($this->play->lockEntry((int) $allocation->purpose_id), ['status' => TournamentEntry::STATUS_PAID, 'paid_at' => $paidAt]);
        $this->notifications->recordOutboxNotification(
            key: "tournament.entry_paid.{$entry->id}",
            userId: (int) $entry->user_id,
            eventType: 'tournament.entry_paid',
            payload: [
                'title' => 'Đã nhận lệ phí giải đấu',
                'body' => "{$entry->tournament->name}: hồ sơ \"{$entry->name}\" đã thanh toán và có suất thi đấu.",
                'action_url' => '/tournaments/me',
                'aggregate_type' => TournamentEntry::class,
                'aggregate_id' => $entry->id,
            ],
        );
    }
}
