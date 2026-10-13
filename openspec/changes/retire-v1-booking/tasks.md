## 0. Điều kiện trước khi bắt đầu

- [x] 0.1 `harden-db-integrity` đã apply và archive. Exclusion constraint `reservation_slots_no_overlap` phải có mặt trên mọi môi trường đích.
  - 2026-10-13: constraint đã có trên prod; `harden-db-integrity` đã archive (`archive/2026-10-13-harden-db-integrity`).

## 1. Phase A — mapping đầy đủ

- [x] 1.1 Tìm mọi writer ghi `bookings`/`booking_slots` mà không đi qua `checkoutV1`/`backfillBooking`: E2E arrange commands, seeders, factory dùng trong command, console. Sửa cho chúng gọi `backfillBooking()` hoặc ghi thẳng sang V2.
  - Giải thích được nguồn gốc 12 booking `direct_owner` chưa có mapping trên bt-server.
- [ ] 1.2 Chạy `BackfillLegacyReservations` trên bt-server và snapshot production.
  - Ghi lại: số booking, số mapping, danh sách booking không map được kèm lý do.
  - Booking không map được thì để nguyên và đưa vào báo cáo, không được ép map.
- [x] 1.3 Feature test cho scenario "Historical booking without a mapping": booking không map được phải được báo cáo, không bị đổi, và làm `v1:retirement-readiness` fail.

## 2. Phase A — backend readers

- [x] 2.1 Migration thêm `reservation_id` (nullable, FK `reservations`, có index) cho `reviews` và `dispute_cases`.
  - Đổi `booking_id` của cả hai bảng thành nullable.
  - Backfill `dispute_cases.reservation_id` qua `legacy_booking_mappings`.
  - `down()` đảo ngược đầy đủ.
- [x] 2.2 `POST reviews` nhận `reservation_code`; `POST disputes` nhận `reservation_id`, chỉ cho reservation mà người gọi có quyền. Cập nhật FormRequest, Resource và test. Không chấp nhận song song field cũ.
- [x] 2.3 Thêm `GET api/v2/admin/reservations` theo luồng Controller → FormRequest → Action → Repository. Booking legacy đã map phải xuất hiện, kèm code gốc.
- [x] 2.4 Viết lại `admin/reports` và `admin/reports.csv` trên `reservations` và owner-payment receipts, giữ nguyên path. Trên cùng bộ data, số liệu phải khớp báo cáo cũ (viết test so sánh).
  - Chỉ đọc `reservations`, không đọc owner-payment receipts (xem design I3, I15).
- [x] 2.5 `DiscountService` (kiểm tra khách quay lại, khoảng dòng 189) đọc `reservations` thay cho `bookings`.

## 3. Phase A — frontend

- [x] 3.1 Đưa `V2ReservationView` vào router (route chi tiết reservation, có guard auth như route booking hiện tại).
- [x] 3.2 Checkout sân: `useCourtSchedule` gọi quote → hold/checkout của `useV2Reservations` thay cho `bookingStore.createBooking`, xong thì chuyển tới chi tiết reservation kèm modal thanh toán.
- [x] 3.3 Lịch sử: `useUserBookings` đọc danh sách reservation. `BookingCancelModal` và `BookingRescheduleModal` bỏ prop `v2` và luôn gọi V2.
- [x] 3.4 Review gửi `reservation_code`; dispute gửi `reservation_id`.
- [x] 3.5 Admin dashboard: danh sách booking dùng `GET api/v2/admin/reservations`; reports giữ nguyên path.
- [x] 3.6 Bỏ chỗ dùng capability `v2_reservations_enabled`. Sửa ghi chú rollback trong `docs/readiness/06_*`: rollback bằng cách redeploy, không bằng flag.
- [x] 3.7 Playwright trên luồng V2, mỗi trường hợp một test (bỏ case coupon, xem design I2 và task 5.5):
  - checkout có điểm loyalty;
  - zero-payable;
  - direct-owner thanh toán QR;
  - cohort legacy wallet;
  - lịch sử hiển thị booking legacy đã map;
  - huỷ;
  - dời lịch đắt hơn và rẻ hơn;
  - review;
  - dispute.
- [x] 3.8 Static gates của frontend pass: type-check, ESLint, oxlint, Prettier, unit test, test kiến trúc. Không được tăng allowlist.

## 4. Phase A — release

- [ ] 4.1 Release phần additive của backend trước (cột mới, route admin mới, reports), sau đó release payload review/dispute cùng lúc với frontend.
- [x] 4.2 Xác nhận trên mọi môi trường: số booking = số mapping, ngoại trừ các booking đã báo cáo ở 1.2 và đang chặn cổng.
  - Prod 2026-10-13: 72 booking, 72 mapping (design C1).

## 5. Phase B — cổng bằng chứng

- [x] 5.1 Lên lịch chạy `v1:adoption-snapshot --days=30` hằng ngày (cron host theo quy ước hiện có).
- [ ] 5.2 Ghi lại snapshot mỗi tuần vào change. Cổng chỉ mở khi đủ cả bốn điều kiện:
  - 30 ngày liền `legacy_booking_count = 0`;
  - không còn booking V1 `pending_payment`;
  - không còn booking V1 `confirmed` ở thời điểm tương lai;
  - số mapping = số booking.
  - Bỏ qua theo quyết định của chủ dự án ngày 2026-10-13 (design C1).
- [x] 5.3 Chạy precheck: không có `booking_slot` active ở tương lai nào thiếu reservation slot active được map. Kết quả phải bằng 0.
  - Prod 2026-10-13: 0 (design C1).
- [ ] 5.4 `php artisan v1:retirement-readiness --approved-change=openspec/changes/retire-v1-booking` trả `ready: true`. Ghi output vào change.
  - Lệnh ghép path với `base_path('../')`, tức là tính từ root repo, nên phải chạy trước khi archive change này.
  - Bỏ qua theo quyết định của chủ dự án ngày 2026-10-13 (design C1).
- [ ] 5.5 Change riêng cho coupon trên V2 checkout (spec `reservations-and-pricing`: V1/V2 dùng chung coupon, hold giữ lượt coupon) đã apply và archive, kèm Playwright checkout có coupon trên luồng V2. Đây là điều kiện trước Phase C.
  - Backend và frontend đã làm luôn trong change này, không tách change (design C4). Còn thiếu Playwright checkout có coupon.

## 6. Phase C — retire

- [x] 6.1 Ghi lại sự phê duyệt của chủ dự án cho Phase C trong change: ai duyệt, ngày nào, dựa trên snapshot nào.
  - Design C1: chủ dự án duyệt ngày 2026-10-13, dựa trên precheck 5.3 trên prod. Cùng lúc chọn bỏ số phiên bản khỏi API (design C5).
- [x] 6.2 Bỏ các route V1 booking: `GET/POST bookings`, `bookings/recurring`, `GET bookings/{code}`, `bookings/{code}/sync-v2`, `legacy/reservations/{reservation}`, `payment-status`, `GET/POST payment`, `refund-quote`, `GET/POST refund-requests`, `reschedule-preview`, `reschedule-requests`, `coupon`, `calendar.ics`, `admin/bookings`, `admin/bookings/{booking}/no-show`.
- [x] 6.3 Xoá code chỉ phục vụ V1:
  - `checkoutV1` và các helper riêng của nó, `CreateBooking`;
  - phần V1 của `BookingController`, `LegacyBookingAdapterController`;
  - lời gọi `backfillBooking` trong checkout;
  - nhánh xác nhận booking trong `ReviewPayment`;
  - `EloquentRefundRepository::createForBooking`;
  - `ExpirePendingBookings`, `NotifyExpiringBookings` và lịch chạy của chúng trong `routes/console.php`.
  - `ExpirePendingBookings` được giữ lại làm sweep cho reservation, bỏ phần V1 (design C3).
- [x] 6.4 Bỏ mọi caller `syncProjection`: `ExpirePendingBookings`, `OwnerRefundService`, `SplitPaymentService`, `OwnerPaymentAttemptService`, `RescheduleService`, `PosService`, `ServiceTabService`. Bỏ luôn nhánh backfill trong `AttendanceService` (khoảng dòng 81–95).
- [x] 6.5 Bỏ nhánh truy vấn `booking_slots` ở 6 chỗ kiểm tra trùng lịch: `SlotSelectionPolicy`, `CourtCommitmentConflictService`, `PriceQuoteService`, `RecurringSeriesService`, `MatchService`, `AvailabilityController`.
- [x] 6.6 Sửa `E2EArrangeTestDataCommand` và `E2EExpireHoldsCommand` để không còn ghi hay expire booking V1.
- [x] 6.7 Frontend: xoá các lời gọi V1 trong `bookingApi`, `bookingStore.createBooking`, phần V1 của `usePaymentProcessing`, route `/payment/:code`, và code chết (`profileApi.getBookings`, `adminApi.updatePayment/markNoShow`, `bookingApi.createRecurring`).
  - Làm cùng C5: frontend chỉ còn một HTTP client gọi `/api` (`apiV2` bị xoá). `usePaymentProcessing`, `PaymentView` và route `/payment/:code?` bị xoá; link cũ chuyển sang `/reservations/:code?`. Các thành phần V2 đổi tên (bỏ tiền tố V2), namespace i18n `v2Reservations` → `reservations`. Ô nhập mã giảm giá gửi `coupon_code` qua báo giá và checkout (5.5).
- [x] 6.8 Test:
  - mỗi route ở 6.2 trả 404 và không tạo hay đổi dòng nào trong `bookings`, `booking_slots`, `payments`, `reservations`, `idempotency_records`, `notifications`;
  - booking legacy vẫn đọc được qua reservation, với code, số tiền và trạng thái gốc;
  - webhook SePay cho POS và nạp ví vẫn ghi `payments` và vẫn chống trùng.
  - Có trong `tests/Feature/Readiness/RetiredV1BookingRoutesTest.php`, kèm `reservation_slots` và `price_quotes` trong danh sách bảng theo dõi, và test cho kịch bản "Stale client calls a versioned path" (`/api/v1`, `/api/v2` → 404, không ghi gì). Coupon trên reservation có trong `tests/Feature/Booking/ReservationCouponTest.php`.
- [ ] 6.9 Xoá test chỉ phục vụ endpoint V1 đã bỏ. Giữ test dùng `Booking` làm dữ liệu lịch sử. Cập nhật allowlist kiến trúc và baseline PHPStan cho các entry không còn khớp (chỉ được giảm).
  - Allowlist kiến trúc và baseline PHPStan đã cập nhật (chỉ giảm; baseline 740 → 700 entry). Việc sửa test dừng giữa chừng theo yêu cầu owner (2026-10-13, "bỏ qua test chỉ sửa thôi check sau"): nhóm checkout/ví/loyalty xong 6/9 file; nhóm slot policy/lịch và nhóm tài khoản/hết hạn/lịch sử còn dở. Cần chạy lại cả bộ PHPUnit để biết test nào còn fail.
- [ ] 6.10 Static gates backend và frontend pass. Bộ PHPUnit trên PostgreSQL và Playwright ở 3.7 pass.
  - Đã pass: PHPStan level 0 và level 5, Pint, test kiến trúc, `vue-tsc`, eslint, oxlint, prettier. Chưa chạy: cả bộ PHPUnit, cả bộ vitest, Playwright. Job `build_and_deploy` cần `backend_test` và `frontend_check`, nên chưa được push lên `main` cho tới khi hai bộ này pass.

## 7. Phase C — release và sau release

- [ ] 7.1 Release Phase C. Xác nhận số `bookings` và `booking_slots` không đổi sau 7 ngày (projection đã đóng băng) và số booking = số mapping.
- [ ] 7.2 Theo dõi log 7 ngày, tìm request 404 tới các path V1 đã bỏ. Nếu có lưu lượng đáng kể thì ghi lại nguồn gọi. Không được khôi phục route.
- [ ] 7.3 Cập nhật `docs/operations/phase-hardening-handover.md` và `docs/readiness/*`: V1 đã retire, cổng đã qua, và từ giờ lịch sử đọc qua reservation.
