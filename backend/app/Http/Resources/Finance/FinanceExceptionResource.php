<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinanceExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'type' => $this->type,
            'severity' => $this->severity,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency ?? 'VND',
            'reference' => $this->reference,
            'description' => $this->description,
            'links' => $this->links,
            'metadata' => $this->metadata,
            'detected_at' => $this->detected_at?->toISOString(),
            'due_at' => $this->due_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'resolved_by' => $this->resolved_by,
            'resolver' => $this->whenLoaded('resolver', fn () => [
                'id' => $this->resolver->id,
                'name' => $this->resolver->name,
                'email' => $this->resolver->email,
            ]),
            'resolution' => $this->resolution,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
