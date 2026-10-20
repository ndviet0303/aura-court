<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// BR-20/23: owners reach every venue of their organization, staff only their assigned venues; platform admin rights do not stand in.
// The not_restricted route middleware (bootstrap/app.php) already refuses restricted accounts; the callback stays safe on its own.
Broadcast::channel('venue.{venueId}', fn (User $user, int $venueId) => ! $user->isRestricted() && $user->canOperateVenue($venueId));
