<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** FR-118: user_a is the customer of a customer–venue conversation; owner–admin conversations belong to an organization. */
#[Fillable(['type', 'pair_key', 'user_a_id', 'user_b_id', 'venue_id', 'organization_id', 'last_message_at'])]
class Conversation extends Model
{
    public const TYPE_CUSTOMER = 'customer_customer';

    public const TYPE_VENUE = 'customer_venue';

    public const TYPE_SUPPORT = 'owner_admin';

    public const SIDE_CUSTOMER = 'customer';

    public const SIDE_VENUE = 'venue';

    public const SIDE_OWNER = 'owner';

    public const SIDE_ADMIN = 'admin';

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    /** @return BelongsTo<User, $this> */
    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<ConversationMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /** @return HasOne<ConversationMessage, $this> */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany();
    }
}
