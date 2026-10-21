## Why

Report Module 04 (`module-04-report.md`: FR-69..FR-81, BR-72..BR-88, 5 bước, UC-4.1..UC-4.12) mô tả vòng đời một đơn đặt sân:

- Một đơn là một sân trong một khoảng liên tục, theo mốc 30 phút.
- Đơn chờ thanh toán giữ chỗ tối đa 10 phút. Hạn không vượt giờ bắt đầu chơi và hạn voucher.
- Khách tự huỷ đơn đang chờ thanh toán.
- Đơn chờ được kiểm lại khi giá mới có hiệu lực.
- Chủ cơ sở tra cứu được các khoản thanh toán bất thường.

Đối chiếu code `311790f3` (`module-04-gap-audit.md`) cho kết quả:

| Kết quả | Số mã |
|---|---|
| Khớp | 6 |
| Khớp một phần | 15 |
| Thiếu hẳn | 2 |
| Làm ngược report | 1 |
| Lệch có chủ đích theo spec cũ | 6 |

Lỗi nặng nhất là hạn thanh toán. Hạn hiện là `now + hold_ttl_minutes` và không bị chặn bởi giờ bắt đầu. Vì vậy khách giữ chỗ lúc 14:58 cho lượt 15:00 vẫn trả và được xác nhận tới 15:13.

Pha 0 (merge `7312b44c`) đã vá các lỗi prod sau:
- webhook SePay nhận secret rỗng;
- staff đọc đơn ngoài cơ sở được phân công;
- audit log chéo tổ chức;
- QR gửi ra `api.qrserver.com`;
- lịch sử đơn chỉ có 20 dòng;
- nhãn trạng thái sai;
- ngày UTC trên lịch sân.

Change này đặc tả phần còn lại để các pha 2–6 bám report. Report thay một số quyết định cũ:
- `archive/2026-09-12-remove-booking-cancellation-and-rescheduling`: cấm mọi huỷ. Nay mở lại cho đơn chờ thanh toán.
- `archive/2026-09-27-align-report-and-direct-owner-payments`: TTL giữ chỗ theo chính sách cơ sở, liên tục là tuỳ chọn.
- `direct-owner-cutover`:
  - slot 1 giờ;
  - ẩn mục ngoại lệ tài chính khi tắt payout (decision 8).

Người dùng chốt Q1 = A ngày 2026-10-21: theo report, lưới 30 phút.

## What Changes

- **Lưới 30 phút và một khoảng liên tục (D6, D14; BR-73, BR-75).**
  - **BREAKING:** báo giá, giữ chỗ và checkout nhận `court_id`, `starts_at`, `ends_at` thay cho mảng `slots`.
  - Hai mốc phải rơi vào :00 hoặc :30 theo giờ cơ sở, cùng một ngày chơi (kết thúc được phép là 24:00). Phải có kết thúc > bắt đầu, tối thiểu 30 phút, bắt đầu chưa qua.
  - Các điều kiện kiểm ở một chỗ dùng chung, dưới lock.
  - Đơn mới lưu đúng một khoảng `[starts_at, ends_at)`, giá tính bằng engine theo phút của module 3.
  - **BREAKING:** bỏ requirement "One-hour booking slots" của cutover. Đơn mới được bán lại nguyên đơn. Đơn cũ giữ các slot đã ghi.
  - Độ dài slot của sân không còn ảnh hưởng tới đặt sân của khách, chỉ còn nhận 30, 60, 90 hoặc 120 phút (bỏ 45).
  - Bỏ `require_continuity`; liên tục là bắt buộc.
- **Hạn thanh toán (D3; BR-79, FR-72, FR-73).**
  - Hạn = `min(lúc giữ + 10 phút, giờ bắt đầu, hạn voucher)`, lưu ở `hold_expires_at`.
  - Yêu cầu thanh toán dùng đúng hạn đó. Mở lại màn thanh toán trả cùng mã và cùng hạn.
  - Hạn ≤ now → 422 `PAYMENT_WINDOW_CLOSED`.
  - Bỏ `hold_ttl_minutes` khỏi đặt sân thường.
- **Thông tin người đặt (D4; BR-74).**
  - Lưu `metadata.booker {name, phone, email}` lúc tạo đơn.
  - Thiếu SĐT hợp lệ → 422 `PHONE_REQUIRED`.
  - Sửa hồ sơ sau đó không đổi đơn.
- **Số tiền khách đã đồng ý (D5; BR-80).**
  - **BREAKING:** checkout bắt buộc gửi `expected_total`.
  - Tổng tính lại cao hơn → 409 `PRICE_CHANGED`, kèm số mới, không tạo gì.
- **Giữ chỗ quá hạn không chặn (D7; BR-77, FR-70).**
  - Availability coi hold đã quá hạn là trống.
  - Giữ chỗ mới nhả các hold quá hạn chồng lấn trước khi chèn, không chờ job quét.
- **Khách huỷ đơn chờ thanh toán (D1, D2; FR-76, BR-84, BR-85).**
  - `POST reservations/{code}/cancel-payment` chuyển `held` → `cancelled`, nhả chỗ và voucher đúng một lần.
  - Gọi lại khi đơn đã huỷ hoặc hết hạn → 200, không làm gì.
  - Đơn đã xác nhận → 409.
  - Tiền tới sau khi huỷ → thanh toán bất thường, đơn vẫn huỷ.
  - Hết hạn, huỷ, nhả hold quá hạn và đổi giá dùng chung một bước nhả.
  - **BREAKING (spec):** "Final bookings and released availability" thu hẹp lệnh cấm khách huỷ về đơn đã xác nhận.
- **Kiểm lại đơn chờ khi giá mới có hiệu lực (D11; FR-78, BR-87).**
  - Tiền phải trả đổi: yêu cầu cũ hết hiệu lực, nhả chỗ và voucher, báo khách.
  - Tiền không đổi: giữ nguyên đơn và hạn.
  - Đơn đã xác nhận không tính lại.
  - Gắn vào hook công bố giá của module 3.
- **Ghi nhận voucher lúc xác nhận (D12; BR-83, FR-74, FR-75).** Gọi `consume` của service voucher module 3, trong cùng transaction xác nhận.
- **Danh sách và chi tiết đơn (D9, D10; FR-79, FR-80).**
  - Chi tiết có hạn thanh toán, kết quả thanh toán, người đặt, check-in/check-out và sự cố.
  - Nhãn trạng thái và QR đơn như Pha 0 đã làm.
- **Chủ cơ sở tra cứu thanh toán bất thường (D8; FR-81, UC-4.12, BR-86, BR-88).**
  - Thêm `GET owner-payment-exceptions`: chỉ đọc, chỉ owner, scope theo tổ chức, gắn với `direct_owner_payments` chứ không với `marketplace_payouts`.
  - Mỗi ngoại lệ có mã đơn, mã thanh toán và thời điểm giao dịch.
- **Phạm vi đọc của staff (BR-88).** Tra mã đơn chỉ trong cơ sở được phân công. Code đã làm ở Pha 0; change này ghi vào spec.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `reservations-and-pricing`:
  - Thêm các requirement: lưới 30 phút, hạn thanh toán, người đặt, số tiền đã đồng ý, khách huỷ đơn chờ, đơn chờ tại mốc đổi giá, danh sách và chi tiết đơn.
  - Sửa "Active inventory exclusivity" (liên tục bắt buộc, hold quá hạn không chặn), "Immutable price quotes" (chỉ đơn đã xác nhận giữ giá) và "Final bookings and released availability" (cấm khách huỷ chỉ áp cho đơn đã xác nhận).
  - Bỏ "One-hour booking slots".
- `venue-court-operations`: "Courts carry their own sport and booking interval" bỏ độ dài 45 phút; đặt sân của khách theo lưới 30 phút thay vì độ dài slot của sân.
- `direct-owner-payments`: thêm "Owner lookup of payment exceptions".
- `on-site-attendance`: thêm "Attendance lookup scope".
- `financial-ledger-and-payouts`: "Settlement and payout lifecycle" ghi rõ màn tra cứu thanh toán bất thường của `direct-owner-payments` không thuộc nhóm màn tài chính ẩn khi tắt payout. Màn ngoại lệ tài chính cũ (xử lý, đối soát) vẫn sau cờ payout (design D8).

## Impact

- **Backend theo pha** (`module-04-report-alignment.plan.md`):
  - Pha 2 – tạo yêu cầu:
    - Một helper trước khi chèn, dùng chung cho `OrdinaryCheckoutService::checkout` và `ReservationService::hold`: hạn thanh toán, người đặt, `expected_total`, nhả hold quá hạn.
    - `SlotSelectionPolicy` luôn kiểm liên tục.
    - `ReservationPaymentService::initiate` dùng `hold_expires_at`.
  - Pha 3: `releaseHeld`, endpoint huỷ, chi tiết đơn.
  - Pha 4: endpoint ngoại lệ cho owner, thêm trường vào `recordReceiptException`.
  - Pha 5, chờ module 3:
    - lưới 30 phút với `starts_at/ends_at` trên engine theo phút;
    - hook đổi giá;
    - `consume` voucher.
- **API:**
  - Thêm `POST reservations/{code}/cancel-payment` và `GET owner-payment-exceptions`.
  - Mã lỗi mới: `PHONE_REQUIRED`, `PRICE_CHANGED`, `PAYMENT_WINDOW_CLOSED`, `INVALID_BOOKING_TIME`.
  - Đổi hợp đồng báo giá, giữ chỗ, checkout như trên.
- **Frontend:**
  - Form đặt chọn giờ bắt đầu và kết thúc trên lưới 30 phút.
  - Khối người đặt có link "Cập nhật số điện thoại".
  - Dòng tiền thuê, giảm và tổng; xử lý `PRICE_CHANGED`.
  - Nút "Hủy thanh toán" có bước xác nhận.
  - Chi tiết đơn đủ trường FR-79.
  - Màn "Thanh toán bất thường" cho owner.
  - Bản dịch vi/en, chữ "Pass lại đơn này".
- **Dữ liệu:**
  - Không cần migration cho người đặt (dùng metadata).
  - Sân có `slot_duration` = 45 chuyển thành 60 trong migration. Prod sau reseed không có dòng nào.
  - Đơn cũ giữ slot đã ghi.
- **Phối hợp:**
  - Thứ tự archive: `direct-owner-cutover` → `align-module-02-venue-court` → change của module 3 → change này.
  - Khối MODIFIED trùng header được dựng lại từ text đang có ở spec chính lúc archive.
  - Module 2 nhận task bỏ `hold_ttl_minutes` và `require_continuity` khỏi form chính sách. Module 3 viết lại availability (khoảng nửa mở, bỏ hold quá hạn, mốc 30 phút).
- **Non-goals:**
  - BR-76 và giá "từ" ở FR-69: thuộc module 2 và 3.
  - Đặt hộ ở quầy.
  - Tài khoản nhận tiền khai sau khi tạo cơ sở.
  - Nhóm chia tiền (`split-groups`).
  - Đặt định kỳ.
