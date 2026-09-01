## Context

See proposal.md for motivation. The current codebase has:
- `WalletAccount` model with `balance`, `status`, and `HasMany` transactions
- `WalletTransaction` model — immutable (blocks update/delete), tracks `type`, `amount`, `balance_after`, `source_type`, `source_id`
- `LedgerService` with double-entry journal posting and `wallet_liability` system account
- `FinancialPostingService` for booking payment journal creation
- `PaymentService` with QR (SePay) and Stripe methods for booking payments
- `CreateBooking` action that sets `status: pending_payment` with 15-minute expiry
- `CancelBooking` action that creates `refund_pending` + `Refund` record for admin review
- Frontend `PaymentView.vue` with `PaymentMethodSelector` (QR + Card tabs) and 4-second polling

## Goals / Non-Goals

**Goals:**
- Single payment path: wallet balance → booking confirmation (synchronous, no polling)
- Automatic instant refund to wallet on eligible cancellation (no admin intervention)
- Simplify frontend checkout to a single "Pay from Wallet" interaction
- Maintain ledger integrity with balanced journal entries for wallet payments and refunds
- Keep wallet deposit flow (QR VietQR/SePay → wallet) unchanged

**Non-Goals:**
- Changing the wallet deposit mechanism (stays QR-based)
- Building a withdrawal/cash-out feature
- Modifying POS wallet integration
- Changing the 2-hour cancellation policy
- Supporting partial wallet payments or mixed payment methods
- Building an auto-top-up feature (user manually deposits)

## Decisions

### 1. Atomic wallet deduction inside `CreateBooking` transaction

**Decision**: Deduct wallet balance within the same DB transaction that creates booking + slots.

**Rationale**: This guarantees atomicity — either the wallet is deducted AND the booking is confirmed, or nothing happens. No orphaned states like `pending_payment` without a corresponding payment.

**Alternative considered**: Separate pay endpoint after booking creation (current flow). Rejected because it creates the very state management complexity we're eliminating.

**Implementation**:
```
DB::transaction(function () {
    // 1. Validate slots, prices, discounts (existing logic)
    // 2. Lock wallet row: WalletAccount::where('user_id', $userId)->lockForUpdate()
    // 3. Check balance >= total_amount, abort(422) if insufficient
    // 4. Decrement balance, create WalletTransaction(type: 'payment', source: booking)
    // 5. Create booking with status: 'confirmed' (not 'pending_payment')
    // 6. Create slots with status: 'confirmed' (not 'held')
    // 7. Create Payment record (provider: 'wallet')
    // 8. Post ledger journal
})
```

### 2. Wallet refund inside `CancelBooking` transaction

**Decision**: Credit wallet balance within the same DB transaction that cancels the booking.

**Rationale**: Eliminates the `refund_pending` intermediate state and admin review. The wallet credit is the refund — no external provider call needed.

**Implementation**:
```
DB::transaction(function () {
    // 1. Validate cancellation eligibility (existing 2-hour rule)
    // 2. Lock wallet row
    // 3. Credit balance, create WalletTransaction(type: 'refund', source: booking)
    // 4. Update booking status: 'refunded' (skip 'refund_pending')
    // 5. Cancel slots
    // 6. Post reversal ledger journal
})
```

**Status flow change**: `confirmed → refunded` (direct, no `refund_pending`)

### 3. Keep `Payment` model for audit trail

**Decision**: Still create a `Payment` record with `provider: 'wallet'` for each booking payment.

**Rationale**: The `Payment` model provides a uniform audit trail across all payment types. Admin views, reconciliation, and financial reporting already query this table. Reusing it avoids modifying those downstream consumers.

**Alternative considered**: Only use `WalletTransaction` as the payment record. Rejected because it would require updating all admin/finance views that reference `payments`.

### 4. Remove Stripe entirely, keep SePay for wallet deposits only

**Decision**: Delete `ProcessStripePayment`, Stripe webhook route, and all card-related frontend code. Keep `ProcessQrPayment` but simplify it to only handle wallet top-up webhooks (no booking matching).

**Rationale**: Stripe was used only for booking payments — with wallet-only, there's no use case left. SePay/VietQR is still needed for the wallet deposit flow.

### 5. Frontend: replace PaymentView with WalletPaymentView

**Decision**: Simplify `PaymentView.vue` to show:
1. Booking summary (court, slots, amount) — reuse `PaymentSummaryCard`
2. Wallet balance display with pay button (or "insufficient balance + top up" link)
3. No QR code, no card form, no polling, no countdown timer

**Rationale**: The entire checkout becomes a single button click. UX is dramatically simpler.

### 6. Auto-create wallet on first booking attempt

**Decision**: If user has no `WalletAccount` when attempting to book, auto-create one with zero balance (which will trigger the "insufficient balance" flow).

**Rationale**: Avoids requiring a separate "create wallet" step. Users discover the wallet naturally through the booking flow.

## Risks / Trade-offs

| Risk | Mitigation |
|------|------------|
| Users unfamiliar with wallet concept may be confused by the deposit-first flow | Clear onboarding UX: show wallet balance on booking page, prominent "Top up" CTA with amount guidance |
| Wallet deposit still relies on QR webhook (inherits existing latency) | This is acceptable — deposit happens before booking, not during checkout. User can pre-load wallet |
| Existing `pending_payment` bookings during migration | Run `ExpirePendingBookings` once before deployment to clean up. Keep the job temporarily |
| Existing `refund_pending` bookings during migration | Admin must manually process remaining pending refunds before or during deployment |
| Wallet row locking could increase contention for high-frequency users | `lockForUpdate()` on wallet row is short-lived within the transaction. Acceptable for expected booking volumes |
| Loss of direct payment flexibility (some users prefer pay-per-booking without wallet) | Trade-off accepted: simplicity > flexibility for current scale. Can revisit if user feedback demands it |

## Migration Plan

1. **Pre-deployment**: Run `ExpirePendingBookings` to clear pending bookings. Manually resolve any `refund_pending` bookings
2. **Deploy backend changes**: Atomic — new booking flow uses wallet, old endpoints removed
3. **Deploy frontend changes**: New payment UI, wallet integration
4. **Rollback strategy**: Revert both frontend and backend to previous version. No data migration needed — `WalletTransaction` and `Payment` records are additive
5. **Post-deployment**: Monitor wallet deposit webhook reliability, check ledger balance integrity
