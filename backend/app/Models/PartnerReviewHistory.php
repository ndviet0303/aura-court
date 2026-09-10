<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerReviewHistory extends Model
{
    use HasFactory;

    protected $table = 'partner_review_histories';

    protected $fillable = [
        'host_kyc_id',
        'reviewer_id',
        'decision',
        'reason',
        'reviewer_notes',
        'feedback_fields',
        'reapplication_allowed',
        'reapplication_cooloff_days',
        'application_version',
    ];

    protected $casts = [
        'feedback_fields' => 'array',
        'reapplication_allowed' => 'boolean',
        'reapplication_cooloff_days' => 'integer',
        'application_version' => 'integer',
    ];

    public function hostKyc(): BelongsTo
    {
        return $this->belongsTo(HostKyc::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isApproval(): bool
    {
        return $this->decision === 'approve';
    }

    public function isRejection(): bool
    {
        return $this->decision === 'reject';
    }

    public function isRevisionRequired(): bool
    {
        return $this->decision === 'revision_required';
    }
}
