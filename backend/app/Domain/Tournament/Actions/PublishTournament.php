<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use App\Models\User;
use App\Services\POS\PosQrPaymentService;
use Illuminate\Support\Facades\DB;

/**
 * FR-120, BR-149, BR-152: a public draft is published once its court time is reserved, its venue can collect the
 * fee on AuraCourt (BR-148), and registration closes before payment, which closes before play starts.
 */
class PublishTournament
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly PosQrPaymentService $receivers,
    ) {}

    public function execute(Tournament $tournament, User $owner): Tournament
    {
        TournamentAccess::authorize($tournament, $owner, 'publish');

        return DB::transaction(function () use ($tournament): Tournament {
            $locked = $this->tournaments->lock($tournament);
            if ($locked->status !== Tournament::STATUS_DRAFT) {
                TournamentAccess::fail('TOURNAMENT_NOT_DRAFT', 'Giải đã được công bố.', 409);
            }
            if ($this->tournaments->allocations($locked)->isEmpty()) {
                TournamentAccess::fail('TOURNAMENT_NO_COURTS', 'Cần dành sân cho giải trước khi công bố.');
            }
            if ($this->receivers->connectionFor((int) $locked->organization_id, (int) $locked->venue_id) === null) {
                TournamentAccess::fail('TOURNAMENT_NO_RECEIVER', 'Cơ sở chưa có tài khoản nhận tiền đã xác minh nên chưa thể thu lệ phí.');
            }
            $ordered = $locked->registration_deadline->isFuture()
                && $locked->registration_deadline->lte($locked->payment_deadline)
                && $locked->payment_deadline->lte($locked->starts_at);
            if (! $ordered) {
                TournamentAccess::fail('TOURNAMENT_DEADLINES', 'Hạn đăng ký phải ở tương lai, trước hạn thanh toán, và hạn thanh toán trước ngày thi đấu.');
            }

            return $this->tournaments->update($locked, ['status' => Tournament::STATUS_OPEN, 'published_at' => now()]);
        });
    }
}
