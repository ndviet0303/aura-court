## 1. Backend: Wallet Payment Service

- [x] 1.1 Create `WalletPaymentService` with `debit(WalletAccount, int amount, string sourceType, int sourceId): WalletTransaction` method — locks wallet row, validates balance, decrements, creates immutable `WalletTransaction`
- [x] 1.2 Add `credit(WalletAccount, int amount, string sourceType, int sourceId): WalletTransaction` method for refunds — locks wallet row, increments balance, creates immutable credit `WalletTransaction`
- [x] 1.3 Add `getOrCreateWallet(int userId): WalletAccount` method — auto-creates wallet with zero balance if none exists
- [x] 1.4 Add `postWalletPaymentJournal(Booking, Payment)` method to `FinancialPostingService` — debit `wallet_liability`, credit collection/liability accounts
- [x] 1.5 Add `postWalletRefundJournal(Booking, WalletTransaction)` method to `FinancialPostingService` — credit `wallet_liability`, debit reversal accounts

## 2. Backend: Modify CreateBooking Action

- [x] 2.1 Inject `WalletPaymentService` into `CreateBooking` action
- [x] 2.2 Add wallet deduction inside the existing `DB::transaction`: lock wallet → check balance → debit → create `Payment(provider: 'wallet')` → set booking `status: 'confirmed'` and slots `status: 'confirmed'`
- [x] 2.3 Return 422 with `balance` and `shortfall` fields when wallet balance is insufficient
- [x] 2.4 Remove `payment_expires_at` assignment from new bookings (no longer needed)
- [x] 2.5 Post wallet payment ledger journal after successful deduction

## 3. Backend: Modify CancelBooking Action

- [x] 3.1 Inject `WalletPaymentService` into `CancelBooking` action
- [x] 3.2 Replace `refund_pending` + `Refund` record creation with direct wallet credit: lock wallet → credit balance → create `WalletTransaction(type: 'refund')` → set booking `status: 'refunded'`
- [x] 3.3 Post wallet refund ledger journal after credit
- [x] 3.4 Keep existing 2-hour cancellation policy check unchanged

## 4. Backend: Simplify PaymentService and Controllers

- [x] 4.1 Remove `qrPayload()`, `stripePayload()`, `processStripePayment()`, `handleStripeWebhook()` from `PaymentService`
- [x] 4.2 Remove `stripePay()` and `demoPay()` endpoints from `BookingController`
- [x] 4.3 Simplify `store()` response — return confirmed booking (no QR payload needed)
- [x] 4.4 Simplify `payment()` endpoint — return booking + wallet balance instead of QR/Stripe payloads
- [x] 4.5 Remove `paymentStatus()` polling endpoint (or simplify to just return current status without triggering expiry)
- [x] 4.6 Update `applyCoupon()` response to not include QR/Stripe payloads
- [x] 4.7 Update `recurring()` endpoint to use wallet payment for each booking in the series

## 5. Backend: Remove Stripe Integration

- [x] 5.1 Delete `ProcessStripePayment.php` action
- [x] 5.2 Remove Stripe webhook route from `routes/api.php`
- [x] 5.3 Simplify `ProcessQrPayment` — remove booking-matching logic (steps 1–4), keep only wallet deposit webhook handling
- [x] 5.4 Remove Stripe-related config from `config/services.php`
- [x] 5.5 Clean up `PaymentProviderManager` — remove Stripe provider registration if present

## 6. Backend: Update Routes

- [x] 6.1 Remove `/bookings/{booking}/stripe-pay` route
- [x] 6.2 Remove `/bookings/{booking}/demo-pay` route
- [x] 6.3 Add `GET /wallet/balance` endpoint for frontend to fetch current user wallet balance
- [x] 6.4 Verify wallet deposit routes remain functional

## 7. Frontend: Wallet Payment API and Store

- [x] 7.1 Add `getWalletBalance()` to booking API — calls `GET /wallet/balance`
- [x] 7.2 Remove `stripePay()` and QR-related API calls from `bookingApi.ts`
- [x] 7.3 Update `bookingStore.ts` — remove `stripePay`, `refreshPaymentStatus` polling; add `walletBalance` state and `fetchWalletBalance()` action
- [x] 7.4 Update `store()` action in bookingStore to handle wallet-confirmed response (booking comes back confirmed, no payment session needed)

## 8. Frontend: Simplify PaymentView

- [x] 8.1 Rewrite `usePaymentProcessing.ts` — remove all Stripe card state (`cardNumber`, `cardExpiry`, `cardCvc`, `cardName`, `isCardProcessing`), remove polling (`timer`, `checkPaymentStatus`), remove QR state, remove countdown timer; add `walletBalance` ref and `payFromWallet()` method
- [x] 8.2 Replace `PaymentMethodSelector.vue` with a `WalletPayButton.vue` component — shows wallet balance, pay button (enabled when balance sufficient), and "Top up" link to `/wallet` (shown when insufficient)
- [x] 8.3 Update `PaymentView.vue` — remove QR/Card method selector, use `WalletPayButton` instead; remove countdown timer display; simplify success flow (instant confirmation)
- [x] 8.4 Update `PaymentSummaryCard.vue` — remove countdown timer, coupon can stay; add wallet balance display
- [x] 8.5 Update tournament payment flow in `usePaymentProcessing.ts` — use wallet deduction instead of QR payment for tournament fees

## 9. Frontend: Update Booking Flow Integration

- [x] 9.1 Update booking creation flow — after successful `store()`, booking is already confirmed; redirect to success/booking-detail instead of payment page
- [x] 9.2 Handle insufficient balance error from `store()` — show inline error with wallet balance, shortfall, and "Top up" link
- [x] 9.3 Update `UserBookingCard.vue` — remove `pending_payment` status handling for new bookings; keep for legacy display
- [x] 9.4 Update notification redirect — change `/payment/{code}` to booking detail page since payment is instant

## 10. Frontend: Cleanup and i18n

- [x] 10.1 Remove Stripe/QR-related i18n keys from `vi.ts` and `en.ts` locale files
- [x] 10.2 Add wallet payment i18n keys: insufficient balance message, pay from wallet button text, top-up prompt, instant confirmation message
- [x] 10.3 Remove or update `PaymentMethodSelector.vue` imports across components
- [x] 10.4 Update router — simplify `/payment/:code` route if payment view changes significantly

## 11. Tests

- [x] 11.1 Update `BookingFlowsTest.php` — test wallet deduction on booking creation (sufficient balance → confirmed, insufficient → 422)
- [x] 11.2 Update `BookingApiTest.php` — test cancel with wallet refund (confirmed → refunded with wallet credit)
- [x] 11.3 Update `FinancialFoundationTest.php` — test wallet payment and wallet refund journal entries balance correctly
- [x] 11.4 Update `PaymentView.spec.ts` — test wallet pay button rendering, insufficient balance state, and top-up link
- [x] 11.5 Verify `PosMembershipWalletTest.php` still passes (POS wallet flow unchanged)
