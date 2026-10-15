<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** BR-19: an invitee may decline an invitation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_invitations', function (Blueprint $table) {
            $table->timestamp('declined_at')->nullable()->after('revoked_at');
        });
        DB::statement('ALTER TABLE organization_invitations DROP CONSTRAINT organization_invitations_status_check');
        DB::statement("ALTER TABLE organization_invitations ADD CONSTRAINT organization_invitations_status_check CHECK (status IN ('accepted', 'declined', 'expired', 'pending', 'revoked'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE organization_invitations DROP CONSTRAINT organization_invitations_status_check');
        DB::statement("ALTER TABLE organization_invitations ADD CONSTRAINT organization_invitations_status_check CHECK (status IN ('accepted', 'expired', 'pending', 'revoked'))");
        Schema::table('organization_invitations', function (Blueprint $table) {
            $table->dropColumn('declined_at');
        });
    }
};
