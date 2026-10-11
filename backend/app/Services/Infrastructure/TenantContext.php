<?php

namespace App\Services\Infrastructure;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class TenantContext
{
    private ?Organization $currentOrganization = null;

    private bool $elevated = false;

    private ?string $elevatedPurpose = null;

    public function setOrganization(?Organization $organization): void
    {
        $this->currentOrganization = $organization;

        // If connected to PostgreSQL with RLS, set transaction session variable
        if ($organization && DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("SET LOCAL app.current_organization_id = '{$organization->id}'");
        }
    }

    public function getOrganization(): ?Organization
    {
        return $this->currentOrganization;
    }

    public function getOrganizationId(): ?int
    {
        return $this->currentOrganization?->id;
    }

    public function hasOrganization(): bool
    {
        return $this->currentOrganization !== null;
    }

    public function setElevated(bool $elevated, ?string $purpose = null): void
    {
        $this->elevated = $elevated;
        $this->elevatedPurpose = $purpose;
    }

    public function isElevated(): bool
    {
        return $this->elevated;
    }

    public function getElevatedPurpose(): ?string
    {
        return $this->elevatedPurpose;
    }

    public function executeInContext(Organization $organization, callable $callback): mixed
    {
        $previous = $this->currentOrganization;
        try {
            $this->setOrganization($organization);

            return $callback($organization);
        } finally {
            $this->setOrganization($previous);
        }
    }

    public function clear(): void
    {
        $this->currentOrganization = null;
        $this->elevated = false;
        $this->elevatedPurpose = null;
    }

    public function scopedCacheKey(string $key): string
    {
        $orgId = $this->getOrganizationId() ?? 'global';

        return "tenant:{$orgId}:{$key}";
    }

    public function scopedChannelName(string $channel): string
    {
        $orgId = $this->getOrganizationId() ?? 'global';

        return "private-tenant.{$orgId}.{$channel}";
    }
}
