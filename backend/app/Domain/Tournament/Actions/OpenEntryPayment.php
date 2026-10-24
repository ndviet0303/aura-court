<?php

namespace App\Domain\Tournament\Actions;

use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\OwnerPaymentAttempt;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\ReservationPaymentService;
use App\Services\POS\PosQrPaymentService;
use Illuminate\Support\Str;

/**
 * UC-7.25, FR-125, BR-151: an approved application pays the fee to the venue's own account before the shared payment
 * deadline. The request holds no place; the place is taken when the transfer is confirmed (BR-151).
 */
class OpenEntryPayment
{
    public function __construct(
        private readonly OwnerPaymentAttemptService $attempts,
        private readonly ReservationPaymentService $payments,
        private readonly PosQrPaymentService $receivers,
    ) {}

    /** @return array<string, mixed> the payment instructions */
    public function execute(TournamentEntry $entry, User $representative): array
    {
        $tournament = $entry->tournament;
        if ((int) $entry->user_id !== (int) $representative->id) {
            TournamentAccess::fail('TOURNAMENT_FORBIDDEN', 'Bạn chỉ thanh toán được hồ sơ của chính mình.', 403);
        }
        if ($entry->status !== TournamentEntry::STATUS_APPROVED) {
            TournamentAccess::fail('ENTRY_NOT_APPROVED', 'Chỉ hồ sơ đã được duyệt mới thanh toán được.', 409);
        }
        if ($tournament->status !== Tournament::STATUS_OPEN || $tournament->payment_deadline->isPast()) {
            TournamentAccess::fail('PAYMENT_DEADLINE_PASSED', 'Đã quá hạn thanh toán của giải.', 409);
        }
        $live = $this->attempts->latestForPurpose(ConfirmEntryPayment::PURPOSE, (string) $entry->id);
        if ($live?->status === OwnerPaymentAttempt::STATUS_PENDING && $live->expires_at->isFuture()) {
            return $this->payments->instructions($live);
        }
        $connection = $this->receivers->connectionFor((int) $tournament->organization_id, (int) $tournament->venue_id);
        if ($connection === null) {
            TournamentAccess::fail('TOURNAMENT_NO_RECEIVER', 'Cơ sở chưa có tài khoản nhận tiền đã xác minh.', 409);
        }
        $reference = 'AC'.Str::upper(Str::random(18));

        return $this->payments->instructions($this->attempts->createAttempt(
            $connection, $representative, ConfirmEntryPayment::PURPOSE, (string) $entry->id, $reference,
            (int) $tournament->fee, 'VND', $tournament->payment_deadline, "tournament-entry:{$entry->id}:{$reference}",
        ));
    }
}
