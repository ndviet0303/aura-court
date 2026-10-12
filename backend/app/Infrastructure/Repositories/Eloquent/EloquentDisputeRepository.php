<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Models\DisputeCase;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentDisputeRepository implements DisputeRepositoryInterface
{
    public function userCanAccess(DisputeCase $dispute, User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // 1. Party in the dispute
        if ((int) $dispute->complainant_id === (int) $user->id || $this->isRespondent($dispute, $user)) {
            return true;
        }
        // 2. Assigned reviewer
        if ($dispute->assigned_reviewer_id && (int) $dispute->assigned_reviewer_id === (int) $user->id) {
            return true;
        }

        // 3. Organization owner or venue manager
        if ($dispute->organization_id) {
            if ($user->hasRoleInOrganization($dispute->organization_id, [OrganizationMember::ROLE_ORGANIZATION_OWNER])) {
                return true;
            }

            if ($dispute->venue_id && $user->canManageVenue($dispute->venue_id)) {
                return true;
            }

            if ($dispute->venue_id === null && $user->hasRoleInOrganization($dispute->organization_id, [OrganizationMember::ROLE_VENUE_MANAGER])) {
                return true;
            }
        }

        return false;
    }

    public function isRespondent(DisputeCase $dispute, User $user): bool
    {
        // An organization answers through whoever owns it now; respondent_id only records who was told.
        return $dispute->organization_id
            ? $user->hasRoleInOrganization($dispute->organization_id, [OrganizationMember::ROLE_ORGANIZATION_OWNER])
            : (int) $dispute->respondent_id === (int) $user->id;
    }

    public function findRespondentIdForOrganization(int $organizationId): ?int
    {
        $userId = OrganizationMember::query()
            ->where('organization_id', $organizationId)
            ->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)
            ->where('status', 'active')
            // Platform staff must stay free to arbitrate, so they never answer for a venue.
            ->whereHas('user', fn ($q) => $q->whereNotIn('role', ['admin', 'platform_admin']))
            ->orderBy('id')
            ->value('user_id');

        return $userId === null ? null : (int) $userId;
    }

    public function applyUserScope($query, ?User $user, ?int $organizationId = null, ?int $venueId = null)
    {
        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        if ($user->isPlatformAdmin()) {
            if ($organizationId) {
                $query->where('organization_id', $organizationId);
            }
            if ($venueId) {
                $query->where('venue_id', $venueId);
            }

            return $query;
        }

        $orgIds = $user->organizations()
            ->where('organization_members.status', 'active')
            ->whereIn('organization_members.role', [OrganizationMember::ROLE_ORGANIZATION_OWNER, OrganizationMember::ROLE_VENUE_MANAGER])
            ->pluck('organizations.id')
            ->toArray();

        $managedVenueIds = [];
        $isFullOrgOwner = false;
        foreach ($user->organizationMembers()->where('status', 'active')->get() as $member) {
            if ($member->role === OrganizationMember::ROLE_ORGANIZATION_OWNER) {
                $isFullOrgOwner = true;
            }
            if (! empty($member->venue_ids)) {
                $managedVenueIds = array_merge($managedVenueIds, $member->venue_ids);
            }
        }
        $managedVenueIds = array_values(array_unique(array_filter($managedVenueIds)));

        return $query->where(function ($q) use ($user, $orgIds, $managedVenueIds, $isFullOrgOwner, $organizationId, $venueId) {
            // Party in dispute
            // An organization's respondent sees its cases through the owner clause below, which tracks current grants.
            $q->where('complainant_id', $user->id)
                ->orWhere(fn ($r) => $r->where('respondent_id', $user->id)->whereNull('organization_id'))
                ->orWhere('assigned_reviewer_id', $user->id);
            // Organization owner / venue manager
            if (! empty($orgIds)) {
                $targetOrgs = $organizationId ? array_values(array_intersect($orgIds, [$organizationId])) : $orgIds;
                if (! empty($targetOrgs)) {
                    $q->orWhere(function ($subQ) use ($targetOrgs, $managedVenueIds, $isFullOrgOwner, $venueId) {
                        $subQ->whereIn('organization_id', $targetOrgs);
                        if ($venueId) {
                            $subQ->where('venue_id', $venueId);
                        } elseif (! $isFullOrgOwner && ! empty($managedVenueIds)) {
                            $subQ->where(function ($vQ) use ($managedVenueIds) {
                                $vQ->whereIn('venue_id', $managedVenueIds)
                                    ->orWhereNull('venue_id');
                            });
                        }
                    });
                }
            }
        });
    }

    public function findById(int $id, ?User $user = null): ?DisputeCase
    {
        $query = DisputeCase::query()
            ->with(['organization', 'venue', 'booking', 'complainant', 'respondent', 'arbitrator']);

        if ($user) {
            $this->applyUserScope($query, $user);
        }

        return $query->find($id);
    }

    public function findByCode(string $code, ?User $user = null): ?DisputeCase
    {
        $query = DisputeCase::query()
            ->with(['organization', 'venue', 'booking', 'complainant', 'respondent', 'arbitrator'])
            ->where('dispute_code', $code);

        if ($user) {
            $this->applyUserScope($query, $user);
        }

        return $query->first();
    }

    public function paginate(
        ?string $status = null,
        ?string $search = null,
        ?string $severity = null,
        ?string $disputeType = null,
        int $perPage = 30,
        ?User $user = null,
        ?int $organizationId = null,
        ?int $venueId = null
    ): LengthAwarePaginator {
        $query = DisputeCase::query()->with(['organization', 'venue', 'booking', 'complainant', 'respondent', 'arbitrator']);

        if ($user) {
            $this->applyUserScope($query, $user, $organizationId, $venueId);
        } elseif ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($severity && $severity !== 'all') {
            $query->where('severity', $severity);
        }

        if ($disputeType && $disputeType !== 'all') {
            $query->where('dispute_type', $disputeType);
        }

        if ($search) {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('dispute_code', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('complainant_name', 'like', $term)
                    ->orWhere('respondent_name', 'like', $term)
                    ->orWhere('facility_name', 'like', $term);
            });
        }

        return $query->orderByRaw("CASE WHEN severity = 'critical' THEN 1 WHEN severity = 'high' THEN 2 WHEN status = 'pending' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getStats(?User $user = null, ?int $organizationId = null): array
    {
        $baseQuery = function () use ($user, $organizationId) {
            $q = DisputeCase::query();
            if ($user) {
                $this->applyUserScope($q, $user, $organizationId);
            } elseif ($organizationId) {
                $q->where('organization_id', $organizationId);
            }

            return $q;
        };

        $pending = $baseQuery()->where('status', 'pending')->count();
        $investigating = $baseQuery()->where('status', 'investigating')->count();
        $awaitingReply = $baseQuery()->where('status', 'awaiting_response')->count();
        $resolved = $baseQuery()->where('status', 'resolved')->count();
        $critical = $baseQuery()->where('severity', 'critical')->whereIn('status', ['pending', 'investigating'])->count();

        return [
            'pending_count' => $pending,
            'investigating_count' => $investigating,
            'awaiting_reply_count' => $awaitingReply,
            'resolved_count' => $resolved,
            'critical_sla_count' => $critical,
            'avg_resolution_time_minutes' => 18.5,
            'consensus_rate' => 98.2,
        ];
    }

    public function create(array $attributes): DisputeCase
    {
        return DisputeCase::create($attributes);
    }

    public function update(DisputeCase $dispute, array $attributes): DisputeCase
    {
        $dispute->update($attributes);

        return $dispute->fresh(['organization', 'venue', 'booking', 'complainant', 'respondent', 'arbitrator']);
    }
}
