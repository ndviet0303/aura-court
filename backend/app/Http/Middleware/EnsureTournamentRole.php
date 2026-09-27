<?php

namespace App\Http\Middleware;

use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Services\Infrastructure\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTournamentRole
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next, ?string $requiredRole = null): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        $tournament = $request->route('tournament');
        $participant = $request->route('participant');

        if (! ($tournament instanceof Tournament)) {
            if ($tournament !== null) {
                $val = is_object($tournament) ? ($tournament->uuid ?? $tournament->id ?? null) : $tournament;
                $tournament = Tournament::where('uuid', (string) $val)
                    ->orWhere('id', is_numeric($val) ? (int) $val : 0)
                    ->first();
            }
        }

        if (! $tournament && $participant) {
            if (! ($participant instanceof TournamentParticipant)) {
                $participant = TournamentParticipant::find($participant);
            }
            if ($participant) {
                // If participant is performing an action on their own record (like self check-in)
                if ($participant->user_id === $user->id) {
                    return $next($request);
                }
                $tournament = $participant->tournament;
            }
        }

        if (! $tournament) {
            abort(404, 'Tournament not found.');
        }

        $organization = $this->tenantContext->getOrganization();
        if ($organization && $tournament->organization_id !== $organization->id) {
            abort(404, 'Tournament does not belong to the active organization.');
        }

        if (! $tournament->isAuthorizedOfficial($user, $requiredRole)) {
            abort(403, 'Insufficient tournament permissions.');
        }

        return $next($request);
    }
}
