<?php

namespace App\Services\Tournament;

use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\ReservationPaymentService;
use App\Services\POS\PosQrPaymentService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Paid tournament entry is a `tournament_registration` owner-payment attempt on the tournament venue's verified connection. */
class TournamentRegistrationPaymentService
{
    private const PURPOSE = 'tournament_registration';

    public function __construct(
        private readonly TournamentService $tournaments,
        private readonly OwnerPaymentAttemptService $attempts,
        private readonly ReservationPaymentService $payments,
        private readonly PosQrPaymentService $connections,
    ) {}

    /** A paid tournament needs a venue of its organization with a verified receiving connection. */
    public function assertVenueCanCollect(int $organizationId, ?int $venueId, int $entryFee): ?Venue
    {
        $venue = $venueId ? Venue::query()->where('organization_id', $organizationId)->find($venueId) : null;
        abort_if($venueId && ! $venue, 422, 'Cơ sở không thuộc tổ chức này.');
        abort_if($entryFee > 0 && ! $venue, 422, 'Giải có phí đăng ký phải chọn cơ sở thu tiền.');
        abort_if($entryFee > 0 && ! $this->connections->connectionFor($organizationId, $venue->id), 422, 'Cơ sở chưa có tài khoản nhận tiền đã xác minh nên không thể thu phí đăng ký.');

        return $venue;
    }

    /** Free entry registers now; paid entry returns the caller's payment instructions and registers when the receipt lands. */
    public function register(Tournament $tournament, User $user): array
    {
        if (TournamentService::parseEntryFee($tournament->settings['entry_fee'] ?? 0) === 0) {
            $already = $this->tournaments->register($tournament, $user)['already_registered'];
        } else {
            $already = $this->open($tournament, $user) === null;
        }

        return $this->status($tournament->fresh(), $user) + ['already_registered' => $already];
    }

    /** Under the tournament lock: returns the caller's live attempt or opens one if capacity allows; null when already registered. */
    public function open(Tournament $tournament, User $user): ?OwnerPaymentAttempt
    {
        return DB::transaction(function () use ($tournament, $user): ?OwnerPaymentAttempt {
            $tournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();
            if ($this->tournaments->activeParticipant($tournament, $user->id)) {
                return null;
            }
            $live = $this->latestFor($tournament, $user->id);
            if ($live?->status === OwnerPaymentAttempt::STATUS_PENDING && $live->expires_at->isFuture()) {
                return $live;
            }

            $this->tournaments->validateAdmission($tournament);
            $fee = TournamentService::parseEntryFee($tournament->settings['entry_fee'] ?? 0);
            $connection = $tournament->venue_id ? $this->connections->connectionFor((int) $tournament->organization_id, (int) $tournament->venue_id) : null;
            abort_unless($connection !== null, 409, 'Cơ sở tổ chức giải chưa có tài khoản nhận tiền đã xác minh nên chưa thể thu phí đăng ký.');

            $expiresAt = now()->addMinutes(15);
            $closes = TournamentService::registrationClosesAt($tournament);
            if ($closes && $closes->lt($expiresAt)) {
                $expiresAt = $closes;
            }
            $reference = 'AC'.Str::upper(Str::random(18));

            return $this->attempts->createAttempt(
                $connection, $user, self::PURPOSE, (string) $tournament->uuid, $reference,
                $fee, 'VND', $expiresAt, 'tournament:'.$tournament->id.':'.$user->id.':'.$reference,
            );
        });
    }

    /** What the payment page polls: the registration once confirmed, otherwise the caller's latest attempt. */
    public function status(Tournament $tournament, User $user): array
    {
        $participant = $this->tournaments->activeParticipant($tournament, $user->id);
        $attempt = $this->latestFor($tournament, $user->id);

        return [
            'registered' => $participant !== null,
            'participant' => $participant?->load('user'),
            'receipt' => $participant ? [
                'receipt_code' => "RCP-TRN-{$tournament->id}-{$participant->id}",
                'tournament_id' => $tournament->id,
                'tournament_name' => $tournament->name,
                'participant_id' => $participant->id,
                'entry_fee' => $participant->waiver_reason ? 0 : TournamentService::parseEntryFee($tournament->settings['entry_fee'] ?? 0),
                'currency' => 'VND',
                'status' => 'confirmed',
                'payment_reference' => $attempt?->status === OwnerPaymentAttempt::STATUS_PAID ? $attempt->reference : null,
                'registered_at' => $participant->created_at?->toIso8601String(),
            ] : null,
            'payment' => $attempt && ! $participant ? $this->payments->instructions($attempt) : null,
        ];
    }

    /** Receipt processing has locked the attempt; the tournament lock follows, as in open(). */
    public function hasConfirmableRegistration(OwnerPaymentAttempt $attempt): bool
    {
        $tournament = $this->lockTournament($attempt);
        if (! $tournament || $this->tournaments->activeParticipant($tournament, (int) $attempt->user_id)) {
            return false;
        }
        try {
            $this->tournaments->validateAdmission($tournament, (int) $attempt->id);
        } catch (HttpException) {
            return false;
        }

        return true;
    }

    public function confirmPaidAttempt(OwnerPaymentAllocation $allocation, CarbonInterface $paidAt): void
    {
        $attempt = OwnerPaymentAttempt::query()->findOrFail($allocation->payment_attempt_id);
        $this->tournaments->admit($this->lockTournament($attempt), (int) $attempt->user_id);
    }

    private function latestFor(Tournament $tournament, int $userId): ?OwnerPaymentAttempt
    {
        return OwnerPaymentAttempt::query()
            ->where('organization_id', $tournament->organization_id)
            ->where('purpose', self::PURPOSE)
            ->where('purpose_id', $tournament->uuid)
            ->where('user_id', $userId)
            ->latest('id')
            ->first();
    }

    private function lockTournament(OwnerPaymentAttempt $attempt): ?Tournament
    {
        return Tournament::query()->withoutGlobalScope('tenant_isolation')
            ->where('organization_id', $attempt->organization_id)
            ->where('uuid', $attempt->purpose_id)
            ->lockForUpdate()
            ->first();
    }
}
