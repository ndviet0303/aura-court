<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class SecurityReviewService
{
    public function run(): array
    {
        $checks = [];
        $patterns = ['APP_KEY=', 'DB_PASSWORD=', 'PAYOS_API_KEY=', 'PAYOS_WEBHOOK_SECRET=', 'SEPAY_HMAC_SECRET='];
        foreach ($patterns as $pattern) {
            $process = new Process(['rg', '--hidden', '--glob', '!.env', '--glob', '!vendor/**', '--glob', '!node_modules/**', $pattern, base_path('../')]);
            $process->run();
            $checks[$pattern] = $process->getExitCode() === 1 ? 'passed' : 'review';
        }
        $checks['rls_policies'] = file_exists(database_path('migrations/2026_08_22_000002_create_postgresql_rls_policies.php')) ? 'present' : 'missing';
        $checks['elevated_audit_path'] = class_exists(\App\Services\ElevatedAccessService::class) ? 'present' : 'missing';
        $checks['export_audit'] = class_exists(\App\Services\AuditLogger::class) ? 'present' : 'missing';
        $checks['rate_limits'] = file_exists(base_path('bootstrap/app.php')) ? 'review' : 'missing';
        return $checks;
    }
}
