<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Disputes filed over HTTP (the only writer of reference_type) stored a null respondent:
        // name the organization's earliest active owner who is not platform staff.
        DB::table('dispute_cases')
            ->whereNull('respondent_id')
            ->whereNotNull('organization_id')
            ->whereNotNull('reference_type')
            ->update(['respondent_id' => DB::raw(
                "(select m.user_id from organization_members m join users u on u.id = m.user_id where m.organization_id = dispute_cases.organization_id and m.role = 'organization_owner' and m.status = 'active' and u.role not in ('admin', 'platform_admin') order by m.id limit 1)"
            )]);
    }

    public function down(): void
    {
        // Data repair only: restoring null respondents would re-break notifications and the conflict-of-interest check.
    }
};
