## Why

Chính sách sản phẩm đã chốt: đặt sân không hỗ trợ hủy hoặc đổi sân/giờ, nên không còn nghiệp vụ hoàn tiền phát sinh từ hai thao tác này. Hiện V1, V2, giao diện khách hàng và quản trị vẫn cho phép hoặc quảng bá các thao tác đó, cần gỡ đồng bộ để tránh tiếp tục thay đổi lịch và số dư ngoài chính sách.

## What Changes

- **BREAKING**: Retire V1 whole-booking cancellation, partial-slot cancellation and rescheduling, and V2 reservation cancellation. Apply to existing and new records, including legacy unpaid holds; user-initiated cancellation is distinct from automatic expiry.
- Remove customer actions, client methods, cancellation dialogs, booking-cancellation refund approval actions and cancellation/rescheduling benefit copy in Vietnamese and English.
- Remove editable cancellation grace periods and cancellation-fee-waiver benefits. Venue readiness must not require obsolete policy configuration or allow a tenant/membership override of the fixed policy.
- Stop generating wallet credits, price-difference debits/credits, replacement bookings and financial reversals from these retired operations.
- Preserve historical cancelled/refunded records, policy/price snapshots, wallet transactions and journals. Historical pending cancellation refunds remain read-only, with no automatic approval, rejection, credit or balance rewrite during rollout.
- Preserve ordinary checkout, check-in, booking history and automatic unpaid-hold expiry with inventory release. Non-cancellation refunds/compensation, dispute handling, marketplace transfers, split-payment expiry, recurring invoice overdue handling and subscription/POS/contract lifecycles are outside this change.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `reservations-and-pricing`: Remove supported cancellation/rescheduling commands, retain historical reads and automatic expiry, and prohibit side effects from retired requests.
- `wallet-payment`: Remove automatic cancellation refunds; retain wallet history and prevent monetary effects from cancellation/rescheduling attempts.
- `venue-court-operations`: Replace configurable cancellation/change policy with fixed no-cancel/no-reschedule behavior while retaining historical snapshots.
- `financial-ledger-and-payouts`: Remove cancellation as a supported source of new reversal journals while preserving ledger history and unrelated compensation.
- `api-alignment`: Remove retired customer/admin controls and misleading policy copy; define failed stale-client requests and read-only historical cancellation-refund visibility.

## Impact

- Backend: `routes/api.php`, booking controller/service and `CancelBooking`, `CancelBookingSlots`, `RescheduleBooking`; V2 reservation cancellation; admin `ProcessRefund`; organization policy, membership benefit and venue-readiness paths.
- Frontend: booking/V2 history and held banners, booking API clients/composables, admin refund panels and policy/membership forms, payment/court/marketing copy, Vietnamese/English locales and associated types/tests.
- Specifications and tests: replace cancellation/rescheduling success scenarios, retain historical and unrelated finance coverage, update active operational/API documentation. Archived change artifacts are not rewritten.
- No new dependencies, policy engine, feature flag or destructive database migration is required. Historical finance records and shared financial posting infrastructure remain intact.
