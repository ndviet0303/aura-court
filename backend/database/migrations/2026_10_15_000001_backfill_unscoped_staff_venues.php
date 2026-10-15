<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Staff without a venue scope used to reach every venue of their organization.
 * Unscoped now means no venue (BR-20), so write today's venues out explicitly
 * to keep existing staff working exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('organization_members')
            ->whereNotIn('role', ['organization_owner', 'customer'])
            ->orderBy('id')
            ->each(function (object $member): void {
                $permissions = json_decode((string) $member->permissions, true) ?: [];
                if (! empty($permissions['venue_ids']) || ! empty($permissions['venue_id'])) {
                    return;
                }

                $venueIds = DB::table('venues')
                    ->where('organization_id', $member->organization_id)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                if ($venueIds === []) {
                    return;
                }

                $permissions['venue_ids'] = $venueIds;
                $permissions['venue_id'] = $venueIds[0];
                DB::table('organization_members')
                    ->where('id', $member->id)
                    ->update(['permissions' => json_encode($permissions)]);
            });
    }

    public function down(): void
    {
        // Irreversible on purpose: the explicit list is what the old behaviour granted.
    }
};
