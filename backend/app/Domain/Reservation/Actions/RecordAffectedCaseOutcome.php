<?php

namespace App\Domain\Reservation\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\AffectedReservationCaseRepositoryInterface;
use App\Models\AffectedReservationCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Records a contact outcome on an open case. The booking, its slots and its money stay exactly as they are. */
class RecordAffectedCaseOutcome
{
    public function __construct(
        private readonly AffectedReservationCaseRepositoryInterface $cases,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

    /** @param  array{status: string, channel: string, note: string, refund_note?: ?string}  $data */
    public function execute(User $actor, int $caseId, array $data): AffectedReservationCase
    {
        return DB::transaction(function () use ($actor, $caseId, $data): AffectedReservationCase {
            $case = $this->cases->find($caseId, lock: true);
            abort_unless($case instanceof AffectedReservationCase, 404);
            abort_unless($actor->canOperateVenue((int) $case->venue_id), 403, 'Bạn không có quyền xử lý đơn tại cơ sở này.');
            $refundNote = $data['refund_note'] ?? null;
            abort_if(filled($refundNote) && ! $actor->canManageVenue((int) $case->venue_id), 403, 'Chỉ chủ cơ sở được ghi chú hoàn tiền ngoài nền tảng.');
            abort_unless($case->isOpen(), 409, 'Case này đã được xử lý xong.');

            $case = $this->cases->update($case, [
                'status' => $data['status'],
                'contact_channel' => $data['channel'],
                'note' => $data['note'],
                'handled_by' => $actor->id,
                'handled_at' => now(),
            ] + (filled($refundNote) ? ['refund_note' => $refundNote] : []));
            $this->notifications->recordAudit($actor->id, 'affected_reservation_case.'.$data['status'], $case, [
                'reservation_id' => (int) $case->reservation_id,
                'channel' => $data['channel'],
                'refund_note' => filled($refundNote),
            ]);

            return $case;
        });
    }
}
