<?php

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('venue.{venueId}', function (User $user, int $venueId) {
    if ($user->isAdmin() || $user->isPlatformAdmin()) {
        return true;
    }

    $venue = Venue::find($venueId);
    if (! $venue?->organization_id) {
        return false;
    }

    $member = $user->organizationMembers()
        ->where('organization_id', $venue->organization_id)
        ->where('status', 'active')
        ->first();

    // Owners reach every venue; other members only their venue scope, where an empty scope means all venues.
    return $member?->isStaff() === true
        && ($member->isOwner() || $member->venue_ids === [] || in_array($venue->id, $member->venue_ids, true));
});
