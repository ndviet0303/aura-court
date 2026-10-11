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
    if (! $venue) {
        return false;
    }

    return $user->hasRoleInOrganization($venue->organization_id, ['owner', 'admin', 'manager', 'cashier', 'staff']);
});
