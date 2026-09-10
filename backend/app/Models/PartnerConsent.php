<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerConsent extends Model
{
    use HasFactory;

    protected $table = 'partner_consents';

    protected $fillable = [
        'host_kyc_id',
        'user_id',
        'consent_type',
        'terms_version',
        'agreed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'agreed_at' => 'datetime',
    ];

    public function hostKyc(): BelongsTo
    {
        return $this->belongsTo(HostKyc::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
