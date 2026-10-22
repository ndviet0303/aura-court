<?php

namespace App\Http\Resources\Venue;

use App\Contracts\Storage\PartnerDocumentStorageInterface;
use App\Models\SuspensionRemediation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SuspensionRemediation */
class SuspensionRemediationResource extends JsonResource
{
    /** Minutes an evidence link stays valid; links are issued only to owners of the organization and platform admins. */
    private const LINK_MINUTES = 30;

    public function toArray(Request $request): array
    {
        $storage = app(PartnerDocumentStorageInterface::class);

        return [
            'id' => $this->id,
            'venue_suspension_id' => $this->venue_suspension_id,
            'status' => $this->status,
            'content' => $this->content,
            'files' => array_map(fn (array $file): array => [
                'name' => $file['name'],
                'mime_type' => $file['mime_type'],
                'size' => $file['size'],
                'url' => $storage->temporaryReadUrl($file['path'], self::LINK_MINUTES),
            ], $this->files ?? []),
            'submitted_by' => $this->whenLoaded('submitter', fn () => $this->submitter?->only(['id', 'name'])),
            'submitted_at' => $this->submitted_at->toIso8601String(),
            'reviewed_by' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->only(['id', 'name'])),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_note' => $this->review_note,
            'submission_count' => $this->when($this->resource->getAttribute('submission_count') !== null, fn () => (int) $this->resource->getAttribute('submission_count')),
            'suspension' => new VenueSuspensionResource($this->whenLoaded('suspension')),
        ];
    }
}
