<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostKyc extends Model
{
    use HasFactory;

    protected $table = 'host_kycs';

    protected $fillable = [
        'kyc_code',
        'organization_id',
        'venue_id',
        'user_id',
        'business_type',
        'business_name',
        'legal_name',
        'tax_code',
        'tax_status',
        'representative_name',
        'id_card_number',
        'id_card_front_url',
        'id_card_back_url',
        'id_card_portrait_url',
        'business_license_url',
        'bank_name',
        'bank_code',
        'bank_account_number',
        'bank_account_holder',
        'napas_match_status',
        'court_scale',
        'lighting_spec',
        'lease_contract_status',
        'facility_images',
        'ai_ocr_score',
        'face_match_score',
        'status',
        'rejection_reason',
        'reviewer_notes',
        'checklist_state',
        'reviewed_by',
        'reviewed_at',
        'submitted_at',
        'version',
        'current_user_id',
        'parent_kyc_id',
        'contact_name',
        'contact_phone',
        'contact_email',
        'address_line',
        'ward',
        'district',
        'city',
        'postal_code',
        'sports',
        'submission_snapshot',
        'submitted_version',
        'reapplication_policy',
        'investigation_status',
    ];

    protected $casts = [
        'facility_images' => 'array',
        'checklist_state' => 'array',
        'ai_ocr_score' => 'float',
        'face_match_score' => 'float',
        'court_scale' => 'integer',
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'sports' => 'array',
        'submission_snapshot' => 'array',
        'reapplication_policy' => 'array',
        'version' => 'integer',
        'submitted_version' => 'integer',
    ];
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
    public function currentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    public function parentKyc(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_kyc_id');
    }

    public function childKycs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_kyc_id');
    }
    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PartnerDocument::class);
    }

    public function consents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PartnerConsent::class);
    }

    public function reviewHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PartnerReviewHistory::class)->orderBy('created_at', 'desc');
    }

    public function provisionedOrganization(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Organization::class, 'source_kyc_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isRevisionRequired(): bool
    {
        return $this->status === 'revision_required' || $this->status === 'needs_supplement';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCurrent(): bool
    {
        return $this->current_user_id !== null;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
