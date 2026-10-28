<?php

namespace App\Domain\Tournament\Rules;

use App\Models\Tournament;
use App\Models\TournamentGrant;
use App\Models\User;

/**
 * Who may do what with one tournament (UC-7.18, D20), checked on every request. The owner manages the venue's
 * organization. An official is staff granted a public tournament and still assigned to its venue. A representative
 * is the renter's account granted an internal tournament.
 */
final class TournamentAccess
{
    public const OWNER = 'owner';

    public const PERMISSIONS = ['edit', 'publish', 'allocate', 'grant', 'review', 'roster', 'lock', 'draw', 'schedule', 'result', 'correct', 'stop'];

    private const ROLE_PERMISSIONS = [
        Tournament::TYPE_PUBLIC => [
            self::OWNER => ['edit', 'publish', 'allocate', 'grant', 'review', 'lock', 'draw', 'schedule', 'result', 'correct', 'stop'],
            TournamentGrant::ROLE_OFFICIAL => ['draw', 'schedule', 'result'],
        ],
        // BR-157: an internal tournament has no correction of a saved result.
        Tournament::TYPE_INTERNAL => [
            self::OWNER => ['edit', 'grant', 'roster', 'lock', 'draw', 'schedule', 'result', 'stop'],
            TournamentGrant::ROLE_REPRESENTATIVE => ['edit', 'roster', 'lock', 'draw', 'schedule', 'result', 'stop'],
        ],
    ];

    public static function role(Tournament $tournament, ?User $user): ?string
    {
        if ($user === null) {
            return null;
        }
        $venue = $tournament->venue;
        if ($user->canManageVenue($venue)) {
            return self::OWNER;
        }
        $role = $tournament->isPublic() ? TournamentGrant::ROLE_OFFICIAL : TournamentGrant::ROLE_REPRESENTATIVE;
        $granted = $tournament->grants()->where('user_id', $user->id)->where('role', $role)->whereNull('revoked_at')->exists();
        if (! $granted || ($role === TournamentGrant::ROLE_OFFICIAL && ! $user->canOperateVenue($venue))) {
            return null;
        }

        return $role;
    }

    /** @return array<string, bool> what the role may do in the tournament's current state; the screen shows these buttons */
    public static function permissions(Tournament $tournament, ?string $role): array
    {
        $allowed = self::ROLE_PERMISSIONS[$tournament->type][$role] ?? [];
        $state = self::stateAllows($tournament);

        return collect(self::PERMISSIONS)->mapWithKeys(fn (string $permission): array => [$permission => in_array($permission, $allowed, true) && $state[$permission]])->all();
    }

    /** The caller's role when it carries the permission: 403 otherwise, and 409 once the tournament is stopped (BR-156). */
    public static function authorize(Tournament $tournament, User $user, string $permission): string
    {
        $role = self::role($tournament, $user);
        if ($role === null || ! in_array($permission, self::ROLE_PERMISSIONS[$tournament->type][$role] ?? [], true)) {
            self::fail('TOURNAMENT_FORBIDDEN', 'Bạn không có quyền thực hiện thao tác này với giải đấu.', 403);
        }
        if ($tournament->status === Tournament::STATUS_STOPPED) {
            self::fail('TOURNAMENT_STOPPED', 'Giải đã dừng; dữ liệu được giữ nguyên và không thể thay đổi.', 409);
        }

        return $role;
    }

    /**
     * FR-122, BR-148: a published public tournament is open to everyone. A draft is seen only by those who manage it;
     * an internal tournament by its managers, the venue's operators and the renter of its bookings.
     */
    public static function canView(Tournament $tournament, ?User $user): bool
    {
        if ($tournament->isPublic() && $tournament->status !== Tournament::STATUS_DRAFT) {
            return true;
        }
        if ($user === null) {
            return false;
        }
        if (self::role($tournament, $user) !== null) {
            return true;
        }

        return ! $tournament->isPublic() && ($user->canOperateVenue($tournament->venue)
            || $tournament->rentals()->whereHas('reservation', fn ($reservation) => $reservation->where('user_id', $user->id))->exists());
    }

    /** @param  array<string, mixed>  $details  what the screen lists with the message, e.g. the conflicting bookings */
    public static function fail(string $code, string $message, int $status = 422, array $details = []): never
    {
        abort(response()->json(['message' => $message, 'code' => $code] + $details, $status));
    }

    /** @return array<string, bool> */
    private static function stateAllows(Tournament $tournament): array
    {
        $status = $tournament->status;
        $live = ! $tournament->isClosed();
        $playing = in_array($status, [Tournament::STATUS_DRAWN, Tournament::STATUS_IN_PROGRESS], true);

        return [
            'edit' => $status === Tournament::STATUS_DRAFT,
            'publish' => $status === Tournament::STATUS_DRAFT,
            'allocate' => $live,
            'grant' => $live,
            'review' => $status === Tournament::STATUS_OPEN,
            'roster' => $status === Tournament::STATUS_DRAFT,
            'lock' => $status === ($tournament->isPublic() ? Tournament::STATUS_OPEN : Tournament::STATUS_DRAFT) && ! $tournament->hasStarted(),
            'draw' => in_array($status, [Tournament::STATUS_ROSTER_LOCKED, Tournament::STATUS_DRAWN], true) && ! $tournament->hasStarted(),
            'schedule' => $playing,
            'result' => $playing,
            'correct' => in_array($status, [Tournament::STATUS_IN_PROGRESS, Tournament::STATUS_FINISHED], true),
            'stop' => $live,
        ];
    }
}
