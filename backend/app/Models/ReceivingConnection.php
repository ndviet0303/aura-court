<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class ReceivingConnection extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'organization_id',
        'venue_id',
        'owner_user_id',
        'provider',
        'provider_account_id',
        'routing_id',
        'bank_code',
        'account_name_ciphertext',
        'account_number_ciphertext',
        'account_last_four',
        'status',
        'webhook_secret_ciphertext',
        'api_token_ciphertext',
        'api_token_version',
        'last_queried_transaction_id',
        'credential_version',
        'owner_attested_at',
        'verified_at',
        'effective_from',
        'effective_until',
    ];

    protected $hidden = [
        'account_name_ciphertext',
        'account_number_ciphertext',
        'webhook_secret_ciphertext',
        'api_token_ciphertext',
    ];

    protected function casts(): array
    {
        return [
            'credential_version' => 'integer',
            'api_token_version' => 'integer',
            'owner_attested_at' => 'datetime',
            'verified_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ReceivingConnectionEvidence::class)->orderByDesc('id');
    }

    public function setAccountDetails(string $accountName, string $accountNumber): void
    {
        $this->account_name_ciphertext = Crypt::encryptString($accountName);
        $this->account_number_ciphertext = Crypt::encryptString($accountNumber);
        $this->account_last_four = substr($accountNumber, -4);
    }

    public function accountName(): string
    {
        return Crypt::decryptString((string) $this->account_name_ciphertext);
    }

    public function accountNumber(): string
    {
        return Crypt::decryptString((string) $this->account_number_ciphertext);
    }

    public function webhookSecret(): ?string
    {
        if (! $this->webhook_secret_ciphertext) {
            return null;
        }

        return Crypt::decryptString($this->webhook_secret_ciphertext);
    }

    public function sepayApiToken(): ?string
    {
        if (! $this->api_token_ciphertext) {
            return null;
        }

        return Crypt::decryptString($this->api_token_ciphertext);
    }

    public function maskedAccountNumber(): string
    {
        return '****'.$this->account_last_four;
    }

    public function isSelectedForVenue(): bool
    {
        return (int) Venue::query()
            ->withoutGlobalScopes()
            ->whereKey($this->venue_id)
            ->value('current_receiving_connection_id') === (int) $this->id;
    }
}
