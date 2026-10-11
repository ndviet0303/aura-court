<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class E2EInspectTargetCommand extends Command
{
    protected $signature = 'e2e:inspect-target
        {--run-id= : Expected run ID}
        {--expected-db= : Expected dedicated database name}
        {--strict : Enforce strict isolated test database rules}';

    protected $description = 'Preflight inspection of environment and database target before E2E mutations';

    public function handle(): int
    {
        $runId = (string) $this->option('run-id');
        $expectedDb = (string) $this->option('expected-db');
        $strict = (bool) $this->option('strict');

        $env = app()->environment();
        $isConfigCached = app()->configurationIsCached();
        $isRoutesCached = app()->routesAreCached();
        $storagePath = app()->storagePath();
        $sessionDriver = (string) config('session.driver');
        $cacheStore = (string) config('cache.default');
        $dbConnection = (string) config('database.default');
        $dbConfig = config("database.connections.{$dbConnection}") ?? [];
        $rawUrl = (string) ($dbConfig['url'] ?? '');

        // Resolve effective config as processed by Laravel DatabaseManager
        try {
            $resolvedConfig = DB::connection()->getConfig();
        } catch (\Throwable $e) {
            $resolvedConfig = $dbConfig;
        }

        $dbHost = (string) ($resolvedConfig['host'] ?? $dbConfig['host'] ?? '');
        $dbPort = (int) ($resolvedConfig['port'] ?? $dbConfig['port'] ?? 5432);
        $dbName = (string) ($resolvedConfig['database'] ?? $dbConfig['database'] ?? '');
        $dbUser = (string) ($resolvedConfig['username'] ?? $dbConfig['username'] ?? '');

        $violations = [];

        // 1. Environment safety: Must never be production or staging
        if ($env === 'production' || $env === 'staging') {
            $violations[] = "CRITICAL: E2E target is running in forbidden environment '{$env}'.";
        }

        if ($strict && $env !== 'testing') {
            $violations[] = "STRICT: E2E runner requires APP_ENV=testing, got '{$env}'.";
        }

        // 2. Cache safety: Configuration must not be cached (can conceal overrides)
        if ($isConfigCached) {
            $violations[] = 'CONFIGURATION_CACHED: Cached configuration is active; test overrides cannot be trusted.';
        }

        // 3. Database connection: Must be postgres
        if ($dbConnection !== 'pgsql') {
            $violations[] = "DB_DRIVER: Database connection must be 'pgsql', got '{$dbConnection}'.";
        }

        // 4. DB_URL override safety: Any URL override must strictly target local loopback and the expected DB
        if ($rawUrl !== '') {
            $parsed = parse_url($rawUrl);
            $urlHost = $parsed['host'] ?? null;
            $urlPath = trim($parsed['path'] ?? '', '/');
            $allowedHosts = ['127.0.0.1', 'localhost', '::1'];

            if ($urlHost && ! in_array($urlHost, $allowedHosts, true)) {
                $violations[] = "DB_URL_REMOTE: DB_URL specifies remote host '{$urlHost}'; forbidden in E2E.";
            }
            if ($urlPath === 'booking') {
                $violations[] = "CRITICAL: DB_URL targets production database 'booking'.";
            }
            if ($strict && $expectedDb !== '' && $urlPath !== '' && $urlPath !== $expectedDb) {
                $violations[] = "DB_URL_MISMATCH: DB_URL database '{$urlPath}' does not match expected '{$expectedDb}'.";
            }
        }

        // 5. Host safety: Must be loopback / local
        $allowedHosts = ['127.0.0.1', 'localhost', '::1'];
        if (! in_array($dbHost, $allowedHosts, true)) {
            $violations[] = "DB_REMOTE_TARGET: Database host '{$dbHost}' is not a local loopback address.";
        }

        // 6. Database name safety
        if ($dbName === 'booking') {
            $violations[] = "CRITICAL: Database name 'booking' is the production/default database.";
        }

        if ($strict && $dbName === 'booking_test') {
            $violations[] = "DB_SHARED_PHPUNIT: Database 'booking_test' is reserved for PHPUnit; E2E must own a dedicated database.";
        }

        if (! str_ends_with($dbName, '_test')) {
            $violations[] = "DB_NAMING: Target database '{$dbName}' does not end with '_test'.";
        }

        if ($expectedDb !== '' && $dbName !== $expectedDb) {
            $violations[] = "DB_MISMATCH: Target database '{$dbName}' does not match expected database '{$expectedDb}'.";
        }

        // 7. Session driver: Must persist across HTTP requests (not array)
        if ($sessionDriver === 'array') {
            $violations[] = "SESSION_DRIVER_ARRAY: session.driver is 'array'; browser requests will lose sessions.";
        }

        // 8. Verify actual DB connectivity, connected database, and actual server IP
        $actualConnectedDb = null;
        $actualServerIp = null;
        if (empty($violations)) {
            try {
                $row = DB::connection()->selectOne('SELECT current_database() as db, inet_server_addr() as server_ip');
                $actualConnectedDb = $row?->db;
                $actualServerIp = $row?->server_ip;

                if ($actualConnectedDb !== $dbName) {
                    $violations[] = "DB_ACTUAL_MISMATCH: Connected database '{$actualConnectedDb}' does not match configured '{$dbName}'.";
                }

                if ($actualServerIp !== null && ! in_array($actualServerIp, ['127.0.0.1', '::1'], true)) {
                    $violations[] = "DB_ACTUAL_REMOTE_IP: Connected PostgreSQL server IP '{$actualServerIp}' is not local loopback.";
                }
            } catch (\Throwable $e) {
                $violations[] = "DB_CONNECTION_FAILED: Unable to connect to database '{$dbName}': ".$e->getMessage();
            }
        }
        $report = [
            'status' => empty($violations) ? 'passed' : 'failed',
            'run_id' => $runId ?: null,
            'environment' => $env,
            'config_cached' => $isConfigCached,
            'routes_cached' => $isRoutesCached,
            'storage_path' => $storagePath,
            'session_driver' => $sessionDriver,
            'cache_store' => $cacheStore,
            'database' => [
                'connection' => $dbConnection,
                'host' => $dbHost,
                'port' => $dbPort,
                'database' => $dbName,
                'actual_connected_database' => $actualConnectedDb,
                'username' => $dbUser,
            ],
            'violations' => $violations,
        ];

        if (! empty($violations)) {
            $this->output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->error('E2E preflight inspection failed with '.count($violations).' violation(s).');

            return self::FAILURE;
        }

        $this->output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
