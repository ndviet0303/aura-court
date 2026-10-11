<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Explicit production acceptance setup only creates newly namespaced synthetic fixtures. */
class E2ESeedProductionFixturesCommand extends E2ESeedFixturesCommand
{
    protected $signature = 'e2e:seed-production-fixtures
        {--run-id= : New prod_e2e_ run namespace}
        {--expected-db= : Explicit authorized database}
        {--expected-host= : Explicit authorized database host}
        {--organization-ids= : Two existing dedicated E2E tenant IDs, never business tenants}';

    protected $description = 'Create fresh synthetic actors and tenants for an explicitly selected production E2E target';

    public function handle(): int
    {
        $runId = (string) $this->option('run-id');
        $expectedDb = (string) $this->option('expected-db');
        $expectedHost = (string) $this->option('expected-host');
        if (! preg_match('/^prod_e2e_[0-9]+_[a-f0-9]{8}$/', $runId)
            || $expectedDb === '' || $expectedHost === '' || ! app()->environment('testing')) {
            $this->error('Production fixtures require a new prod_e2e_ namespace, explicit database/host and the testing CLI environment.');

            return self::FAILURE;
        }
        $config = DB::connection()->getConfig();
        if (($config['host'] ?? '') !== $expectedHost || ($config['database'] ?? '') !== $expectedDb
            || DB::scalar('SELECT current_database()') !== $expectedDb) {
            $this->error('Production fixture target does not match the explicitly selected host/database.');

            return self::FAILURE;
        }
        // Reusing a namespace could overwrite actor credentials; production setup must only create new actors.
        if (DB::table('users')->where('email', 'like', "%_{$runId}@auracourt.test")->exists()
            || DB::table('organizations')->where('slug', "aura-primary-{$runId}")->exists()) {
            $this->error('Production fixture namespace already exists; choose a new run.');

            return self::FAILURE;
        }
        $organizationIds = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('organization-ids')))));
        if ($organizationIds !== []) {
            if (count($organizationIds) !== 2 || count(array_unique($organizationIds)) !== 2) {
                $this->error('Production acceptance requires exactly two distinct E2E tenant IDs.');

                return self::FAILURE;
            }
            foreach ($organizationIds as $organizationId) {
                $organization = Organization::find($organizationId);
                $previousRun = (string) ($organization?->settings['e2e_run_id'] ?? '');
                if (! preg_match('/^prod_e2e_[0-9]+_[a-f0-9]{8}$/', $previousRun)) {
                    $this->error('Refusing to reset a business organization; only existing E2E-owned tenants may be reused.');

                    return self::FAILURE;
                }
            }
        }

        return $this->seedFixtures($runId, [], Str::random(48), $organizationIds);
    }
}
