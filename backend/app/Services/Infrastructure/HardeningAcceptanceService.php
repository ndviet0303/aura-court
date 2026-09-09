<?php

namespace App\Services\Infrastructure;

use App\Models\AcceptanceRun;
use Symfony\Component\Process\Process;

class HardeningAcceptanceService
{
    public function run(): AcceptanceRun
    {
        $suites = [
            'api_authorization_isolation_finance' => ['vendor/bin/phpunit', 'tests/Feature'],
            'frontend_typecheck' => ['npm', 'run', 'type-check'],
            'frontend_build_accessibility_baseline' => ['npm', 'run', 'build'],
        ];
        $startedAt = now();
        $results = [];
        $passed = true;
        foreach ($suites as $name => $command) {
            $cwd = str_starts_with($command[0], 'npm') ? base_path('../frontend') : base_path();
            $process = new Process($command, $cwd, array_merge($_SERVER, $this->acceptanceEnvironment()));
            $process->setTimeout(3600);
            $code = $process->run();
            $results[$name] = ['status' => $code === 0 ? 'passed' : 'failed', 'exit_code' => $code, 'output' => substr($process->getOutput().$process->getErrorOutput(), -20000)];
            $passed = $passed && $code === 0;
        }
        return AcceptanceRun::create(['phase' => 'final-hardening', 'status' => $passed ? 'passed' : 'blocked', 'suites' => array_keys($suites), 'results' => $results, 'started_at' => $startedAt, 'completed_at' => now()]);
    }

    /** @return array<string, string> */
    private function acceptanceEnvironment(): array
    {
        return array_merge(getenv() ?: [], [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => '127.0.0.1',
            'DB_DATABASE' => 'booking_test',
            'DB_URL' => '',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
        ]);
    }
}
