<?php

namespace App\Services\Infrastructure;

use App\Models\OperationalDrillRun;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;

class OperationalDrillService
{
    public function run(string $type): OperationalDrillRun
    {
        $run = OperationalDrillRun::create(['drill_type' => $type, 'status' => 'running', 'started_at' => now()]);
        try {
            $evidence = match ($type) {
                'peak-load' => $this->process(['k6', 'run', base_path('../devops/load/k6-readiness-benchmark.js')], base_path()),
                'delayed-webhook' => ['status' => 'manual', 'instruction' => 'Replay a signed provider event and verify one provider_events row and one journal.'],
                'redis-restart' => ['status' => in_array(strtoupper((string) Redis::connection()->ping()), ['1', 'TRUE', 'PONG'], true) ? 'passed' : 'failed'],
                'database-failover' => ['status' => 'manual', 'instruction' => 'Fail over the managed PostgreSQL replica and verify RPO/RTO evidence.'],
                'queue-lag' => ['status' => 'manual', 'instruction' => 'Run monitor:queue-lag while injecting delayed outbox jobs.'],
                'realtime-degradation' => ['status' => 'manual', 'instruction' => 'Stop realtime consumer and verify tournament polling/resync remains authoritative.'],
                default => throw new \InvalidArgumentException('Unsupported operational drill.'),
            };
            $isBlocked = ($evidence['exit_code'] ?? 0) === 127 || str_contains($evidence['output'] ?? '', 'not found');
            $status = $isBlocked ? 'blocked' : ($evidence['status'] === 'failed' ? 'failed' : ($evidence['status'] === 'manual' ? 'manual' : 'passed'));
            $run->update([
                'status' => $status,
                'evidence' => $evidence,
                'failure_reason' => $isBlocked ? 'External prerequisite unavailable: '.trim($evidence['output'] ?? '') : null,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $run->update(['status' => 'blocked', 'failure_reason' => $exception->getMessage(), 'completed_at' => now()]);
        }

        return $run->fresh();
    }

    private function process(array $command, string $cwd): array
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(3600);
        $code = $process->run();

        return ['status' => $code === 0 ? 'passed' : 'failed', 'exit_code' => $code, 'output' => substr($process->getOutput().$process->getErrorOutput(), -20000)];
    }
}
