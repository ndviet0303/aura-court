<?php

namespace App\Traits;

use App\Models\Organization;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            $context = app(TenantContext::class);
            if (! $model->organization_id && $context->hasOrganization()) {
                $model->organization_id = $context->getOrganizationId();
            }
        });

        static::addGlobalScope('tenant_isolation', function (Builder $builder) {
            $context = app(TenantContext::class);
            if ($context->isElevated()) {
                return;
            }

            if ($context->hasOrganization()) {
                $builder->where($builder->getModel()->getTable() . '.organization_id', $context->getOrganizationId());
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
