<?php

namespace App\Services\Infrastructure;

use App\Models\AcceptanceRun;
use Symfony\Component\Process\Process;

class PilotAcceptanceService
{
    public const SUITES = [
        'tenant_isolation' => ['tests/Feature/TenantIsolationAndRoleMatrixTest.php'],
        'financial_integrity' => ['tests/Feature/FinancialFoundationTest.php'],
        'reservations_and_v1' => ['tests/Feature/V2ReservationsAndPricingTest.php', 'tests/Feature/Readiness/LegacyMigrationRehearsalTest.php'],
        'security_static' => ['lint'],
    ];

    public function run(bool $includeLoad = false, bool $includeRecovery = false): AcceptanceRun
    {
        $suites = self::SUITES;
        if ($includeLoad) {
            $suites['load'] = ['devops/load/k6-readiness-benchmark.js'];
        }
        if ($includeRecovery) {
            $suites['recovery'] = ['devops/scripts/backup-restore-rehearsal.sh'];
        }
        $startedAt = now();
        $results = [];
        $passed = true;
        foreach ($suites as $name => $commands) {
            $result = $this->runSuite($name, $commands);
            $results[$name] = $result;
            $passed = $passed && $result['status'] === 'passed';
        }

        return AcceptanceRun::create(['phase' => 'phase-1', 'status' => $passed ? 'passed' : 'blocked', 'suites' => array_keys($suites), 'results' => $results, 'started_at' => $startedAt, 'completed_at' => now()]);
    }

    private function runSuite(string $name, array $commands): array
    {
        if ($commands === ['lint']) {
            $process = new Process([base_path('vendor/bin/pint'), '--test'], base_path());
        } elseif (str_ends_with($commands[0], '.js')) {
            $process = new Process(['k6', 'run', base_path('../'.$commands[0])], base_path());
        } elseif (str_ends_with($commands[0], '.sh')) {
            $process = new Process(['bash', base_path('../'.$commands[0])], base_path());
        } else {
            $process = new Process(array_merge([PHP_BINARY, base_path('artisan'), 'test'], array_map(fn (string $file) => base_path($file), $commands)), base_path());
        }
        $process->setTimeout(3600);
        try {
            $exitCode = $process->run();

            return ['status' => $exitCode === 0 ? 'passed' : 'failed', 'exit_code' => $exitCode, 'output' => substr($process->getOutput().$process->getErrorOutput(), -20000)];
        } catch (\Throwable $exception) {
            return ['status' => 'blocked', 'error' => $exception->getMessage()];
        }
    }
}
