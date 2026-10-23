<?php

namespace App\Http\Resources\Community;

use App\Models\FeedReport;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * FR-132: a report as the platform admin sees it; target is null once its author deleted the content.
 *
 * @mixin FeedReport
 */
class ContentReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $target = $this->reportable;

        return [
            'id' => $this->id,
            'type' => array_search($this->reportable_type, FeedReport::TYPES, true) ?: null,
            'target_id' => $this->reportable_id,
            'reason' => $this->reason,
            'status' => $this->status,
            'action_taken' => $this->action_taken,
            'reporter' => $this->reporter ? ['id' => $this->reporter->id, 'name' => $this->reporter->name] : null,
            'reviewer' => $this->reviewer ? ['id' => $this->reviewer->id, 'name' => $this->reviewer->name] : null,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'target' => $target === null ? null : [
                'author' => AuthorResource::make($target->getRelationValue('user')),
                'excerpt' => Str::limit((string) ($target instanceof Review ? $target->body : $target->getAttribute('content')), 160),
                'is_hidden' => $target instanceof Review ? ! $target->is_published : (bool) $target->getAttribute('is_hidden'),
            ],
        ];
    }
}
