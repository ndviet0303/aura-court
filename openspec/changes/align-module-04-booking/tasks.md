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
  - Khi viết lại "Immutable price quotes", giữ câu "a booking still pending payment SHALL follow 'Pending bookings at a price change'" và kịch bản tương ứng.
  - `AvailabilityController` viết lại phải: so chồng lấn nửa mở, coi `held` có `hold_expires_at ≤ now` là trống, sinh mốc 30 phút.
  - Cần ba thứ, có tên và chữ ký cụ thể: sự kiện "giá mới có hiệu lực" (D11), engine tính tiền cho một khoảng, và service voucher `hold / release / consume` (D12).
- [x] 1.3 Gửi session module 2 (đã gửi 2026-10-21):
  - Change này dựng khối MODIFIED "Final bookings and released availability" và "Courts carry their own sport and booking interval" trên text của `align-module-02-venue-court`. Nếu module 2 sửa lại hai requirement đó thì báo để dựng lại.
  - Độ dài slot của sân chỉ còn 30/60/90/120; đặt sân của khách theo lưới 30 phút.
  - Nhắc task 2.16 của module 2: bỏ `hold_ttl_minutes` và `require_continuity` khỏi form và request chính sách.
- [x] 1.4 Ghi chú phối hợp vào `openspec/changes/direct-owner-cutover/tasks.md`: requirement "One-hour booking slots" bị change này REMOVED; decision 8 (ẩn ngoại lệ khi tắt payout) được nới cho màn tra cứu chỉ đọc.
- [x] 1.5 `openspec validate align-module-04-booking --strict` đạt.

## 2. Pha 2 – Tạo yêu cầu đặt sân (D2, D3, D4, D5, D6, D7)

Điều kiện: Pha 0 của module 2 đã merge (`9dddc773`).

- [ ] 2.1 Tách `releaseHeld(Reservation $locked, string $status, string $reason)` từ `ExpirePendingBookings` (D2).
  - Nhả slot, entitlement, lượt voucher, điểm; cho attempt đang chờ hết hạn; ghi `metadata.release_reason`.
  - Không làm gì khi đơn không còn `held`.
  - `ExpirePendingBookings` gọi hàm này với `('expired', 'payment_deadline')`.
  - Test: `HeldReservationReleaseTest`. Nhả hai lần chỉ hoàn điểm và nhả voucher một lần; đơn `confirmed` không đổi.
- [ ] 2.2 Helper dùng chung trước khi chèn (`ReservationIntake`), gọi từ `OrdinaryCheckoutService::checkout` và `ReservationService::hold`. Thứ tự:
  - Kiểm SĐT bằng `User::PHONE_PATTERN` → 422 `PHONE_REQUIRED` trước khi khoá.
  - Khoá; nhả các đơn `held` quá hạn chồng lấn trên sân đó (theo id) bằng `releaseHeld(…, 'expired', 'payment_deadline')` (D7).
  - Tính tiền; nếu cao hơn `expected_total` → 409 `PRICE_CHANGED` kèm `{rental, discount, total}` (D5).
  - Tính `hold_expires_at = min(now + 10 phút, giờ bắt đầu, hạn voucher)` khi tiền phải trả > 0; hạn ≤ now → 422 `PAYMENT_WINDOW_CLOSED` (D3).
  - Ghi `metadata.booker = {name, phone, email}` (D4).
  - Bỏ đọc `booking.hold_ttl_minutes` ở `OrdinaryCheckoutService.php:333` và `ReservationService.php:273`.
- [ ] 2.3 Request checkout và hold: `expected_total` bắt buộc, số nguyên ≥ 0; thiếu → 422.
- [ ] 2.4 `SlotSelectionPolicy` (D6, D7):
  - Luôn kiểm tính liên tục, bỏ đọc `require_continuity`.
  - Truy vấn chồng lấn bỏ qua `held` có `hold_expires_at ≤ now`.
  - Bỏ `require_continuity` khỏi `E2ESeedFixturesCommand`.
- [ ] 2.5 `ReservationPaymentService::initiate` (D3): attempt dùng `expires_at = hold_expires_at`, bỏ `now()->addMinutes(15)`. Attempt còn chờ thì trả lại đúng attempt đó.
- [ ] 2.6 Migration backfill `metadata.booker` cho reservation chưa có, lấy từ hồ sơ lúc chạy, ghi `metadata.booker_backfilled = true`.
- [ ] 2.7 Màn danh sách đơn của chủ sân và tra mã ở quầy đọc người đặt từ `metadata.booker`, không đọc hồ sơ hiện tại.
- [ ] 2.8 Frontend `BookingView.vue`, `QuoteCalculatorCard.vue`, `ReservationPaymentModal.vue`:
  - Khối người đặt (tên, SĐT, email). Thiếu SĐT thì hiện link "Cập nhật số điện thoại" và khoá nút gửi.
  - Dòng tiền thuê, voucher, tiền giảm, tổng phải trả; gửi `expected_total` bằng tổng đang hiển thị.
  - `PRICE_CHANGED`: hiện số mới, khách bấm xác nhận lại mới gửi tiếp.
  - Thông báo cho `PHONE_REQUIRED` và `PAYMENT_WINDOW_CLOSED` (vi/en).
  - Đếm ngược theo `hold_expires_at`.
  - Vitest đi kèm.
- [ ] 2.9 Test backend (`PaymentDeadlineTest`, `BookerSnapshotTest`, `AgreedAmountTest`, `OverdueHoldTest`):
  - Scenario "Start time caps the deadline" (14:58 → 15:00; tiền tới 15:01 là ngoại lệ `transfer_after_expiry`).
  - Scenario "Voucher end caps the deadline", "Ten-minute cap" (chính sách 30 phút bị bỏ qua), "Reopening the payment information", "No payment window left".
  - Scenario "Missing phone number", "Profile edited after booking".
  - Scenario "Price rose before submission" (không có dòng reservation, slot, entitlement, coupon usage, point ledger, attempt nào), "Price fell before submission", "Amount not sent".
  - Scenario "Overdue hold does not block", "Live hold blocks".
  - Scenario "Configured booking window": hai slot không liền nhau → 422 dù cơ sở từng đặt `require_continuity = false`.
  - Cập nhật các test đang gửi checkout không có `expected_total` hoặc dựa vào `hold_ttl_minutes`.

## 3. Pha 3 – Huỷ đơn chờ thanh toán và chi tiết đơn (D1, D2, D10)

- [ ] 3.1 `POST reservations/{code}/cancel-payment`, chỉ chủ đơn (D1).
  - Transaction: khoá attempt của đơn rồi khoá reservation.
  - `held` → `releaseHeld(…, 'cancelled', 'customer_payment_cancel')`.
  - `cancelled` hoặc `expired` → 200, không làm gì. `confirmed` → 409. Người khác → 403, hoặc 404 khi không thấy đơn.
- [ ] 3.2 Kiểm webhook: receipt cho attempt đã hết hạn vì huỷ phải thành ngoại lệ `transfer_after_expiry` và không khôi phục đơn. Sửa `applyAttemptChecks` nếu hiện tại cho kết quả khác.
- [ ] 3.3 API chi tiết đơn của khách (D10) trả thêm:
  - `hold_expires_at`; kết quả thanh toán (attempt và receipt mới nhất);
  - `booker`; giờ check-in, check-out (eager-load `attendance`);
  - xử lý sự cố từ bản ghi đơn bị ảnh hưởng của module 2.
- [ ] 3.4 Frontend:
  - Nút "Hủy thanh toán" có bước xác nhận, đặt ở `HeldReservationBanner.vue` và `ReservationPaymentModal.vue`.
  - Nhãn "Đã hủy thanh toán" dựa trên `release_reason = customer_payment_cancel`.
  - `ReservationDetailModal.vue` hiện đủ trường của 3.3. Đơn chờ có nút mở lại thanh toán và huỷ.
  - Không có nút check-in hay check-out cho khách.
  - Vitest đi kèm.
- [ ] 3.5 Test backend `CustomerPaymentCancelTest`:
  - Scenario "Cancel a pending booking" (chỗ, voucher, điểm nhả đúng một lần; hạn voucher không đổi).
  - Scenario "Repeated cancellation", "Confirmed booking" (409), "Another customer's booking".
  - Scenario "Transfer arrives after cancellation" (một `FinanceException`, đơn vẫn `cancelled`).
  - Scenario "Customer cancels a booking still pending payment" (không có refund quote hay refund obligation).
  - Đua: xác nhận commit trước → huỷ trả 409; huỷ commit trước → tiền thành ngoại lệ.
  - Scenario "Pending booking detail", "Completed and expired bookings".

## 4. Pha 4 – Chủ cơ sở tra cứu thanh toán bất thường (D8)

- [ ] 4.1 `recordReceiptException` chép thêm `purpose`, `purpose_id`, `payment_code` (như khi nhận) và `event_at` từ receipt và attempt khớp mã. Không khớp attempt thì `purpose_id = null`.
- [ ] 4.2 `GET owner-payment-exceptions`:
  - Chỉ `organization_owner`, scope theo tổ chức, chỉ loại `owner_payment_*`, phân trang, chỉ đọc.
  - Gắn với capability `direct_owner_payments`, không đi sau `marketplace_payouts`.
  - Trả loại, số tiền, mã thanh toán, thời điểm giao dịch, kết nối, trạng thái, mã đơn và mục đích (khi có).
- [ ] 4.3 Frontend: màn "Thanh toán bất thường" cho owner, route `orgRoles: ['organization_owner']`, hiện trên điều hướng khi bật `direct_owner_payments`; màn ngoại lệ tài chính cũ vẫn sau cờ payouts. Bản dịch vi/en. Vitest đi kèm.
- [ ] 4.4 Test backend `OwnerPaymentExceptionLookupTest`:
  - Scenario "Transfer after cancellation", "Unknown payment code" (không gán theo số tiền), "Payouts disabled", "Unauthorized reader".
  - Hai tổ chức: owner A chỉ thấy dòng của A.
  - Thiếu tiền, thừa tiền, chuyển muộn có mã đơn; mã lạ không có mã đơn.
  - Scenario "Direct-only cohort opens the console": mục payout và đối soát vẫn ẩn, mục tra cứu vẫn hiện.

## 5. Pha 5 – Lưới 30 phút và phần phụ thuộc module 3 (D11, D12, D14, D15)

Điều kiện: module 3 đã merge engine tính tiền theo phút, service voucher, sự kiện "giá mới có hiệu lực" và `AvailabilityController` mới. Dùng đúng tên module 3 đưa ra.

- [ ] 5.1 Cắt hợp đồng quote, hold, checkout (D14, D15):
  - Nhận `court_id`, `starts_at`, `ends_at`; bỏ mảng `slots` (gửi `slots` → 422).
  - Helper kiểm dưới lock: :00 hoặc :30 theo giờ cơ sở, cùng ngày chơi (kết thúc được phép 24:00), kết thúc > bắt đầu, ≥ 30 phút, bắt đầu chưa qua. Sai → 422 `INVALID_BOOKING_TIME`.
  - Áp như nhau cho mọi sân, không phụ thuộc `slot_duration`.
- [ ] 5.2 Đơn mới tạo đúng một `ReservationSlot` và một `ReservationEntitlement` phủ cả khoảng; `unit_price` là tiền thuê do engine tính.
- [ ] 5.3 Bán lại: đơn mới bán nguyên đơn qua entitlement duy nhất; đơn cũ bán từng slot như cũ. Chữ tiếng Việt ghi khoảng giờ, ví dụ "Pass lại đơn 15:30–17:00", không dùng chữ "slot".
- [ ] 5.4 `Court::SLOT_DURATIONS = [30, 60, 90, 120]`. Migration đổi `slot_duration` 45 → 60 và in số dòng đã đổi. Đặt định kỳ vẫn dùng `slot_duration`.
- [ ] 5.5 D12: gọi `consume($reservation)` trong transaction xác nhận (webhook và đơn 0đ). `releaseHeld` gọi `release` của service voucher.
- [ ] 5.6 D11: listener sự kiện "giá mới có hiệu lực".
  - Tìm đơn `held` có khoảng nằm trong vùng hiệu lực, tính lại bằng engine.
  - Tiền khác → `releaseHeld(…, 'expired', 'price_changed')` và thông báo qua `DurableNotificationService`. Tiền bằng → giữ nguyên.
- [ ] 5.7 Frontend:
  - Form chọn giờ bắt đầu và kết thúc trên lưới 30 phút, từ availability mới.
  - Hiện khoảng giờ và thời lượng ("1 giờ 30 phút") thay cho danh sách slot.
  - Rà chữ "slot" trong `vi.ts` cho đặt sân, lỗi, thông báo chờ và bán lại.
  - Vitest đi kèm.
- [ ] 5.8 Test backend (`ThirtyMinuteBookingIntervalTest`, `PendingPriceChangeTest`, `VoucherConsumeOnConfirmTest`):
  - Scenario "Half-hour boundaries" (15:30–17:00 theo ví dụ report), "Thirty-minute minimum", "Off-grid time", "Gapped selection", "Past start", "Court interval length does not apply", "Resale of a new booking".
  - Scenario "Unsupported interval length", "Interval length does not shape customer bookings", "Existing 45-minute courts".
  - Scenario "Amount payable changes", "Amount payable unchanged", "Confirmed booking", "Transfer for an invalidated request".
  - Scenario "Price rules change after quote acceptance".
  - `consume` chạy đúng một lần khi webhook tới hai lần; xác nhận thất bại thì không tiêu lượt.

## 6. Pha 6 – Kiểm tra và archive

- [ ] 6.1 Gate mỗi pha:
  - phpunit trên DB `*_test` bằng phpunit config tạm; PHPStan với `.env` tạm; không chạy artisan hay test vào DB prod.
  - Vitest chạy riêng với `--maxWorkers=1`, không chạy cùng lúc với phpunit.
  - Lint và build frontend.
- [ ] 6.2 Playwright:
  - Đặt 15:30–17:00 → thanh toán → đã xác nhận.
  - Giữ chỗ → hủy thanh toán → khung giờ trống lại.
  - Giá tăng giữa chừng → `PRICE_CHANGED` → xác nhận lại.
  - Owner thấy dòng thanh toán bất thường khi tắt payout.
- [ ] 6.3 Cập nhật `report-traceability.md`: trạng thái từng mã sau mỗi pha, ghi commit và test thật.
- [ ] 6.4 Kiểm lại D13 sau khi các pha của module 2 và module 3 xong: BR-76, giá "từ" của FR-69, tài khoản nhận tiền.
- [ ] 6.5 Trước khi archive: dựng lại các khối MODIFIED ("Active inventory exclusivity", "Immutable price quotes", "Final bookings and released availability", "Courts carry their own sport and booking interval", "Settlement and payout lifecycle") từ text đang có ở spec chính, chỉ giữ phần sửa của change này; chạy lại `openspec validate --strict`.
- [ ] 6.6 Archive sau `direct-owner-cutover`, `align-module-02-venue-court` và change của module 3.
