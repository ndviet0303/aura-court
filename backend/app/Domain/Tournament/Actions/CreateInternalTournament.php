<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\DTOs\TournamentSetupData;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Reservation;
use App\Models\Tournament;
use App\Models\TournamentGrant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FR-121, BR-148, UC-7.17, D21: the owner opens an internal tournament on one renter's confirmed bookings at one
 * venue, and grants it to a representative (the renter unless another account is named). It plays only inside
 * those bookings; it has no public entry and no fee.
 */
class CreateInternalTournament
{
    public function __construct(private readonly TournamentRepositoryInterface $tournaments) {}

    /** @param  list<string>  $codes */
    public function execute(User $owner, TournamentSetupData $setup, array $codes, ?string $representativeEmail): Tournament
    {
        $codes = array_values(array_unique(array_map('trim', $codes)));

        return DB::transaction(function () use ($owner, $setup, $codes, $representativeEmail): Tournament {
            $rentals = $this->tournaments->reservationsByCode($codes);
            $venueId = $this->assertRentals($rentals, count($codes), (string) $setup->attributes['sport']);
            if (! $owner->canManageVenue($venueId)) {
                TournamentAccess::fail('TOURNAMENT_FORBIDDEN', 'Chỉ chủ cơ sở được tạo giải nội bộ từ đơn thuê của cơ sở.', 403);
            }
            $representative = $representativeEmail === null || trim($representativeEmail) === ''
                ? $rentals->first()->user
                : $this->tournaments->findUserByEmail($representativeEmail);
            if ($representative === null) {
                TournamentAccess::fail('GRANT_INVALID', 'Không tìm thấy tài khoản đại diện với email này.');
            }
            $slots = $rentals->flatMap->slots;
            $tournament = $this->tournaments->create($setup->attributes + [
                'type' => Tournament::TYPE_INTERNAL,
                'organization_id' => $rentals->first()->organization_id,
                'venue_id' => $venueId,
                'created_by' => $owner->id,
                'status' => Tournament::STATUS_DRAFT,
                'fee' => 0,
                'starts_at' => $slots->min('starts_at'),
                'ends_at' => $slots->max('ends_at'),
            ]);
            $this->tournaments->attachRentals($tournament, $rentals->pluck('id')->map(fn ($id): int => (int) $id)->all());
            $this->tournaments->grant($tournament, $representative, TournamentGrant::ROLE_REPRESENTATIVE, $owner);

            return $tournament;
        });
    }

    /** @param  Collection<int, Reservation>  $rentals */
    private function assertRentals(Collection $rentals, int $asked, string $sport): int
    {
        $courts = $rentals->flatMap->slots->pluck('court');
        $venues = $courts->pluck('venue_id')->unique();
        $valid = $asked > 0 && $rentals->count() === $asked
            && $rentals->every(fn (Reservation $reservation): bool => $reservation->status === 'confirmed' && $reservation->slots->isNotEmpty())
            && $rentals->pluck('user_id')->unique()->count() === 1
            && $venues->count() === 1
            && $courts->every(fn ($court): bool => str_starts_with((string) $court?->sport_type, $sport))
            && ! $this->tournaments->anyRented($rentals->pluck('id')->map(fn ($id): int => (int) $id)->all());
        if (! $valid) {
            TournamentAccess::fail('INTERNAL_RENTALS_INVALID', 'Chọn các đơn đã xác nhận của cùng một khách, tại cùng một cơ sở, đúng môn thi đấu và chưa thuộc giải khác.');
        }

        return (int) $venues->first();
    }
}
