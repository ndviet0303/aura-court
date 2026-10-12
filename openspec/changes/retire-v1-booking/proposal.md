## Why

Reservation đã là đường ghi chính (`routes/api.php:430`: "Reservation commands are the canonical v2 write path"). Nhưng V1 vẫn đang sống song song và kéo theo chi phí ở khắp nơi:

- **Ghi kép.** `POST v1/bookings` ghi `bookings`, `booking_slots`, `payments`, rồi ghi kép sang `reservations` qua `LegacyBookingBackfillService::backfillBooking`.
- **Kiểm tra trùng lịch.** Phải quét cả hai bảng slot, ở 6 chỗ: `SlotSelectionPolicy`, `CourtCommitmentConflictService`, `PriceQuoteService`, `RecurringSeriesService`, `MatchService`, `AvailabilityController`.
- **Sync ngược.** Mỗi thay đổi trên reservation phải sync ngược về projection V1, qua 7 nhóm caller `syncProjection`.
- **Báo cáo.** Admin report, kiểm tra khách quay lại (`DiscountService`), review và dispute vẫn đọc `bookings`.
- **Mapping thiếu.** Trên bt-server, 85/280 booking chưa có reservation tương ứng.
- **Frontend.** Checkout sân, trang thanh toán, lịch sử kèm huỷ/dời lịch, review và admin bookings đều chỉ chạy V1. `V2ReservationView` đã có sẵn nhưng chưa nằm trong router, nên không ai vào được.

Codebase đã dựng sẵn cổng cho việc này. `v1:retirement-readiness` là "hard stop" (`docs/operations/phase-hardening-handover.md:19`) cho tới khi có hai thứ: một change riêng được duyệt, và adoption snapshot. Change này chính là change đó.

Không tìm thấy client V1 nào ngoài frontend: không có mobile app, không có Postman/OpenAPI, không có đối tác tích hợp. Caller bên ngoài duy nhất là webhook SePay, và nó nằm ngoài phạm vi change này.

## What Changes

Chia làm ba phase. Phase sau chỉ được bắt đầu khi phase trước đã xong.

**Phase A — chuyển client và các luồng đọc sang V2, V1 vẫn chạy nguyên:**
- Backfill mọi booking còn thiếu mapping. Điều tra writer nào ghi `bookings` mà bỏ qua backfill.
- Frontend:
  - đưa `V2ReservationView` vào router;
  - checkout sân (`useCourtSchedule`) đi qua quote/checkout V2;
  - trang thanh toán dùng luồng thanh toán V2;
  - lịch sử đọc `reservations`, gồm cả booking legacy đã map;
  - huỷ/dời lịch luôn gọi V2;
  - review và dispute tham chiếu reservation;
  - admin dashboard đọc danh sách reservation.
- Backend:
  - thêm `reservation_id` cho `reviews` và `dispute_cases`, backfill từ mapping;
  - API review/dispute nhận reservation;
  - thêm `GET api/v2/admin/reservations`;
  - admin reports và kiểm tra khách quay lại đọc `reservations`.

**Phase B — cổng bằng chứng:** deploy Phase A, rồi chạy `v1:adoption-snapshot --days=30` hằng ngày. Cổng mở khi đủ cả bốn điều kiện:
- trong 30 ngày liền không có booking V1 mới;
- không còn booking V1 nào ở trạng thái active;
- mọi booking đều đã có mapping;
- `v1:retirement-readiness` pass.

**Phase C — retire V1:**
- **BREAKING:** bỏ toàn bộ route V1 dành riêng cho booking:
  - `GET/POST bookings`, `bookings/recurring`, `bookings/{code}`, `sync-v2`;
  - `legacy/reservations/{r}`;
  - `payment-status`, `payment`, `refund-quote`, `refund-requests`, `reschedule-preview`, `reschedule-requests`, `coupon`, `calendar.ics`;
  - `admin/bookings`, `admin/bookings/{booking}/no-show`.
- Bỏ code chỉ phục vụ V1:
  - `checkoutV1`, `CreateBooking`, lời gọi ghi kép, mọi `syncProjection`;
  - nhánh truy vấn `booking_slots` ở 6 chỗ kiểm tra trùng lịch;
  - lệnh `booking:expire-pending` và `booking:notify-expiring`;
  - nhánh xác nhận booking trong `ReviewPayment`;
  - `EloquentRefundRepository::createForBooking` (không có caller);
  - code V1 trong frontend.
- **Giữ lại:**
  - `bookings`, `booking_slots` và hai bảng mapping, làm lịch sử chỉ đọc. Spec cấm xoá dữ liệu lịch sử.
  - `payments`: SePay vẫn ghi vào đây cho POS và nạp ví, đồng thời dùng nó để chống trùng.
  - `refunds`: lịch sử.
  - `V1AdoptionService` và công cụ backfill/pilot.
  - `GET/PATCH admin/payments` cho giao dịch cần duyệt tay, admin refunds, `GET availability` (đọc reservation).

## Capabilities

### New Capabilities

Không có.

### Modified Capabilities

- `reservations-and-pricing`: yêu cầu "Versioned reservation API compatibility" đổi từ "V1 và V2 cùng được hỗ trợ trong giai đoạn chuyển đổi" thành: V2 là API duy nhất. V1 chỉ được giữ cho tới khi cổng retirement mở. Sau đó endpoint V1 từ chối mọi request, còn lịch sử booking đọc qua reservation đã map.
- `platform-readiness`: yêu cầu "Rehearsed data migration and rollback" đổi theo: giai đoạn tương thích `/api/v1` chỉ kết thúc qua cổng retirement. Lịch sử booking legacy vẫn phải đọc được, và số booking phải khớp với số reservation đã map.

## Impact

- **Backend:**
  - Phase A: migration thêm `reservation_id` cho `reviews` và `dispute_cases`; `ReviewController`, `AdminDisputeController`, `DiscountService`, admin reports, `AvailabilityController`; route mới `GET api/v2/admin/reservations`.
  - Phase C: xoá `BookingController` (phần V1), `LegacyBookingAdapterController`, `CreateBooking`, `checkoutV1`, `ExpirePendingBookings`, `NotifyExpiringBookings`; sửa 6 chỗ kiểm tra trùng lịch và 7 nhóm caller `syncProjection`.
- **Frontend:**
  - Phase A: `useCourtSchedule`, `bookingStore`, `usePaymentProcessing`, `useUserBookings`, `BookingCancelModal`, `BookingRescheduleModal`, `useAdminDashboard`, router.
  - Phase C: xoá `bookingApi` V1 và các code chết (`profileApi.getBookings`, `adminApi.updatePayment/markNoShow`, `bookingApi.createRecurring`).
- **Data:** không xoá bảng nào. Từ Phase C, `bookings` và `booking_slots` đứng yên như ảnh chụp lịch sử. Rollback Phase C chỉ cần deploy lại bản trước, không cần phục hồi dữ liệu.
- **Phụ thuộc:** nên archive `harden-db-integrity` trước. Exclusion constraint của change đó đã phủ cả slot do V1 ghi kép, nên sau Phase C không mất lớp chặn nào.
- **Non-goals:**
  - Xoá bảng `bookings`, `booking_slots`, `payments`, `refunds` hoặc hai bảng mapping.
  - Tắt webhook SePay hay luồng nạp ví/POS.
  - Viết lại các spec khác có nhắc "V1/V2" (`account-lifecycle`, `on-site-attendance`, `runtime-execution`, `point-of-sale-and-loyalty`, ledger reconciliation). Các câu đó vẫn đúng với booking V1 lịch sử.
  - Sửa câu "V1/V2 direct checkout SHALL share…" trong "Authoritative ordinary checkout and promotion result". Sau Phase C câu đó không còn đối tượng áp dụng nhưng cũng không sai, nên để dọn khi archive.
