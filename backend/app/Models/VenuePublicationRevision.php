<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenuePublicationRevision extends Model
{
    use HasFactory;

    protected $table = 'venue_publication_revisions';

    protected $fillable = [
        'venue_id',
        'revision_number',
        'status',
        'configuration_snapshot',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'decision_reason',
        'feedback_fields',
    ];

    protected $casts = [
        'revision_number' => 'integer',
        'configuration_snapshot' => 'array',
        'feedback_fields' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRevisionRequired(): bool
    {
        return $this->status === 'revision_required';
    }
}
