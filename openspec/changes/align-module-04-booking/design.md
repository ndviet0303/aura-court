## Context

Lý do và phạm vi xem `proposal.md`. Phần này chỉ ghi hiện trạng code tại `7312b44c` (sau Pha 0) và các ràng buộc quyết định cách làm.

- **Hai đường tạo đơn.**
  - `OrdinaryCheckoutService::checkout` (dùng `quote_token`) và `ReservationService::hold`.
  - Cả hai đều khoá rồi gọi `SlotSelectionPolicy::assertAllowed`. Mỗi slot 1 giờ được tạo thành một `ReservationSlot` và một `ReservationEntitlement`, giá lấy theo slot từ `PriceQuoteService` (báo giá sống 15 phút).
- **Hạn giữ chỗ.**
  - `hold_expires_at = now + booking.hold_ttl_minutes` (`OrdinaryCheckoutService.php:333`, `ReservationService.php:273`).
  - `ReservationPaymentService::initiate` cho attempt hết hạn ở `min(hold_expires_at, now + 15 phút)` (dòng 84–85).
  - Hạn này không bị chặn bởi giờ bắt đầu hay hạn voucher.
- **`SlotSelectionPolicy`** kiểm thời điểm đã qua, lịch, khoá sân, horizon, độ dài và chồng lấn trên `held`/`confirmed`.
  - Tính liên tục chỉ được kiểm khi cơ sở bật `require_continuity` (dòng 106).
  - Kiểm chồng lấn không xét `hold_expires_at`.
- **Ràng buộc gist** (migration `2026_10_11_000010`) chặn hai slot `held`/`confirmed` chồng lấn trên cùng sân.
- **Nhả chỗ.**
  - `ExpirePendingBookings` nhả đầy đủ: slot, entitlement, lượt voucher, điểm, attempt.
  - `ReservationService::release` không nhả điểm và không cho attempt hết hạn.
- **Webhook xác nhận.** `OwnerPaymentAttemptService::processReceipt` khoá theo thứ tự receipt → attempt (theo mã) → reservation (theo `purpose_id`).
- **Ghi ngoại lệ.**
  - `recordReceiptException` ghi một `FinanceException` loại `owner_payment_*`. Bản ghi có `links` tới receipt, attempt và kết nối, nhưng không có mã đơn, mã thanh toán hay thời điểm giao dịch.
  - Màn xem ngoại lệ nằm sau `marketplace_payouts` (cutover decision 8).
- **Người đặt** chưa được lưu. Màn chủ sân đọc hồ sơ hiện tại của tài khoản.
- **Pha 0 đã làm:** phạm vi tra mã của staff (`assertCanOperate`), QR vẽ tại chỗ, phân trang lịch sử đơn, nhãn trạng thái, ngày địa phương.
- **Các change song song.**
  - `direct-owner-cutover` chưa archive và đang giữ requirement "One-hour booking slots".
  - `align-module-02-venue-court` sửa "Final bookings and released availability" và "Courts carry their own sport and booking interval". Task 2.16 của change này bỏ `hold_ttl_minutes` và `require_continuity` khỏi form chính sách.
  - Module 3 (chưa tạo change) giữ phần `price_tables`, engine tính tiền theo phút, service voucher `hold / release / consume`, sự kiện công bố giá và việc viết lại `AvailabilityController`.

## Goals / Non-Goals

**Goals:**
- Một helper trước khi chèn, dùng cho cả hai đường tạo đơn. Hạn thanh toán, người đặt, số tiền đã đồng ý, lưới 30 phút và việc nhả hold quá hạn chỉ được viết một lần.
- Một bước nhả dùng chung cho cả bốn trường hợp: hết hạn, khách huỷ, nhả hold quá hạn, đổi giá.
- Hạn thanh toán là giá trị tính một lần lúc tạo đơn và lưu lại. Attempt và màn thanh toán chỉ đọc giá trị đó.
- Không thêm bảng mới. Người đặt lưu trong metadata. Dữ liệu chỉ đổi ở hai chỗ: backfill người đặt cho đơn cũ và đổi sân 45 phút thành 60 phút.
- Cắt hợp đồng API một lần (`slots[]` → `starts_at/ends_at`), không giữ đường tương thích.

**Non-Goals:**
- Engine giá, bảng giá, bộ đếm voucher, viết lại availability: thuộc module 3. Module 04 chỉ gọi tới.
- Form chính sách cơ sở: thuộc module 2 (task 2.16).
- Đặt định kỳ: vẫn dùng độ dài slot của sân và hạn giữ chỗ riêng của nó.
- Đặt hộ ở quầy, nhóm chia tiền, thời điểm khai tài khoản nhận tiền (D13).

## Decisions

### D1. Khách huỷ đơn đang chờ thanh toán
- Chọn: thêm `POST reservations/{code}/cancel-payment`, chỉ chủ đơn gọi được.
  - Trong một transaction: khoá các attempt của đơn rồi khoá reservation, cùng chiều với webhook (attempt → reservation) nên hai bên không khoá chéo nhau.
  - `held` → gọi `releaseHeld($reservation, 'cancelled', 'customer_payment_cancel')`.
  - Đơn đang `cancelled` hoặc `expired` → 200, không làm gì.
  - Đơn `confirmed` → 409.
  - Người khác gọi → 403, hoặc 404 khi policy không cho thấy đơn.
- Phương án khác:
  - Giữ lệnh cấm huỷ (archive `2026-09-12`, cutover). Loại: trái FR-76 và BR-84.
  - Cho huỷ cả đơn đã xác nhận. Loại: lệnh cấm ban đầu nhằm tránh nghĩa vụ hoàn tiền, mà điều đó vẫn đúng với đơn đã trả.
- Lý do: FR-76, BR-84, BR-85. Đơn chưa trả thì không có tiền để hoàn, nên huỷ không tạo nghĩa vụ nào. Ai commit trước thì thắng. Nếu tiền tới sau khi huỷ, webhook thấy đơn không còn `held` và ghi ngoại lệ như đang làm với đơn hết hạn.

### D2. Một bước nhả `releaseHeld`
- Chọn: tách phần nhả trong `ExpirePendingBookings` thành `releaseHeld(Reservation $locked, string $status, string $reason)`. Hàm làm các việc sau:
  - đổi trạng thái slot và entitlement;
  - nhả lượt voucher (service `release` của module 3, trước khi có thì dùng đường hiện tại);
  - hoàn điểm;
  - cho attempt đang chờ hết hạn;
  - ghi `metadata.release_reason`.
- Hàm không làm gì khi đơn không còn `held`. Bốn chỗ dùng: job hết hạn, D1, D7, D11.
- Phương án khác:
  - Dùng `ReservationService::release`. Loại: hàm này không hoàn điểm và không cho attempt hết hạn.
  - Mỗi trường hợp tự nhả. Loại: các bản sẽ lệch nhau theo thời gian.
- Lý do: BR-84 đòi giải phóng chỗ và voucher đúng một lần. Spec ghi rõ bốn trường hợp cho cùng một trạng thái sau khi nhả.

### D3. Hạn thanh toán
- Chọn: helper tính `hold_expires_at = min(now + 10 phút, starts_at, voucher_ends_at)` trước khi chèn.
  - Chỉ áp cho đơn có tiền phải trả lớn hơn 0. Đơn 0đ xác nhận ngay (BR-82).
  - Hạn ≤ now → 422 `PAYMENT_WINDOW_CLOSED`, không tạo gì.
  - `initiate` tạo attempt với `expires_at = hold_expires_at`. Nếu attempt cũ còn chờ thì trả lại chính nó: cùng mã, cùng số tiền, cùng người nhận.
  - 10 phút là hằng số trong code, không phải cài đặt của cơ sở.
  - Đặt sân thường bỏ hẳn việc đọc `booking.hold_ttl_minutes`.
- Phương án khác:
  - Giữ hạn theo chính sách cơ sở (design archive `2026-09-27`). Loại: BR-79 cố định 10 phút.
  - Giữ hạn của cơ sở nhưng chặn bởi giờ bắt đầu. Loại: vẫn trái BR-79 khi cơ sở đặt hơn 10 phút.
- Lý do: BR-79 và ví dụ 14:58 → 15:00. Khi hạn của đơn và hạn của attempt là cùng một giá trị, mở lại màn thanh toán không thể gia hạn và không cấp mã mới.

### D4. Lưu người đặt trong metadata
- Chọn: lưu `reservations.metadata.booker = {name, phone, email}` lúc tạo đơn.
  - SĐT kiểm bằng `User::PHONE_PATTERN`. Thiếu hoặc sai → 422 `PHONE_REQUIRED` trước khi khoá hay chèn gì.
  - Màn chủ sân, tra mã ở quầy và chi tiết đơn của khách đều đọc snapshot này.
  - Đơn cũ được backfill một lần từ hồ sơ lúc chạy migration. Code đọc chỉ có một nhánh, không fallback về hồ sơ hiện tại.
- Phương án khác:
  - Thêm ba cột. Loại: metadata đã chứa `coupon_usage` và `operational_status`; ba cột chỉ đọc không đáng một migration schema.
  - Fallback về hồ sơ khi thiếu snapshot. Loại: đó là shim, sửa hồ sơ sẽ lại đổi đơn cũ.
- Lý do: BR-74. Dữ liệu prod sau reseed là dữ liệu demo, nên backfill từ hồ sơ hiện tại không làm mất thông tin thật.

### D5. Số tiền khách đã đồng ý
- Chọn: request checkout và hold phải có `expected_total` (số nguyên ≥ 0).
  - Server tính lại trong transaction, sau khi khoá.
  - Tiền tính lại > `expected_total` → 409 `PRICE_CHANGED`, kèm `{rental, discount, total}` mới, không tạo gì.
  - Tiền tính lại ≤ `expected_total` → tạo đơn theo số tính lại.
- Phương án khác:
  - Từ chối mọi chênh lệch (bản nháp D6 của module 3). Loại: BR-68 của module 3 và BR-80 chỉ cấm số cao hơn; chặn cả giá giảm làm khách phải bấm thêm một lần mà không bảo vệ thêm gì.
  - Tin giá server, không so. Loại: trái BR-80 và BR-68.
- Lý do: report chỉ cấm tạo yêu cầu thanh toán cao hơn số khách đã thấy. Module 04 làm phần so sánh trong checkout. Module 3 cần sửa D6 cho khớp (task 1.2).

### D6. Liên tục luôn bắt buộc
- Chọn: `SlotSelectionPolicy` luôn kiểm tính liên tục và không đọc `require_continuity` nữa.
  - Theo D14, mỗi request chỉ có một khoảng, nên liên tục là đương nhiên. Việc kiểm vẫn giữ để chặn dữ liệu do đường khác ghi.
  - Module 2 bỏ trường khỏi form (task 2.16). Fixture E2E bỏ key này.
- Phương án khác: giữ tuỳ chọn (D33 archive). Loại: BR-73 không cho tuỳ chọn.
- Lý do: BR-73.

### D7. Hold quá hạn không chặn
- Chọn:
  - Truy vấn chồng lấn trong `SlotSelectionPolicy` và availability (module 3 viết lại) coi `held` có `hold_expires_at ≤ now` là trống.
  - Trước khi chèn, helper khoá các đơn quá hạn chồng lấn trên sân đó (theo thứ tự id) và gọi `releaseHeld(…, 'expired', 'payment_deadline')`. Nhờ vậy ràng buộc gist không chặn lần chèn mới.
  - Nếu webhook của đơn quá hạn chạy song song thì bên nào commit trước thắng. Webhook chạy sau sẽ ghi ngoại lệ, đúng như với đơn quá hạn thông thường.
- Phương án khác: chấp nhận độ trễ tới lần quét kế tiếp. Loại: BR-77 nói "giữ chỗ còn hạn"; nếu cron dừng thì sân bị chặn mãi (lỗi prod #10).
- Lý do: BR-77, FR-70, decision 12 của cutover (tính đúng, không chờ cron).

### D8. Chủ cơ sở tra cứu thanh toán bất thường
- Chọn: thêm `GET owner-payment-exceptions`.
  - Chỉ đọc, phân trang, chỉ `organization_owner`, scope theo tổ chức của người gọi, chỉ lấy loại `owner_payment_*`.
  - Gắn với capability `direct_owner_payments`, không đi sau `marketplace_payouts`.
  - `recordReceiptException` chép thêm vào exception: `purpose`, `purpose_id` (mã đơn), `payment_code` (như khi nhận), `event_at`. Các giá trị lấy từ receipt và attempt khớp mã.
  - Mã không khớp attempt nào thì `purpose_id = null`. Không có nhánh nào gán theo số tiền.
  - Màn ngoại lệ tài chính cũ (xử lý, đối soát) vẫn sau cờ payouts. Delta `financial-ledger-and-payouts` thêm một câu loại màn tra cứu mới ra khỏi nhóm màn bị ẩn.
- Phương án khác:
  - Bỏ cờ cho màn ngoại lệ cũ. Loại: màn đó có thao tác ghi và đối soát gắn với payouts (cutover design 8 cũng đã loại).
  - Giữ ẩn. Loại: trái FR-81 và UC-4.12.
  - Không viết delta `financial-ledger`, chỉ giải thích ở đây. Loại: kịch bản "Direct-only cohort opens the console" cấm mục "finance-exception" trên điều hướng, và màn mới dễ bị đọc là vi phạm câu đó.
- Lý do: FR-81, UC-4.12, BR-86, BR-88. Chép giá trị vào exception lúc ghi giữ dòng ngoại lệ bất biến, và màn tra cứu không phải join qua bốn bảng.

### D9. QR đơn vẽ tại chỗ (đã làm ở Pha 0)
- Dùng `qrcode` đã cài, payload `AURA_BOOKING_<code>`, một component dùng chung. QR thẻ ví cũng vẽ tại chỗ.
- Change này chỉ ghi vào spec, trong requirement "Customer booking list and detail".

### D10. Trạng thái hiển thị và chi tiết đơn
- Nhãn và điều kiện hiện QR đã làm ở Pha 0.
- Pha 3 thêm vào chi tiết đơn:
  - `hold_expires_at`;
  - kết quả thanh toán (attempt và receipt mới nhất);
  - người đặt (D4);
  - giờ check-in và check-out (eager-load `attendance`);
  - xử lý sự cố, đọc từ bản ghi đơn bị ảnh hưởng của module 2 (D8 của module 2).
- Lý do: FR-79, D50 archive (trạng thái vận hành nằm trong metadata).

### D11. Kiểm lại đơn chờ khi giá mới có hiệu lực
- Chọn: một listener nghe sự kiện "giá mới có hiệu lực" do module 3 phát (lúc công bố nếu hiệu lực ngay, hoặc lúc tới `effective_at`).
  - Listener tìm các đơn `held` trên những sân bị ảnh hưởng có khoảng nằm trong vùng hiệu lực, rồi tính lại tiền bằng engine của module 3.
  - Tiền khác → `releaseHeld(…, 'expired', 'price_changed')` và gửi thông báo qua `DurableNotificationService`.
  - Tiền bằng → không làm gì.
  - Đơn đã xác nhận không nằm trong truy vấn.
- Phương án khác:
  - Hook vào controller `PriceRule` hiện tại. Loại: module 3 sẽ thay mô hình giá, hook vào đó là phí công.
  - Định giá lại đơn tại chỗ và cấp mã mới. Loại: BR-87 đòi yêu cầu cũ hết hiệu lực và khách gửi lại.
- Lý do: FR-78, BR-87.

### D12. Ghi nhận dùng voucher lúc xác nhận
- Chọn: gọi `consume($reservation)` của service voucher module 3 trong cùng transaction xác nhận (webhook và đơn 0đ). Lượt được giữ lúc tạo đơn chờ và nhả qua D2.
- Phương án khác: module 04 tự sửa bộ đếm. Loại: bộ đếm thuộc module 3 và đã thống nhất ngày 2026-10-20.
- Lý do: BR-82, BR-83.

### D13. Không đổi trong change này
- BR-76 (giao lịch sân với giờ cơ sở, đình chỉ) và giá "từ" của FR-69: thuộc module 2 và module 3.
- Đặt hộ ở quầy: giữ theo `on-site-attendance`.
- Tài khoản nhận tiền khai sau khi tạo cơ sở: theo D2 của module 2.
- Kiểm lại khi các pha đó xong (task 6.4).

### D14. Lưới 30 phút: mỗi đơn mới là một slot phủ cả khoảng
- Người dùng chọn Q1 = A ngày 2026-10-21.
- Chọn:
  - Quote, hold và checkout nhận `court_id`, `starts_at`, `ends_at`.
  - Helper kiểm, dưới lock: hai mốc rơi vào :00 hoặc :30 theo giờ cơ sở; cùng ngày chơi (kết thúc được phép là 24:00); kết thúc > bắt đầu; độ dài ≥ 30 phút; bắt đầu chưa qua. Sai → 422 `INVALID_BOOKING_TIME`.
  - Đơn mới lưu đúng một `ReservationSlot` và một `ReservationEntitlement` phủ `[starts_at, ends_at)`. `unit_price` là tổng tiền thuê do engine theo phút của module 3 tính.
  - Bán lại chuyển entitlement duy nhất đó, tức là bán nguyên đơn.
  - `slot_duration` của sân chỉ còn dùng cho đặt định kỳ và chỉ nhận 30, 60, 90, 120.
- Phương án khác:
  - Mỗi 30 phút một slot. Loại: tính giá theo từng slot rồi làm tròn từng slot thì lệch với `T = ⌊Σ dᵢ·pᵢ / 60⌋` của BR-55; số dòng cũng gấp đôi.
  - Giữ slot 1 giờ (phương án B của Q1). Loại: người dùng đã chọn A.
- Lý do: BR-73, BR-75 và ví dụ 15:30–17:00 của report. Ràng buộc gist và chồng lấn nửa mở đã làm việc trên khoảng bất kỳ, nên không phải đổi schema.
- Hệ quả:
  - Không bán lại được một phần đơn.
  - Đơn cũ giữ các slot 1 giờ đã ghi và vẫn bán lại được từng slot như cũ.

### D15. Cắt hợp đồng API một lần
- Chọn: backend bỏ hẳn mảng `slots` ở quote, hold và checkout. Client gửi `slots` nhận 422. Frontend đổi form sang chọn giờ bắt đầu và kết thúc trong cùng lần merge.
- Phương án khác: nhận cả hai dạng trong một thời gian. Loại: AGENTS.md yêu cầu cutover sạch, và hai dạng thì phải định giá theo hai cách.
- Lý do: client duy nhất là frontend của dự án.

### Phối hợp và thứ tự archive
- Thứ tự archive: `direct-owner-cutover` → `align-module-02-venue-court` → change của module 3 → change này.
- Các khối MODIFIED trùng header:
  - "Final bookings and released availability", "Courts carry their own sport and booking interval": dựng trên text của module 2.
  - "Active inventory exclusivity", "Immutable price quotes": dựng trên text ở spec chính.
  - "Settlement and payout lifecycle": dựng trên text của cutover.
  - Trước khi archive, dựng lại các khối này từ text đang có ở spec chính, chỉ giữ phần sửa của change này (task 6.5).
- REMOVED "One-hour booking slots" chỉ archive được sau cutover, vì requirement đó do cutover thêm.
- Module 3 viết lại "Immutable price quotes" thì phải giữ câu về đơn chờ thanh toán (task 1.2).

## Risks / Trade-offs

- **[Cửa sổ deploy]** Push lên main thì frontend tự deploy, còn backend chờ người dùng chạy `glab ci run -b main`. Với D15, giữa hai lần deploy, form đặt sân mới gọi vào backend cũ (hoặc ngược lại) sẽ hỏng.
  → Hẹn giờ với người dùng trước khi push Pha 5, chọn giờ ít khách, chạy backend ngay sau push, rồi smoke test đặt 15:30–17:00.
- **[Phụ thuộc module 3]** D11, D12 và phần định giá của D14 cần engine, service voucher và sự kiện công bố giá của module 3.
  → Pha 2–4 không phụ thuộc. Pha 5 chỉ bắt đầu khi module 3 đã merge các phần đó. Tới lúc đó lưới 1 giờ vẫn chạy như cũ.
- **[Khoá khi nhả hold quá hạn]** D7 khoá thêm các đơn khác trong request giữ chỗ.
  → Chỉ khoá đơn `held` đã quá hạn chồng lấn trên đúng sân đó, theo thứ tự id. Webhook khoá attempt trước rồi mới tới reservation và không bao giờ khoá hai reservation, nên không có vòng khoá.
- **[Đặt định kỳ vẫn đọc `hold_ttl_minutes`]** `RecurringSeriesService.php:147` đọc key này, mặc định 30. Khi form không ghi key này nữa, giá trị cũ vẫn nằm trong settings và cơ sở mới dùng mặc định.
  → Report không có đặt định kỳ. Giữ nguyên và ghi lại để xem khi làm module định kỳ.
- **[Bán lại nguyên đơn]** Khách không bán được một nửa đơn 2 giờ.
  → Đây là hệ quả trực tiếp của một khoảng và một giá theo BR-55. Spec ghi rõ. Người dùng có thể đổi trong lúc review.
- **[Backfill người đặt từ hồ sơ hiện tại]** Đơn cũ nhận thông tin lúc migration, không phải lúc đặt.
  → Dữ liệu prod là dữ liệu demo sau reseed. Migration ghi `metadata.booker_backfilled = true` để phân biệt.

## Migration Plan

1. **Pha 2** (sau khi Pha 0 của module 2 merge):
   - Migration backfill `metadata.booker` cho các reservation chưa có.
   - Chưa đổi schema.
   - `expected_total` bắt buộc cũng là một lần cắt hợp đồng, nên frontend và backend vào cùng một merge. Deploy backend ngay sau push, theo giờ đã hẹn với người dùng (giống rủi ro "Cửa sổ deploy").
2. **Pha 3, Pha 4:** chỉ thêm endpoint và trường. Deploy theo thứ tự nào cũng được.
3. **Pha 5:**
   - Migration đổi `courts.slot_duration` 45 → 60. Prod sau reseed không có dòng nào; migration in ra số dòng đã đổi.
   - Validation bỏ 45.
   - Cắt hợp đồng theo D15.
4. **Rollback:**
   - Revert code là đủ: metadata thêm vào không ảnh hưởng code cũ.
   - Đổi 45 → 60 thì không đảo được. Bản ghi id các sân đã đổi nằm trong output của migration.

## Open Questions

- Tên và chữ ký phía module 3 chốt trong design của change module 3. Module 3 đưa tên dự kiến ngày 2026-10-21:
  - Sự kiện `CourtPricesTookEffect(courtIds, effectiveAt)`. Phát khi công bố giá có hiệu lực ngay, khi tới `effective_at`, và khi gán hay thay bảng giá của sân. D11 kiểm lại các đơn `held` trên các sân đó có giờ kết thúc sau `effectiveAt`.
  - `RentalPriceCalculator::quote(Court, startsAt, endsAt, ?voucherCode, ?customer)` trả `{rental, discount, total}` kèm snapshot phiên bản giá và voucher. D14 và D5 dùng hàm này.
  - Service voucher: `hold(reservation, code)` trả hạn voucher, `release(reservation)`, `consume(reservation)`. D2 và D12 dùng service này.
  - Snapshot của `quote` có hạn voucher (null khi không có voucher). Module 3 đã nhận vào hợp đồng ngày 2026-10-21. Lý do: D3 phải tính hạn thanh toán trước khi chèn đơn, còn `hold` chỉ chạy được sau khi có reservation. Giá trị `hold` trả về được dùng để kiểm lại trong cùng transaction.
  - Các tên này không đổi spec hay cách chia task. Task 5.x dùng đúng tên cuối cùng của module 3.
