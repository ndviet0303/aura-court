<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Enable Row-Level Security on tenant-owned tables
            $tables = ['venues', 'organization_members'];

            foreach ($tables as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");
                DB::statement("
                    DO $$
                    BEGIN
                        IF NOT EXISTS (
                            SELECT 1 FROM pg_policies WHERE tablename = '{$table}' AND policyname = 'tenant_isolation_policy'
                        ) THEN
                            CREATE POLICY tenant_isolation_policy ON {$table}
                            FOR ALL
                            USING (
                                organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::bigint
                                OR current_setting('app.is_elevated', true) = 'true'
                            );
                        END IF;
                    END
                    $$;
                ");
            }
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $tables = ['venues', 'organization_members'];
            foreach ($tables as $table) {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table};");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY;");
            }
        }
    }
};
