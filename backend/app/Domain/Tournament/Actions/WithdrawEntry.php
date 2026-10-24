<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\TournamentEntry;
use App\Models\User;
use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Support\Facades\DB;

/** FR-123, BR-151, UC-7.23: the representative withdraws an application that is not paid yet; its open payment request expires. */
class WithdrawEntry
{
    public function __construct(
        private readonly TournamentPlayRepositoryInterface $play,
        private readonly OwnerPaymentAttemptService $payments,
    ) {}

    public function execute(TournamentEntry $entry, User $representative): TournamentEntry
    {
        if ((int) $entry->user_id !== (int) $representative->id) {
            TournamentAccess::fail('TOURNAMENT_FORBIDDEN', 'Bạn chỉ rút được hồ sơ của chính mình.', 403);
        }

        return DB::transaction(function () use ($entry): TournamentEntry {
            // Attempts first, then the entry: the order receipt processing takes them in.
            $this->payments->expirePendingForPurpose(ConfirmEntryPayment::PURPOSE, (string) $entry->id);
            $locked = $this->play->lockEntry((int) $entry->id);
            if (! in_array($locked?->status, [TournamentEntry::STATUS_SUBMITTED, TournamentEntry::STATUS_APPROVED], true)) {
                TournamentAccess::fail('ENTRY_NOT_WITHDRAWABLE', 'Hồ sơ đã thanh toán hoặc đã xử lý xong nên không rút được.', 409);
            }

            return $this->play->updateEntry($locked, ['status' => TournamentEntry::STATUS_WITHDRAWN, 'withdrawn_at' => now()]);
        });
    }
}
