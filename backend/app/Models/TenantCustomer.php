<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'is_guest',
        'name',
        'phone',
        'email',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'is_guest' => 'boolean',
        'metadata' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
