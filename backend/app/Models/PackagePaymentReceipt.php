<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Database\Factories\PackagePaymentReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One provider transaction carrying a PKG code, stored once with its outcome for reconciliation (D5, D6). */
#[Fillable(['provider', 'provider_transaction_id', 'payment_code', 'package_payment_request_id', 'amount', 'content', 'transferred_at', 'received_at', 'outcome', 'raw'])]
class PackagePaymentReceipt extends Model
{
    /** @use HasFactory<PackagePaymentReceiptFactory> */
    use HasFactory;

    public const PROVIDER_SEPAY = 'sepay';

    public const OUTCOME_APPLIED = 'applied';

    public const OUTCOME_LATE = 'late';

    public const OUTCOME_AMOUNT_MISMATCH = 'amount_mismatch';

    public const OUTCOME_UNKNOWN_CODE = 'unknown_code';

    public const OUTCOME_ALREADY_PAID = 'already_paid';

    public const OUTCOME_TERM_CONFLICT = 'term_conflict';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'transferred_at' => UtcDateTime::class,
            'received_at' => UtcDateTime::class,
            'raw' => 'array',
        ];
    }

    /** @return BelongsTo<PackagePaymentRequest, $this> */
    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PackagePaymentRequest::class, 'package_payment_request_id');
    }
}
