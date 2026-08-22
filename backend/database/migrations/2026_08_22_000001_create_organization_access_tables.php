<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('tax_code', 50)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 40)->default('customer')->index();
            $table->json('permissions')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('elevated_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('action', 100);
            $table->string('purpose', 255);
            $table->string('outcome', 50)->default('success');
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
        });

        // Reversible legacy data backfill:
        // Create an organization for each existing venue and link it
        $now = now();
        $venues = DB::table('venues')->get();
        foreach ($venues as $venue) {
            $orgId = DB::table('organizations')->insertGetId([
                'name' => $venue->name,
                'slug' => $venue->slug ?: 'org-' . $venue->id,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('venues')->where('id', $venue->id)->update(['organization_id' => $orgId]);

            // Assign existing admin users as organization_owner
            $adminUsers = DB::table('users')->where('role', 'admin')->get();
            foreach ($adminUsers as $admin) {
                DB::table('organization_members')->insertOrIgnore([
                    'organization_id' => $orgId,
                    'user_id' => $admin->id,
                    'role' => 'organization_owner',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::dropIfExists('elevated_audit_logs');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }
};
