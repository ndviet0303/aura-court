<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'tournament_id', 'user_id']);
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement(
                'CREATE UNIQUE INDEX tournament_grants_active_unique ON tournament_grants (tournament_id, user_id, role) WHERE revoked_at IS NULL'
            );
        }

        Schema::table('tournament_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_participants', 'waiver_reason')) {
                $table->string('waiver_reason')->nullable()->after('checked_in_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            if (Schema::hasColumn('tournament_participants', 'waiver_reason')) {
                $table->dropColumn('waiver_reason');
            }
        });

        Schema::dropIfExists('tournament_grants');
    }
};
