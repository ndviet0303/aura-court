## 1. Pha 1 – Tài liệu và phối hợp

- [x] 1.1 Pha 0 đã làm (commit `ff8b41a6`, merge `7312b44c`). Không làm lại.
  - Webhook SePay từ chối chữ ký khi secret rỗng.
  - `AttendanceService::getAttendance` gọi `assertCanOperate`; đây là "Attendance lookup scope".
  - QR đơn và QR thẻ ví vẽ tại chỗ (D9).
  - `useCourtSchedule` dùng ngày địa phương.
  - Lịch sử đơn có phân trang, số đếm lấy từ `total`, nhãn và QR theo D10.
  - `finance/audit-logs` lọc theo tổ chức của người gọi.
- [x] 1.2 Gửi session module 3 (đã gửi 2026-10-21):
  - Q1 = A: lưới 30 phút; đầu vào đặt sân là `court_id`, `starts_at`, `ends_at`; mỗi đơn mới là một slot định giá bằng engine theo phút (D14). Bỏ `slot_duration` 45.
  - `expected_total`: chỉ từ chối khi số tính lại cao hơn (D5, BR-68). Module 04 làm phần so sánh trong checkout. D6 của module 3 cần sửa cho khớp.
  - Giữ câu "a booking still pending payment SHALL follow 'Pending bookings at a price change'" và kịch bản tương ứng. Module 3 đã REMOVED "Immutable price quotes" và chép nguyên văn hai phần này sang "Court quotes from price tables".
  - `AvailabilityController` viết lại phải: so chồng lấn nửa mở, coi `held` có `hold_expires_at ≤ now` là trống. Mốc 30 phút do change này bật ở task 5.1; bản Pha 2 của module 3 giữ bước `slot_duration` (module 3 D29).
  - Cần ba thứ, có tên và chữ ký cụ thể: sự kiện "giá mới có hiệu lực" (D11), engine tính tiền cho một khoảng, và service voucher `hold / release / consume` (D12). Tên cuối cùng ở Open Questions của design.
- [x] 1.3 Gửi session module 2 (đã gửi 2026-10-21):
  - Change này dựng khối MODIFIED "Final bookings and released availability" và "Courts carry their own sport and booking interval" trên text của `align-module-02-venue-court`. Nếu module 2 sửa lại hai requirement đó thì báo để dựng lại.
  - Độ dài slot của sân chỉ còn 30/60/90/120; đặt sân của khách theo lưới 30 phút.
  - Nhắc task 2.16 của module 2: bỏ `hold_ttl_minutes` và `require_continuity` khỏi form và request chính sách.
- [x] 1.4 Ghi chú phối hợp vào `openspec/changes/direct-owner-cutover/tasks.md`: requirement "One-hour booking slots" bị change này REMOVED; decision 8 (ẩn ngoại lệ khi tắt payout) được nới cho màn tra cứu chỉ đọc.
- [x] 1.5 `openspec validate align-module-04-booking --strict` đạt.
- [x] 1.6 Nhận phản hồi của module 3 (task 1.3 của `align-module-03-pricing-promotions`, 2026-10-21) và sửa tài liệu:
  - Bỏ khối MODIFIED "Immutable price quotes"; bỏ vế voucher khỏi scenario "No payment window left"; thêm lý do nhả `voucher_changed` vào spec.
  - Design D2, D3, D5, D10, D11, D12, D14, D15, mục phối hợp, Risks và Open Questions theo D11, D16, D17, D19, D20, D29 của module 3.
  - Lệch với đề xuất của module 3: `processReceipt` khoá cơ sở trước attempt, không phải ngay sau khi khoá đơn (design D12, Risks). Đã báo lại session module 3.
- [x] 1.7 Nhận phản hồi thứ hai của module 3 (task 1.6 của họ, 2026-10-21) và sửa tài liệu:
  - D12, task 5.5: nhánh hỏng của `confirmPaidAttempt` hết hạn attempt `split_share` còn chờ bằng `DB::afterCommit`. Thêm so với đề xuất của module 3: `SplitPaymentService::expire` cũng chuyển như vậy, vì hiện nó UPDATE attempt trong lúc giữ nhóm (design D12, Risks).
  - D11, task 5.6: listener giá chạy mỗi đơn một transaction và chỉ báo khách khi `releaseHeld` trả `true`.

## 2. Pha 2 – Tạo yêu cầu đặt sân (D2, D3, D4, D5, D6, D7)

Điều kiện: Pha 0 của module 2 đã merge (`9dddc773`).

- [x] 2.1 Tách `releaseHeld(Reservation $locked, string $status, string $reason)` từ `ExpirePendingBookings` (D2).
  - Nhả slot, entitlement, lượt voucher, điểm; cho attempt đang chờ hết hạn; ghi `metadata.release_reason`.
  - Không làm gì khi đơn không còn `held`.
  - `ExpirePendingBookings` gọi hàm này với `('expired', 'payment_deadline')`.
  - Test: `HeldReservationReleaseTest`. Nhả hai lần chỉ nhả voucher (và hoàn điểm của đơn cũ có giữ điểm) một lần; đơn `confirmed` không đổi.
- [x] 2.2 Helper dùng chung trước khi chèn (`ReservationIntake`), gọi từ `OrdinaryCheckoutService::checkout` và `ReservationService::hold`. Thứ tự:
  - Kiểm SĐT bằng `User::PHONE_PATTERN` → 422 `PHONE_REQUIRED` trước khi khoá.
  - Khoá; nhả các đơn `held` quá hạn chồng lấn trên sân đó (theo id) bằng `releaseHeld(…, 'expired', 'payment_deadline')` (D7).
  - Tính tiền; nếu cao hơn `expected_total` → 409 `PRICE_CHANGED` kèm `{rental, discount, total}` (D5).
  - Tính `hold_expires_at = min(now + 10 phút, giờ bắt đầu, hạn voucher)` khi tiền phải trả > 0; hạn ≤ now → 422 `PAYMENT_WINDOW_CLOSED` (D3).
  - Ghi `metadata.booker = {name, phone, email}` (D4).
  - Bỏ đọc `booking.hold_ttl_minutes` ở `OrdinaryCheckoutService.php:333` và `ReservationService.php:273`.
- [x] 2.3 Request checkout và hold: `expected_total` bắt buộc, số nguyên ≥ 0; thiếu → 422.
- [x] 2.4 `SlotSelectionPolicy` (D6, D7):
  - Luôn kiểm tính liên tục, bỏ đọc `require_continuity`.
  - Truy vấn chồng lấn bỏ qua `held` có `hold_expires_at ≤ now`.
  - Bỏ `require_continuity` khỏi `E2ESeedFixturesCommand`.
- [x] 2.5 `ReservationPaymentService::initiate` (D3): attempt dùng `expires_at = hold_expires_at`, bỏ `now()->addMinutes(15)`. Attempt còn chờ thì trả lại đúng attempt đó.
- [x] 2.6 Migration backfill `metadata.booker` cho reservation chưa có, lấy từ hồ sơ lúc chạy, ghi `metadata.booker_backfilled = true`.
- [x] 2.7 Màn danh sách đơn của chủ sân và tra mã ở quầy đọc người đặt từ `metadata.booker`, không đọc hồ sơ hiện tại.
- [x] 2.8 Frontend `BookingView.vue`, `QuoteCalculatorCard.vue`, `ReservationPaymentModal.vue`:
  - Khối người đặt (tên, SĐT, email). Thiếu SĐT thì hiện link "Cập nhật số điện thoại" và khoá nút gửi.
  - Dòng tiền thuê, voucher, tiền giảm, tổng phải trả; gửi `expected_total` bằng tổng đang hiển thị.
  - `PRICE_CHANGED`: hiện số mới, khách bấm xác nhận lại mới gửi tiếp.
  - Thông báo cho `PHONE_REQUIRED` và `PAYMENT_WINDOW_CLOSED` (vi/en).
  - Đếm ngược theo `hold_expires_at`.
  - Vitest đi kèm.
- [x] 2.9 Test backend (`PaymentDeadlineTest`, `BookerSnapshotTest`, `AgreedAmountTest`, `OverdueHoldTest`):
  - Scenario "Start time caps the deadline" (14:58 → 15:00; tiền tới 15:01 là ngoại lệ `transfer_after_expiry`).
  - Scenario "Voucher end caps the deadline", "Ten-minute cap" (chính sách 30 phút bị bỏ qua), "Reopening the payment information", "No payment window left".
  - Scenario "Missing phone number", "Profile edited after booking".
  - Scenario "Price rose before submission" (không có dòng reservation, slot, entitlement, coupon usage, attempt nào), "Price fell before submission", "Amount not sent".
  - Scenario "Overdue hold does not block", "Live hold blocks".
  - Scenario "Configured booking window": hai slot không liền nhau → 422 dù cơ sở từng đặt `require_continuity = false`.
  - Cập nhật các test đang gửi checkout không có `expected_total` hoặc dựa vào `hold_ttl_minutes`.

## 3. Pha 3 – Huỷ đơn chờ thanh toán và chi tiết đơn (D1, D2, D10)

- [x] 3.1 `POST reservations/{code}/cancel-payment`, chỉ chủ đơn (D1).
  - Transaction: khoá attempt của đơn rồi khoá reservation.
  - `held` → `releaseHeld(…, 'cancelled', 'customer_payment_cancel')`.
  - `cancelled` hoặc `expired` → 200, không làm gì. `confirmed` → 409. Người khác → 403, hoặc 404 khi không thấy đơn.
- [x] 3.2 Kiểm webhook: receipt cho attempt đã hết hạn vì huỷ phải thành ngoại lệ `transfer_after_expiry` và không khôi phục đơn. Sửa `applyAttemptChecks` nếu hiện tại cho kết quả khác.
- [x] 3.3 API chi tiết đơn của khách (D10) trả thêm:
  - `hold_expires_at`; kết quả thanh toán (attempt và receipt mới nhất);
  - `booker`; giờ check-in, check-out (eager-load `attendance`).
  - Xử lý sự cố tách sang 3.6.
- [x] 3.4 Frontend:
  - Nút "Hủy thanh toán" có bước xác nhận, đặt ở `HeldReservationBanner.vue` và `ReservationPaymentModal.vue`.
  - Nhãn "Đã hủy thanh toán" dựa trên `release_reason = customer_payment_cancel`.
  - `ReservationDetailModal.vue` hiện đủ trường của 3.3. Đơn chờ có nút mở lại thanh toán và huỷ.
  - Không có nút check-in hay check-out cho khách.
  - Vitest đi kèm.
- [x] 3.5 Test backend `CustomerPaymentCancelTest`:
  - Scenario "Cancel a pending booking" (chỗ và voucher nhả đúng một lần; hạn voucher không đổi).
  - Scenario "Repeated cancellation", "Confirmed booking" (409), "Another customer's booking".
  - Scenario "Transfer arrives after cancellation" (một `FinanceException`, đơn vẫn `cancelled`).
  - Scenario "Customer cancels a booking still pending payment" (không có refund quote hay refund obligation).
  - Đua: xác nhận commit trước → huỷ trả 409; huỷ commit trước → tiền thành ngoại lệ.
  - Scenario "Pending booking detail", "Completed and expired bookings".
- [ ] 3.6 Xử lý sự cố trong chi tiết đơn (D10). Chờ module 2 merge bảng `affected_reservation_cases` (task 4.2 của `align-module-02-venue-court`).
  - API chi tiết đơn của khách trả case đơn bị ảnh hưởng mới nhất: trạng thái và cách đã xử lý (dời, đổi giờ, huỷ, giữ nguyên).
  - `ReservationDetailModal.vue` hiện dòng xử lý sự cố khi có case. Vitest và test backend đi kèm.

## 4. Pha 4 – Chủ cơ sở tra cứu thanh toán bất thường (D8)

- [x] 4.1 `recordReceiptException` chép thêm `purpose`, `purpose_id`, `payment_code` (như khi nhận) và `event_at` từ receipt và attempt khớp mã. Không khớp attempt thì `purpose_id = null`.
- [x] 4.2 `GET owner-payment-exceptions`:
  - Chỉ `organization_owner`, scope theo tổ chức, chỉ loại `owner_payment_*`, phân trang, chỉ đọc.
  - Gắn với capability `direct_owner_payments`, không đi sau `marketplace_payouts`.
  - Trả loại, số tiền, mã thanh toán, thời điểm giao dịch, kết nối, trạng thái, mã đơn và mục đích (khi có).
- [x] 4.3 Frontend: màn "Thanh toán bất thường" cho owner, route `orgRoles: ['organization_owner']`, hiện trên điều hướng khi bật `direct_owner_payments`; màn ngoại lệ tài chính cũ vẫn sau cờ payouts. Bản dịch vi/en. Vitest đi kèm.
- [x] 4.4 Test backend `OwnerPaymentExceptionLookupTest`:
  - Scenario "Transfer after cancellation", "Unknown payment code" (không gán theo số tiền), "Payouts disabled", "Unauthorized reader".
  - Hai tổ chức: owner A chỉ thấy dòng của A.
  - Thiếu tiền, thừa tiền, chuyển muộn có mã đơn; mã lạ không có mã đơn.
  - Scenario "Direct-only cohort opens the console": mục payout và đối soát vẫn ẩn, mục tra cứu vẫn hiện.

## 5. Pha 5 – Lưới 30 phút và phần phụ thuộc module 3 (D11, D12, D14, D15)

Điều kiện (module 3 D29): module 3 đã merge Pha 3 (sự kiện `CourtPricesTookEffect`; trước đó Pha 2 có engine và `AvailabilityController` mới với bước `slot_duration`) và task 5.1–5.3 (phần voucher của engine, `VoucherUsageService`, danh sách voucher). Không chờ cả Pha 5 của module 3: task 5.4–5.5 của họ merge sau task 5.1, 5.5 và 5.7 của change này. Dùng đúng tên ở Open Questions của design.

- [x] 5.1 Cắt hợp đồng quote, hold, checkout (D14, D15): (test: chưa làm)
  - Nhận `court_id`, `starts_at`, `ends_at`; bỏ mảng `slots` (gửi `slots` → 422).
  - Helper kiểm dưới lock: :00 hoặc :30 theo giờ cơ sở, cùng ngày chơi (kết thúc được phép 24:00), kết thúc > bắt đầu, ≥ 30 phút, bắt đầu chưa qua. Sai → 422 `INVALID_BOOKING_TIME`.
  - Áp như nhau cho mọi sân, không phụ thuộc `slot_duration`.
  - `PriceQuote` đổi sang `starts_at/ends_at`, giữ token, hạn 15 phút và các kiểm tra trước khi giữ chỗ (D15).
  - `AvailabilityController` (bản Pha 2 của module 3) đổi bước lưới từ `slot_duration` sang 30 phút cho mọi sân; panel trang chủ theo đó. Test: đơn 19:00–21:00 làm bốn mốc 19:00, 19:30, 20:00, 20:30 không trống (scenario "One court is booked from 19 to 21" của module 3).
  - Ghi chú khi làm (2026-10-22):
    - Helper nằm ở `ReservationIntake` (`bookingInterval`, `recheckBookingTime`, `refuseBookingTime`). Hold và checkout kiểm lại ngay sau khi khoá sân, cơ sở và kiểm điều kiện cơ sở, trên khoảng từ đầu tới cuối của `slot_snapshot`.
    - `ReservationController` trả 422 `INVALID_BOOKING_TIME` khi request có `slots`, trước bước validate, để cả quote, store và checkout trả cùng một mã.
    - `PriceQuoteService::create` nhận `starts_at`, `ends_at`. Đặt định kỳ dùng `createForSlots` (giữ cách tính theo `slot_duration`). Phương án khác: cho đặt định kỳ cũng gửi một khoảng. Loại vì một buổi định kỳ vẫn là các slot dài `slot_duration` (D14), đổi nó nằm ngoài change này.
    - `reprice` lấy độ dài từ slot đầu của `slot_snapshot`, vì mọi slot trong một báo giá dài bằng nhau.
    - `CourtAvailabilityService` (bước lưới của `AvailabilityController`) bỏ `step()` theo `slot_duration`, dùng 30 phút cho mọi sân.
- [x] 5.2 Đơn mới tạo đúng một `ReservationSlot` và một `ReservationEntitlement` phủ cả khoảng; `unit_price` là tiền thuê do engine tính. Có sẵn khi báo giá chỉ có một mốc bắt đầu với độ dài cả khoảng: `priceSnapshots` trả một phần tử, giá là tiền thuê engine tính cho cả khoảng, và hold/checkout tạo một slot, một entitlement cho mỗi phần tử.
- [x] 5.3 Bán lại: đơn mới bán nguyên đơn qua entitlement duy nhất; đơn cũ bán từng slot như cũ. Chữ tiếng Việt ghi khoảng giờ, ví dụ "Pass lại đơn 15:30–17:00", không dùng chữ "slot". Backend không đổi: tin bán gắn với một entitlement, và đơn mới chỉ có một entitlement phủ cả khoảng. Frontend: nút "Pass lại đơn {khoảng giờ}", bỏ "1h"/"1 giờ" ở thông báo đăng tin, nút "Pass Thêm" và tiêu đề mua.
- [x] 5.4 `Court::SLOT_DURATIONS = [30, 60, 90, 120]`. Migration đổi `slot_duration` 45 → 60 và in số dòng đã đổi. Đặt định kỳ vẫn dùng `slot_duration`. (test: chưa làm)
- [ ] 5.5 D12: nối `VoucherUsageService`.
  - `consume`: `processReceipt` (với `$receipt->event_at`), `SplitPaymentService::confirmPaidAttempt` (với `$paidAt`), `ReservationService::confirm` (với `now()`). Đơn 0đ và đơn xác nhận ngay khi tạo do module 3 gọi.
  - `release`: `releaseHeld` và `ReservationService::release`.
  - `VoucherNoLongerApplies` xử lý theo từng đường như D12, nhả bằng `releaseHeld(…, 'expired', 'voucher_changed')` và báo khách. `ReservationController::confirm` trả 422 `VOUCHER_INVALID` kèm lý do.
  - `processReceipt` khoá cơ sở `FOR KEY SHARE` trước khi khoá attempt. `confirmPaidAttempt` khoá đơn bằng `HeldReservationRelease::lock` trước khi đánh dấu nhóm `confirmed`.
  - Nhánh hỏng của `confirmPaidAttempt` và `SplitPaymentService::expire` hết hạn attempt `split_share` còn chờ bằng `DB::afterCommit`, trong một transaction riêng, không làm trong transaction đang giữ nhóm. Phải xong trước khi module 3 merge task 5.4 của họ.
  - Đã làm trước (2026-10-22, chưa có test): `processReceipt` khoá cơ sở `FOR KEY SHARE` trước attempt; `SplitPaymentService::expire` hết hạn attempt `split_share` sau commit. Còn lại chờ `VoucherUsageService` (task 5.1–5.3 của module 3): `consume`, `release`, `VoucherNoLongerApplies`, khoá đơn trước khi nhóm `confirmed` và nhánh hỏng của `confirmPaidAttempt` (dùng cùng cách hết hạn sau commit như `expire`).
- [ ] 5.6 D11: listener `CourtPricesTookEffect(courtIds, effectiveAt)`.
  - Tìm đơn `held` trên `courtIds` có giờ kết thúc sau `effectiveAt`.
  - Mỗi đơn một transaction: khoá qua `HeldReservationRelease::lock`, đơn không còn `held` thì bỏ qua, tính lại bằng `RentalPriceCalculator::quote` với voucher đơn đang giữ và `$heldBy` là chính đơn đó.
  - Tiền khác → `releaseHeld(…, 'expired', 'price_changed')`; chỉ thông báo qua `DurableNotificationService` khi hàm này trả `true`. Tiền bằng → giữ nguyên.
- [ ] 5.7 Frontend:
  - Form chọn giờ bắt đầu và kết thúc trên lưới 30 phút, từ availability mới.
  - Hiện khoảng giờ và thời lượng ("1 giờ 30 phút") thay cho danh sách slot.
  - Panel availability ở trang chủ hiện các mốc 30 phút.
  - Không gửi `coupon_code` hay `loyalty_points`. Chừa chỗ cho phần chọn voucher; task 5.5 của module 3 thêm phần chọn và gửi `voucher_program_id` hoặc `voucher_code`.
  - Rà chữ "slot" trong `vi.ts` cho đặt sân, lỗi, thông báo chờ và bán lại.
  - Vitest đi kèm.
  - Quyết định (2026-10-22): chưa làm phần nào trước khi merge Pha 2 của module 3. Task 2.8 và 2.10 của họ (đang làm, chưa commit) đã sửa chính các file của task này: `ScheduleBookingSummary`, `useCourtSchedule`, `bookingStore`, `BookingAgreement`, `HomeBookingDock`, `HomeView`, `vi.ts`; họ cũng xoá `LoyaltyPointSelector` và `CouponCodeField`, và đổi `useCheckoutCoupon` thành `useSelectionQuote`. Việc bỏ `coupon_code` và `loyalty_points` vì thế thuộc task 2.10 của họ. Các phương án đã cân nhắc: (a) làm form 30 phút ngay trên file cũ, loại vì chắc chắn xung đột khi merge và form vẫn phải gửi `slots` tới khi xong task 5.1; (b) viết sẵn helper chưa có chỗ dùng, loại vì là code để dành. Chọn (c): làm cả task sau khi merge `feat/module03-price-tables`, cùng với task 5.1.
- [ ] 5.8 Test backend (`ThirtyMinuteBookingIntervalTest`, `PendingPriceChangeTest`, `VoucherConsumeOnConfirmTest`):
  - Scenario "Half-hour boundaries" (15:30–17:00 theo ví dụ report), "Thirty-minute minimum", "Off-grid time", "Gapped selection", "Past start", "Court interval length does not apply", "Resale of a new booking".
  - Scenario "Unsupported interval length", "Interval length does not shape customer bookings", "Existing 45-minute courts".
  - Scenario "Amount payable changes", "Amount payable unchanged", "Confirmed booking", "Transfer for an invalidated request".
  - Scenario "Price rules change after quote acceptance" (nay thuộc "Court quotes from price tables" của module 3). Listener truyền `$heldBy`: đơn giữ lượt cuối mà tiền không đổi thì giữ nguyên. Đơn đã bị đường khác nhả trước khi listener khoá thì không bị báo lần nữa.
  - `VoucherConsumeOnConfirmTest`:
    - `consume` chạy đúng một lần khi webhook tới hai lần; xác nhận thất bại thì không tiêu lượt.
    - Khoản nhận tới sau khi chương trình dừng → receipt EXCEPTION `voucher_changed`, đơn `expired`, lượt nhả một lần, job không chạy lại.
    - Khoản nhận có `event_at` trước lúc chương trình kết thúc mà job chạy sau → đơn xác nhận.
    - Chia tiền, phần cuối tới sau khi chương trình dừng → nhóm `expired`, mỗi phần đã trả một ngoại lệ, `expireDue` sau đó không ghi thêm. Attempt của phần còn chờ thành `expired` sau commit; query log không có UPDATE `owner_payment_attempts` nào giữa lúc khoá `split_groups` và lúc commit. `expire` cũng vậy.
    - Hết hạn chia tiền (`SplitPaymentService::expire`) nhả lượt đúng một lần; gọi lại không đổi bộ đếm.
    - Ví legacy qua HTTP có `Idempotency-Key` → 422 `VOUCHER_INVALID`, ví không đổi, đơn `expired`, lượt nhả một lần.
    - Query log của `processReceipt` khoá `venues` trước `owner_payment_attempts` và trước `promotion_programs`.
  - Không thêm scenario dưới "Atomic voucher use"; requirement đó thuộc module 3.

## 6. Pha 6 – Kiểm tra và archive

- [ ] 6.1 Gate mỗi pha:
  - phpunit trên DB `*_test` bằng phpunit config tạm; PHPStan với `.env` tạm; không chạy artisan hay test vào DB prod.
  - Vitest chạy riêng với `--maxWorkers=1`, không chạy cùng lúc với phpunit.
  - Lint và build frontend.
  - Đã đạt ở Pha 0, 2, 3, 4. Còn Pha 5.
- [ ] 6.2 Playwright:
  - Đặt 15:30–17:00 → thanh toán → đã xác nhận. Chờ lưới 30 phút (Pha 5).
  - Giữ chỗ → hủy thanh toán → khung giờ trống lại. Đã có: test 4.6 trong `p0-booking-payment-journey.spec.ts`.
  - Giá tăng giữa chừng → `PRICE_CHANGED` → xác nhận lại. Viết sau Pha 5, xem ghi chú dưới.
  - Owner thấy dòng thanh toán bất thường khi tắt payout. Đã có: cùng test 4.6.
  - Ghi chú (2026-10-21):
    - Kịch bản huỷ thanh toán và kịch bản của owner gộp vào một hành trình 4.6 cho đỡ dựng lại đơn: khách giữ chỗ, người xem khác thấy khung bận, khách huỷ, khung trống lại không cần quét hết hạn; tiền tới sau khi huỷ, owner thấy dòng "Chuyển sau khi đơn hết hạn hoặc đã hủy" ở `/admin/payment-exceptions`, không thấy mục ngoại lệ tài chính cũ. Môi trường e2e không bật `FEATURE_MARKETPLACE_PAYOUTS` nên đúng ca tắt payout.
    - Pha 2 bỏ `.total-num` ở thẻ tóm tắt lịch sân nhưng quên test 4.5.2. Test này nay đọc tổng phải trả từ `[data-testid="agreement-payable"]`, như vitest `ScheduleView`.
    - Chạy lại Khu 2, 3, 4 (57 test) trên một lane cô lập: đạt cả. Sáu test lần đầu dừng ở `/login` vì throttle `auth-login` khi mọi lần đăng nhập dồn vào một backend; chạy lại riêng trên lane mới thì đạt. Cấu hình chuẩn bốn lane không gặp.
    - `PRICE_CHANGED` đã có ở backend và frontend từ Pha 2 nhưng chưa viết Playwright. Phương án: (a) viết ngay, đổi giá bằng cách sửa `PriceRule` và `CourtPriceSlot` của sân e2e; (b) viết sau Pha 5, đổi giá qua bảng giá của module 3. Chọn (b): engine `RentalPriceCalculator` và bảng giá của module 3 thay nguồn giá đó, nên test viết theo (a) phải viết lại ở Pha 5. Trong lúc chờ, hành vi được giữ bởi `AgreedAmountTest` và vitest `ScheduleView` ("resubmits at a changed price only after the customer confirms it").
- [ ] 6.3 Cập nhật `report-traceability.md`: trạng thái từng mã sau mỗi pha, ghi commit và test thật.
  - Đã cập nhật tới Pha 4 (mục "Nghiệm thu theo pha"). Cập nhật lại sau Pha 5.
- [ ] 6.4 Kiểm lại D13 sau khi các pha của module 2 và module 3 xong: BR-76, giá "từ" của FR-69, tài khoản nhận tiền.
- [ ] 6.5 Trước khi archive: dựng lại các khối MODIFIED ("Active inventory exclusivity", "Final bookings and released availability", "Courts carry their own sport and booking interval", "Settlement and payout lifecycle") từ text đang có ở spec chính, chỉ giữ phần sửa của change này; chạy lại `openspec validate --strict`.
- [ ] 6.6 Archive sau `direct-owner-cutover`, `align-module-02-venue-court` và `align-module-03-pricing-promotions`, ngay sau change của module 3.
