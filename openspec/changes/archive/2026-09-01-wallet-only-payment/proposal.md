## Why

The current booking payment flow supports multiple payment methods (QR bank transfer via SePay, Stripe card payments) which creates significant complexity: webhook matching with 4-step fallback logic, frontend polling every 4 seconds, 15-minute payment timeouts, and a manual admin-review refund process. The wallet system already exists with deposit functionality, immutable transactions, and ledger integration. Consolidating all booking payments through the wallet eliminates webhook-matching complexity for bookings, enables instant payment confirmation (no polling), and makes refunds automatic and immediate — credited back to the wallet balance without admin intervention.

## What Changes

- **BREAKING**: Remove QR bank transfer as a direct booking payment method. Users must first deposit funds into their wallet, then pay from wallet balance.
- **BREAKING**: Remove Stripe card payment for bookings. The `stripePay` endpoint and card form UI are removed.
- Add wallet balance deduction as the sole booking payment method. Booking confirmation is instant upon successful balance deduction.
- Add automatic wallet refund on booking cancellation. When a confirmed booking is cancelled (within the 2-hour policy), the full amount is credited back to the wallet immediately — no `refund_pending` state or admin review needed.
- Simplify the `PaymentView.vue` frontend: replace QR/Card method selector with a single "Pay from Wallet" button showing current balance and a "Top up" link if insufficient.
- Remove frontend payment status polling (no longer needed — payment is synchronous).
- Tournament fee payments also transition to wallet-only.
- Wallet deposit flow (QR VietQR/SePay into wallet) remains unchanged.

## Capabilities

### New Capabilities
- `wallet-payment`: Wallet balance deduction for booking payments and automatic refund-to-wallet on cancellation. Covers the pay-from-wallet action, insufficient-balance handling, and instant refund credit.

### Modified Capabilities
- `financial-ledger-and-payouts`: Refund processing changes from external provider refund to internal wallet credit. The ledger must record wallet debit (payment) and wallet credit (refund) journals. Provider operations requirement narrows to wallet deposits only (not booking payments).
- `reservations-and-pricing`: Booking creation no longer produces a `pending_payment` → webhook-confirmed flow. A reservation is either confirmed instantly (wallet deducted) or rejected (insufficient balance). The payment expiry timer and slot-held state for pending payments are removed.

## Impact

### Backend
- `PaymentService` — remove `qrPayload()`, `stripePayload()`, `processStripePayment()`, `handleStripeWebhook()` methods; add `payFromWallet()` method
- `BookingController` — remove `stripePay()`, `demoPay()` endpoints; simplify `store()` and `payment()` to use wallet; remove payment polling endpoint
- `ProcessQrPayment` — keep for wallet deposit webhook only, remove booking-matching logic
- `ProcessStripePayment` — delete entirely
- `CancelBooking` — replace `refund_pending` + `Refund` record with direct wallet credit
- `CreateBooking` — change from `pending_payment` status to immediate `confirmed` with wallet deduction
- `FinancialPostingService` — add wallet payment and wallet refund journal posting methods
- Routes: remove Stripe webhook route, `stripe-pay` endpoint, `demo-pay` endpoint

### Frontend
- `PaymentView.vue` — replace QR + Card UI with wallet balance display and single pay button
- `PaymentMethodSelector.vue` — delete or replace with wallet pay component
- `usePaymentProcessing.ts` — remove Stripe card state, polling logic, QR state; add wallet balance check and pay action
- `bookingStore.ts` — remove `stripePay`, `refreshPaymentStatus` polling; add `walletPay` action
- `bookingApi.ts` — remove stripe/QR endpoints; add wallet payment endpoint

### Database
- `bookings.status` — `pending_payment` state largely eliminated for new bookings (instant confirm or reject)
- `payments` table — new records use `provider: 'wallet'` instead of `sepay`/`stripe`
- `refunds` table — may become unused for wallet-based refunds (wallet transactions serve as refund record)
