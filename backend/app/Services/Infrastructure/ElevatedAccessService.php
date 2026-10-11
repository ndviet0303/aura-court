<?php

namespace App\Services\Infrastructure;

use App\Models\ElevatedAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class ElevatedAccessService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function executeElevated(
        User $actor,
        string $action,
        string $purpose,
        ?int $organizationId,
        callable $callback
    ): mixed {
        if (! $actor->isPlatformAdmin()) {
            throw new AuthorizationException('Only platform administrators can perform elevated actions.');
        }

        $ip = Request::ip();
        $startTime = microtime(true);
        $outcome = 'success';
        $metadata = [];

        $this->tenantContext->setElevated(true, $purpose);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("SET LOCAL app.is_elevated = 'true'");
        }

        try {
            return $callback();
        } catch (\Throwable $e) {
            $outcome = 'failed: '.$e->getMessage();
            $metadata['exception'] = [
                'class' => get_class($e),
                'message' => $e->getMessage(),
            ];
            throw $e;
        } finally {
            $this->tenantContext->setElevated(false, null);
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement("SET LOCAL app.is_elevated = 'false'");
            }

            $metadata['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);

            ElevatedAuditLog::create([
                'user_id' => $actor->id,
                'organization_id' => $organizationId,
                'action' => $action,
                'purpose' => $purpose,
                'outcome' => $outcome,
                'ip_address' => $ip,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        }
    }
}
