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
  - Module 3 (`align-module-03-pricing-promotions`) giữ phần `price_tables`, engine tính tiền theo phút, `VoucherUsageService` (`hold / release / consume`), sự kiện giá có hiệu lực và việc viết lại `AvailabilityController`. Bản viết lại ở Pha 2 của họ vẫn dùng bước `slot_duration`; change này đổi bước sang 30 phút ở task 5.1 (D14).

## Goals / Non-Goals

**Goals:**
- Một helper trước khi chèn, dùng cho cả hai đường tạo đơn. Hạn thanh toán, người đặt, số tiền đã đồng ý, lưới 30 phút và việc nhả hold quá hạn chỉ được viết một lần.
- Một bước nhả dùng chung cho cả bốn trường hợp: hết hạn, khách huỷ, nhả hold quá hạn, đổi giá.
- Hạn thanh toán là giá trị tính một lần lúc tạo đơn và lưu lại. Attempt và màn thanh toán chỉ đọc giá trị đó.
- Không thêm bảng mới. Người đặt lưu trong metadata. Dữ liệu chỉ đổi ở hai chỗ: backfill người đặt cho đơn cũ và đổi sân 45 phút thành 60 phút.
- Cắt hợp đồng API một lần (`slots[]` → `starts_at/ends_at`), không giữ đường tương thích.

**Non-Goals:**
- Engine giá, bảng giá, bộ đếm voucher, viết lại availability: thuộc module 3. Module 04 chỉ gọi tới, và chỉ đổi bước lưới của availability sang 30 phút (D14).
- Form chính sách cơ sở: thuộc module 2 (task 2.16).
- Đặt định kỳ: vẫn dùng độ dài slot của sân và hạn giữ chỗ riêng của nó.
- Đặt hộ ở quầy, nhóm chia tiền, thời điểm khai tài khoản nhận tiền (D13). Nhóm chia tiền chỉ được nối `consume` và `release` voucher (D12).

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
  - hoàn điểm nếu đơn có giữ điểm. Checkout thường không còn trừ điểm (module 3, Q2 = A), nhưng đơn tạo trước đó vẫn phải được hoàn;
  - cho attempt đang chờ hết hạn;
  - ghi `metadata.release_reason`.
- Hàm không làm gì khi đơn không còn `held`. Các chỗ dùng: job hết hạn, D1, D7, D11, D12 (voucher hỏng lúc xác nhận) và D19 của module 3 (chương trình đổi khi có đơn chờ). Hai chỗ cuối ghi lý do `voucher_changed`.
- Phương án khác:
  - Dùng `ReservationService::release`. Loại: hàm này không hoàn điểm và không cho attempt hết hạn.
  - Mỗi trường hợp tự nhả. Loại: các bản sẽ lệch nhau theo thời gian.
- Lý do: BR-84 đòi giải phóng chỗ và voucher đúng một lần. Spec ghi rõ năm trường hợp (hết hạn, khách huỷ, hold quá hạn, đổi giá, voucher đổi) cho cùng một trạng thái sau khi nhả. Spec không nhắc tới điểm vì checkout thường không còn giữ điểm.

### D3. Hạn thanh toán
- Chọn: helper tính `hold_expires_at = min(now + 10 phút, starts_at, voucher_ends_at)` trước khi chèn.
  - Chỉ áp cho đơn có tiền phải trả lớn hơn 0. Đơn 0đ xác nhận ngay (BR-82).
  - Hạn ≤ now → 422 `PAYMENT_WINDOW_CLOSED`, không tạo gì.
  - `initiate` tạo attempt với `expires_at = hold_expires_at`. Nếu attempt cũ còn chờ thì trả lại chính nó: cùng mã, cùng số tiền, cùng người nhận.
  - 10 phút là hằng số trong code, không phải cài đặt của cơ sở.
  - Đặt sân thường bỏ hẳn việc đọc `booking.hold_ttl_minutes`.
  - Hạn voucher lấy từ snapshot của engine. Module 3 nối vào `ReservationIntake::paymentDeadline` ở task 5.4 của họ. Từ Pha 2 của họ tới lúc đó, đơn mới không có voucher.
  - Thứ tự kiểm khi tạo đơn (module 3 D20): voucher (422 `VOUCHER_INVALID`) → so `expected_total` (409 `PRICE_CHANGED`, D5) → hạn thanh toán (422 `PAYMENT_WINDOW_CLOSED`). `ReservationIntake` giữ thứ tự này khi module 3 thêm bước voucher. Voucher hết hạn đúng lúc gửi đã bị `VOUCHER_INVALID` chặn, nên không tới bước hạn thanh toán.
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
  - Bước so nằm sau bước kiểm voucher và trước bước tính hạn thanh toán (thứ tự ở D3).
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
  - xử lý sự cố, đọc từ bản ghi đơn bị ảnh hưởng của module 2 (D8 của module 2). Phần này chờ module 2, xem task 3.6.
- Voucher trong chi tiết đơn do module 3 thêm ở task 5.4–5.5 của họ: `Reservation::voucherUse`, trường `voucher` ở `ReservationController::show`, phần voucher ở `ReservationDetailFacts` và `quoteDiscounts`.
- Lý do: FR-79, D50 archive (trạng thái vận hành nằm trong metadata).

### D11. Kiểm lại đơn chờ khi giá mới có hiệu lực
- Chọn: một listener nghe `CourtPricesTookEffect(courtIds, effectiveAt)` của module 3.
  - Module 3 phát sự kiện sau commit, lúc công bố phiên bản (`effectiveAt = max(effective_at, now)`) và lúc áp, thay hay kết thúc liên kết sân – bảng giá. Không phát khi tới `effective_at` (module 3 D16): theo D6 của họ, giá một đơn xác nhận phải trả chỉ đổi ở hai thời điểm đó.
  - Listener tìm các đơn `held` trên `courtIds` có giờ kết thúc sau `effectiveAt`. Mỗi đơn chạy trong một transaction riêng:
    - khoá qua `HeldReservationRelease::lock` (attempt → reservation);
    - đơn không còn `held` thì bỏ qua;
    - tính lại bằng `RentalPriceCalculator::quote` với voucher đơn đang giữ và `$heldBy` là chính đơn đó. Thiếu `$heldBy` thì lượt cuối hoặc mã riêng mà đơn đang giữ bị coi là hết, và đơn bị nhả oan.
  - Tiền khác → `releaseHeld(…, 'expired', 'price_changed')`. Chỉ khi hàm này trả `true` mới gửi thông báo qua `DurableNotificationService`, để đơn vừa bị đường khác nhả không bị báo hai lần.
  - Tiền bằng → không làm gì.
  - Đơn đã xác nhận không nằm trong truy vấn.
  - Một transaction cho mỗi đơn, không gom cả lô: một đơn lỗi không rollback các đơn khác, và listener không giữ khoá của nhiều đơn cùng lúc (D12, mục khoá). Module 3 D19 (cập nhật hoặc dừng chương trình) làm theo cùng cách.
- Phương án khác:
  - Hook vào controller `PriceRule` hiện tại. Loại: module 3 sẽ thay mô hình giá, hook vào đó là phí công.
  - Định giá lại đơn tại chỗ và cấp mã mới. Loại: BR-87 đòi yêu cầu cũ hết hiệu lực và khách gửi lại.
- Lý do: FR-78, BR-87.

### D12. Ghi nhận và nhả lượt voucher
- Chọn: transaction nào xác nhận đơn thì gọi `consume($reservation, $confirmedAt)` của `VoucherUsageService` (module 3 D11, D29). Lượt được module 3 giữ bằng `hold` lúc tạo đơn chờ.
  - Change này gọi `consume` ở:
    - `OwnerPaymentAttemptService::processReceipt` (đơn trả một lần), với `$receipt->event_at`;
    - `SplitPaymentService::confirmPaidAttempt` (gọi từ `processReceipt`, `payShare`, `coverShortfall`), với `$paidAt`;
    - `ReservationService::confirm` (ví legacy), với `now()`.
  - Đường xác nhận của `RecurringSeriesService` không gọi, vì đơn định kỳ không giữ voucher.
  - Đơn xác nhận ngay trong transaction tạo nó (checkout 0đ, checkout ví legacy, `ReservationService::hold` 0đ trực tiếp) do module 3 gọi `consume` ngay sau `hold` (task 5.4 của họ).
  - Gọi `release` ở `releaseHeld` và `ReservationService::release` (gọi từ `confirm` khi hold quá hạn, `SplitPaymentService::expire`, `RecurringReleaseService`).
  - `consume` và `release` không làm gì khi đơn không giữ voucher, nên nối được trước khi module 3 bật `hold`.
- `consume` ném `VoucherNoLongerApplies` khi voucher không còn áp được hoặc tiền giảm đổi. Mọi đường đều nhả đơn bằng `releaseHeld(…, 'expired', 'voucher_changed')` và báo khách. Lỗi không được lan ra khỏi transaction ngoài sau khi đã nhả.
  - `processReceipt`: gọi sau khi `applyAttemptChecks` ra ALLOCATED và đơn đã khoá qua kiểm `held`, trước khi lưu receipt và tạo `OwnerPaymentAllocation`. Hỏng → receipt thành EXCEPTION mã `voucher_changed`, nhả đơn, `recordReceiptException` ghi như các ngoại lệ khác. Không ném lại, để job không chạy lại.
  - `confirmPaidAttempt`: khi nhóm đủ tiền, khoá đơn trước bằng `HeldReservationRelease::lock` (hiện đơn chỉ bị khoá sau khi nhóm thành `confirmed`), gọi `consume`, rồi mới đánh dấu nhóm `confirmed`. Hỏng → làm như `expire`: nhóm và phần chia còn chờ sang `expired`, mọi phần đã trả (kể cả phần vừa nhận) được ghi qua `SplitExpiredShareExceptions::record` đúng một lần, và các attempt `split_share` còn chờ của nhóm hết hạn sau commit (mục khoá bên dưới). Khác `expire` ở chỗ đơn được nhả bằng `releaseHeld` trên bản đã khoá. Không ném lại.
  - `ReservationService::confirm`: gọi trước khi trừ ví. Hỏng → nhả đơn, không trừ ví, không ghi ngoại lệ thanh toán, rồi ném sau khi transaction của service xong. `ReservationController::confirm` bắt lỗi trong closure của `IdempotencyService::run` và trả 422 `VOUCHER_INVALID` kèm lý do, để việc nhả được commit.
  - Nhánh hold quá hạn hiện có của `confirm` gọi `release` rồi `abort(409)` nên bị rollback; lượt của đơn đó do `releaseHeld` nhả sau.
- Khoá:
  - Thứ tự chung: attempt → reservation → chương trình. `consume` khoá dòng chương trình `FOR SHARE`. `release` không khoá chương trình hay mã, nên các transaction nhả nhiều đơn liền nhau (`releaseOverdueHolds`, `RecurringReleaseService`) không tạo vòng khoá. Listener giá (D11) không thuộc nhóm này vì mỗi đơn một transaction.
  - Chia tiền: `processReceipt` của một phần khoá attempt của phần đó (`OwnerPaymentAttemptService.php:270-274`) rồi mới chờ khoá nhóm (`SplitPaymentService.php:315`). Vì vậy không transaction nào đang giữ nhóm được UPDATE attempt `split_share`, nếu không sẽ thành vòng nhóm → attempt ngược với attempt → nhóm. Nhánh hỏng của `confirmPaidAttempt` và `SplitPaymentService::expire` cùng đưa việc hết hạn attempt `split_share` còn chờ vào `DB::afterCommit`, chạy trong một transaction riêng không khoá gì khác. Nếu callback bị mất, attempt còn chờ vẫn không xác nhận được: nhóm đã `expired` nên `hasConfirmableShare` trả `false`, và khoản nhận thành ngoại lệ `split_share_unavailable`.
  - `processReceipt` khoá dòng cơ sở `FOR KEY SHARE` trước `consume`, vì insert allocation sau đó khoá ngầm cơ sở. Chọn khoá ngay trước khi khoá attempt (đọc `venue_id` của attempt trước, cột này không đổi), không phải ngay sau khi khoá đơn như module 3 đề xuất. Lý do: checkout khoá cơ sở `FOR UPDATE` rồi mới khoá attempt của hold quá hạn (D7). Khoá cơ sở sau attempt vẫn để lại vòng khoá hiện có (xem Risks), còn khoá trước attempt thì cùng chiều với checkout và `initiate`.
  - `ReservationService::confirm` không khoá cơ sở.
- Phương án khác:
  - Module 04 tự sửa bộ đếm. Loại: bộ đếm thuộc module 3 và đã thống nhất ngày 2026-10-20.
  - Change này gọi `consume` cho đơn 0đ (bản trước). Loại: đơn đó xác nhận trong transaction checkout mà module 3 viết lại; hai module cùng sửa một transaction dễ gọi thiếu hoặc gọi hai lần.
  - Để `VoucherNoLongerApplies` lan ra. Loại: transaction ngoài rollback luôn việc nhả, đơn giữ lượt tới lần quét kế tiếp.
  - Khoá cơ sở ngay sau khi khoá đơn (đề xuất của module 3). Loại: vẫn còn vòng attempt → cơ sở ngược với checkout.
  - Chỉ đưa nhánh hỏng của `confirmPaidAttempt` ra sau commit, để `expire` như cũ. Loại: `expire` hiện UPDATE attempt trong lúc giữ nhóm (`SplitPaymentService.php:407-410`), nên cũng có vòng đó, dù cửa sổ hẹp hơn (`processReceipt` phải qua `hasConfirmableShare` ngay trước `expires_at`). Hai đường cùng một bước thì nên cùng một cách.
- Lý do: BR-83, FR-74; BR-71 của module 3 (không xác nhận bằng voucher của chương trình đã dừng). FR-75 và BR-82 (đơn 0đ) chuyển sang task 5.4 của module 3.

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
  - Availability: task 5.1 đổi bước lưới của `AvailabilityController` (bản Pha 2 của module 3, bước `slot_duration`) sang 30 phút cho mọi sân, cùng panel trang chủ. Module 3 giữ khối MODIFIED "Home availability identifies its selected court and date" với mốc 30 phút; change này không thêm khối trùng.
- Phương án khác:
  - Mỗi 30 phút một slot. Loại: tính giá theo từng slot rồi làm tròn từng slot thì lệch với `T = ⌊Σ dᵢ·pᵢ / 60⌋` của BR-55; số dòng cũng gấp đôi.
  - Giữ slot 1 giờ (phương án B của Q1). Loại: người dùng đã chọn A.
- Lý do: BR-73, BR-75 và ví dụ 15:30–17:00 của report. Ràng buộc gist và chồng lấn nửa mở đã làm việc trên khoảng bất kỳ, nên không phải đổi schema.
- Hệ quả:
  - Không bán lại được một phần đơn.
  - Đơn cũ giữ các slot 1 giờ đã ghi và vẫn bán lại được từng slot như cũ.

### D15. Cắt hợp đồng API một lần
- Chọn: backend bỏ hẳn mảng `slots` ở quote, hold và checkout. Client gửi `slots` nhận 422. Frontend đổi form sang chọn giờ bắt đầu và kết thúc trong cùng lần merge.
  - `PriceQuote` được giữ và đổi sang `starts_at/ends_at`: token, hạn 15 phút và các kiểm tra trước khi giữ chỗ vẫn như cũ, `slot_snapshot` chỉ còn một khoảng. Giá vẫn tính lại lúc gửi bằng `reprice`.
  - Form đặt sân không gửi `coupon_code` hay `loyalty_points`: từ Pha 2 của module 3, hai trường không rỗng nhận 422. Form chừa chỗ cho phần chọn voucher; task 5.5 của module 3 thêm phần chọn và gửi `voucher_program_id` hoặc `voucher_code`.
- Phương án khác:
  - Nhận cả hai dạng trong một thời gian. Loại: AGENTS.md yêu cầu cutover sạch, và hai dạng thì phải định giá theo hai cách.
  - Bỏ `PriceQuote`. Loại: checkout đang dùng `quote_token`, và "Court quotes from price tables" của module 3 vẫn đòi báo giá hết hạn phải được khách đồng ý lại.
- Lý do: client duy nhất là frontend của dự án.

### Phối hợp và thứ tự archive
- Thứ tự archive: `direct-owner-cutover` → `align-module-02-venue-court` → `align-module-03-pricing-promotions` → change này. Change này archive ngay sau module 3, vì "Court quotes from price tables" của module 3 nhắc tới "Pending bookings at a price change" của change này.
- Các khối MODIFIED trùng header:
  - "Final bookings and released availability", "Courts carry their own sport and booking interval": dựng trên text của module 2.
  - "Active inventory exclusivity": dựng trên text ở spec chính.
  - "Settlement and payout lifecycle": dựng trên text của cutover.
  - Trước khi archive, dựng lại các khối này từ text đang có ở spec chính, chỉ giữ phần sửa của change này (task 6.5).
- REMOVED "One-hour booking slots" chỉ archive được sau cutover, vì requirement đó do cutover thêm.
- Không có khối MODIFIED "Immutable price quotes": module 3 REMOVED requirement đó. Câu "a booking still pending payment SHALL follow 'Pending bookings at a price change'" và scenario "Price rules change after quote acceptance" nằm nguyên văn trong "Court quotes from price tables" của module 3.
- File của change này mà module 3 sửa (module 3 D29):
  - Pha 2 của họ: `PriceQuoteService::create` và `reprice` tính bằng engine; bỏ phần đọc `Coupon`/`PromotionCampaign` trong `ReservationIntake::voucherEndsAt`; sửa các test đang gửi `coupon_code` (`PaymentDeadlineTest`, `HeldReservationReleaseTest`, `OverdueHoldTest`, `AgreedAmountTest`, `CustomerPaymentCancelTest`). Ca voucher của `PaymentDeadlineTest` và ca giá tăng có coupon của `AgreedAmountTest` được `OrdinaryCheckoutVoucherTest` của họ phủ lại ở task 5.4.
  - Pha 4 của họ: bỏ nhánh nhả coupon trong `releaseHeld` và `ReservationService::release`.
  - Task 5.4 của họ: nối hạn voucher của snapshot vào `ReservationIntake::paymentDeadline`; khoá dòng chương trình sau `releaseOverdueHolds` và trước `reprice` ở hai đường tạo đơn; chặn `hold_expires_at` của đơn legacy trong `ReservationService::hold` (đang giữ hạn báo giá) bằng hạn voucher.
  - Task 5.4–5.5 của họ: phần voucher trong chi tiết đơn (D10).
- Cổng với module 3 (module 3 D29): Pha 5 của change này chờ Pha 3 của module 3 (sự kiện giá, sau Pha 2 có engine và `AvailabilityController` mới) và task 5.1–5.3 của họ (phần voucher của engine, `VoucherUsageService`, danh sách voucher), không chờ cả Pha 5 của họ. Task 5.4–5.5 của module 3 merge sau task 5.1, 5.5 và 5.7 của change này.

### Ghi chú khi apply Pha 2
- **Tính lại giá lúc gửi.**
  - Chọn: chỉ tính lại khi request có `expected_total`. Request HTTP luôn phải có. `PriceQuoteService::reprice` ghi lại tiền và voucher vào báo giá trong transaction, sau khi khoá. Checkout so với số phải trả cuối cùng, tức số khách thấy.
  - Phương án khác: luôn tính lại, kể cả đặt định kỳ. Loại: đặt định kỳ tạo báo giá ngay trong cùng transaction nên không có số nào để so.
- **SĐT và người đặt áp cho mọi đường tạo đơn.**
  - Gồm cả đặt hộ ở quầy (kiểm SĐT của khách được đặt hộ) và đặt định kỳ.
  - Phương án khác: chỉ kiểm khi khách tự đặt. Loại: BR-74 cần người đặt trên mọi đơn. Màn chủ sân chỉ đọc một nguồn là `metadata.booker`.
- **Hạn thanh toán.**
  - Đặt định kỳ vẫn dùng `hold_expires_at_override` (Non-Goal).
  - Đơn ví cũ (`legacy_wallet_v1`) giữ hạn của báo giá.
  - `initiate` cho attempt hết hạn đúng lúc hết hạn đơn, nên mở lại màn thanh toán không gia hạn.
- **Nhả hold quá hạn (D7).**
  - Thứ tự khoá: sân → cơ sở → attempt của đơn quá hạn → reservation. Webhook khoá attempt → reservation. `initiate` khoá cơ sở → kết nối → attempt → reservation. Từ task 5.4 của module 3, checkout khoá thêm chương trình sau cùng (sân → cơ sở → attempt → reservation → chương trình).
  - Ghi chú này từng kết luận "không có vòng khoá". Kết luận đó sai: webhook insert `OwnerPaymentAllocation` sau khi khoá attempt và đơn, và insert này khoá ngầm cơ sở. Xem Risks "Vòng khoá webhook và nhả hold quá hạn".
  - Sau commit, khách của đơn bị nhả nhận thông báo hết hạn. Không báo waitlist, vì chỗ đó vừa được đặt ngay.
- **Truy vấn chồng lấn.**
  - Scope `Reservation::blocksInventory()` dùng chung cho `SlotSelectionPolicy` và kiểm chồng lấn của báo giá.
  - `AvailabilityController` để module 3 viết lại (đã báo module 3). Bước lưới đổi sang 30 phút ở task 5.1 (D14).
- **Người đặt trên JSON.** Accessor `booker` đọc `metadata.booker` và được append vào JSON của reservation. Danh sách đơn của chủ sân và tra mã ở quầy dùng nó mà không cần thêm query.
- **Điểm (module 3, Q2 = A).**
  - Pha 2 không gỡ phần điểm khỏi checkout; module 3 làm việc đó.
  - `releaseHeld` vẫn hoàn điểm cho đơn có giữ điểm.
- **Bước nhả nằm ở `app/Services/Reservation/HeldReservationRelease`.**
  - Luật kiến trúc không cho model query trong `Domain/*/Actions`. Đây là bước dùng chung, không phải một use case.
  - Phương án khác: để trong Actions và nâng allowlist. Loại vì allowlist chỉ được hạ.
- **Múi giờ của hạn thanh toán.** Cột `hold_expires_at` dùng cast `datetime`, lưu nguyên giờ đồng hồ, còn giờ bắt đầu đọc từ `slot_snapshot` là UTC. Vì vậy hạn được đổi về múi giờ của app trước khi lưu. `PaymentDeadlineTest` bắt được lỗi lệch 7 giờ này.
- **Màn tạo đơn ở frontend.**
  - `BookingView.vue` chỉ hiện lịch sử, không tạo đơn.
  - Khối người đặt và số tiền (`BookingAgreement`) được đặt ở cả ba chỗ tạo đơn: `QuoteCalculatorCard`, `ScheduleBookingSummary` và dock ở trang chủ.
  - Dock ở trang chủ được tách thành `HomeBookingDock` vì `HomeView` đã chạm giới hạn số dòng.
  - Khi khách xác nhận giá mới sau `PRICE_CHANGED`, form gửi lại với Idempotency-Key mới, vì body đã khác.
- **Tra mã ở quầy.**
  - Frontend chưa có màn nào gọi `GET /attendance/{code}`. API đã trả `reservation.booker`; `BookerSnapshotTest` kiểm tra điều này.
  - POS đọc tên và SĐT từ `booker`. Đơn không có `booker` hiện "Khách vãng lai".

### Ghi chú khi apply Pha 3
- **Endpoint huỷ là một controller riêng.**
  - `CancelReservationPaymentController` là controller invokable. `ReservationController` đã chạm trần số public method trong allowlist, và luật kiến trúc không cho model query trong controller.
  - Phần tìm đơn mà người dùng được xem (trước là `findReservation` và `scopeVisibleReservations`) tách sang `App\Services\Reservation\VisibleReservations` để hai controller dùng chung. Trần `model-query` của `ReservationController` hạ từ 7 xuống 6.
  - Phương án khác: thêm method vào `ReservationController` và nâng allowlist. Loại vì allowlist chỉ được hạ.
- **Mã trả về.**
  - Không thấy đơn (khách khác) → 404. Thấy đơn nhưng không phải chủ đơn (chủ cơ sở, nhân viên) → 403.
  - Đơn `cancelled` hoặc `expired` → 200, trả đơn như hiện tại.
  - Đơn ở trạng thái khác `held` → 409 với `code = RESERVATION_NOT_PENDING`.
  - Endpoint cần `Idempotency-Key` như các thao tác ghi khác.
- **Khoá.** Dùng `HeldReservationRelease::lock()` (attempt → reservation, cùng chiều webhook), đọc lại trạng thái sau khi khoá.
- **Waitlist.**
  - Sau commit, báo waitlist cho từng slot vừa nhả, giống job hết hạn, vì giờ đó trống thật.
  - Khách vừa huỷ không nhận thông báo, vì chính họ vừa bấm huỷ.
- **Tiền tới sau khi huỷ (3.2).**
  - Trước đây, receipt cho attempt đã `expired` mà chưa qua `expires_at` rơi vào `attempt_not_pending`. Nay `applyAttemptChecks` coi attempt `expired` như đã quá hạn, nên receipt thành `late` / `transfer_after_expiry`, kèm FinanceException `owner_payment_transfer_after_expiry`.
  - Thay đổi áp cho mọi đường làm attempt hết hạn sớm: khách huỷ, nhả hold quá hạn, đổi giá, POS nhả QR. Hai test cũ đổi kỳ vọng theo: `OwnerPaymentAttemptServiceTest` và `PosVerifiedPendingQrCollectionTest`.
  - Attempt `paid` nhận thêm tiền vẫn là `attempt_not_pending`.
  - Phương án khác: chỉ đổi khi `release_reason = customer_payment_cancel`. Loại: bước kiểm attempt sẽ phải đọc reservation, mà spec direct-owner-payments coi mọi trường hợp tiền tới sau khi yêu cầu hết hạn là cùng một loại ngoại lệ.
- **Chi tiết đơn (3.3).**
  - `payment` gồm attempt mới nhất và receipt mới nhất của attempt đó.
    - Attempt: `status`, `amount`, `currency`, `expires_at`, `paid_at`. Attempt `pending` đã qua `expires_at` trả `status = expired`.
    - Receipt: `status`, `amount`, `currency`, `transferred_at`.
    - Đơn không có attempt thì `payment = null`.
  - `attendance` chỉ gồm `status`, `checked_in_at`, `checked_out_at`. Ghi chú của nhân viên không ra API của khách.
  - `hold_expires_at` và `booker` đã có sẵn trong JSON.
  - **Xử lý sự cố chưa làm được.** Bảng `affected_reservation_cases` của module 2 chưa có trên main (task 4.2, D7 trên nhánh `docs/align-module-02-venue-court`). Phần này tách thành task 3.6 và chờ module 2.
- **Frontend.**
  - `CancelPaymentButton` dùng chung ở banner đơn chờ, modal thanh toán và modal chi tiết. Nút có bước xác nhận. Lỗi 409 hiện câu `reservations.messages.notPending`.
  - Nhãn:
    - Đơn `cancelled` có `release_reason = customer_payment_cancel` → "Đã hủy thanh toán".
    - Đơn huỷ vì lý do khác → "Đã hủy". Trước đây mọi đơn huỷ đều hiện "Đã hủy thanh toán".
  - Modal chi tiết gọi `GET /reservations/{code}` khi mở, vì danh sách không trả `payment` và `attendance`.
  - Khung "quote snapshot" (mã báo giá, giờ hết hạn báo giá) được thay bằng `ReservationDetailFacts`. Giờ hết hạn báo giá dễ bị đọc nhầm thành hạn thanh toán.
  - `ReservationView.vue` đang ở đúng trần 382 dòng. Bỏ 5 comment HTML chia mục để thêm phần nối sự kiện mà không phải nâng trần.

## Risks / Trade-offs

- **[Cửa sổ deploy]** Push lên main thì frontend tự deploy, còn backend chờ người dùng chạy `glab ci run -b main`. Với D15, giữa hai lần deploy, form đặt sân mới gọi vào backend cũ (hoặc ngược lại) sẽ hỏng.
  → Hẹn giờ với người dùng trước khi push Pha 5, chọn giờ ít khách, chạy backend ngay sau push, rồi smoke test đặt 15:30–17:00.
- **[Phụ thuộc module 3]** D11, D12 và phần định giá của D14 cần engine, `VoucherUsageService` và sự kiện giá của module 3.
  → Pha 2–4 không phụ thuộc. Pha 5 chờ Pha 3 và task 5.1–5.3 của module 3 (cổng ở mục "Phối hợp và thứ tự archive"). Tới lúc đó lưới vẫn theo `slot_duration` như cũ.
- **[Vòng khoá webhook và nhả hold quá hạn]** Có trên main từ Pha 2. Checkout khoá cơ sở `FOR UPDATE` rồi khoá attempt của hold quá hạn (D7). Webhook khoá attempt và đơn rồi insert allocation, khoá ngầm cơ sở `FOR KEY SHARE`. Hai bên chờ nhau khi một khoản chuyển tới trước hạn nhưng được xử lý sau hạn, đúng lúc một khách khác đặt khoảng chồng lấn trên cùng sân. PostgreSQL huỷ một bên: job chạy lại, còn checkout trả lỗi.
  → Hiếm và tự hồi phục. Task 5.5 khoá cơ sở `FOR KEY SHARE` trước khi khoá attempt trong `processReceipt` (D12), nên vòng này đóng cùng lúc với việc nối `consume`.
- **[Vòng khoá hết hạn chia tiền]** Có trên main. `SplitPaymentService::expire` khoá nhóm rồi UPDATE attempt `split_share` còn chờ (`SplitPaymentService.php:407-410`). `processReceipt` của một phần khoá attempt rồi chờ khoá nhóm (`SplitPaymentService.php:315`). Hai bên chỉ chờ nhau khi khoản nhận qua `hasConfirmableShare` ngay trước `expires_at` của nhóm, còn `expireDue` chạy ngay sau mốc đó.
  → Hiếm và tự hồi phục: PostgreSQL huỷ một bên, job hoặc lần quét sau chạy lại. Task 5.5 đưa việc hết hạn attempt ra sau commit cho cả `expire` lẫn nhánh hỏng mới của `confirmPaidAttempt` (D12). Nhánh hỏng mới gặp vòng này thường hơn nhiều, vì hai phần trả gần nhau là chuyện bình thường.
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

- Tên và chữ ký phía module 3 đã chốt trong design của `align-module-03-pricing-promotions` (D11, D16, D17). Task 5.x dùng đúng các tên này:
  - Sự kiện `CourtPricesTookEffect(courtIds, effectiveAt)`: phát sau commit lúc công bố phiên bản và lúc áp, thay hay kết thúc liên kết; không phát khi tới `effective_at`. D11 kiểm lại các đơn `held` trên các sân đó có giờ kết thúc sau `effectiveAt`.
  - `RentalPriceCalculator::quote(Court $court, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?VoucherChoice $voucher, ?Reservation $heldBy = null)` trả `{rental, discount, total}` kèm snapshot các phần giá và voucher (chương trình, điều khoản, `ends_at`) hoặc `null`. Không có tham số khách hàng. D5, D11 và D14 dùng hàm này; D11 truyền `$heldBy`.
  - `VoucherUsageService::hold(Reservation, VoucherChoice)` không trả gì; `release(Reservation)`; `consume(Reservation, CarbonInterface $confirmedAt)` ném `VoucherNoLongerApplies`. D2 và D12 dùng `release` và `consume`; `hold` do module 3 gọi.
  - Hạn voucher lấy từ snapshot của `quote`, không từ `hold`: checkout khoá chương trình trước khi tính giá, nên hạn trong snapshot đúng tới lúc commit (module 3 D17). D3 tính hạn thanh toán trước khi chèn đơn từ giá trị này.
  - `VoucherChoice` đến từ request: `voucher_program_id` (chương trình công khai) hoặc `voucher_code` (mã riêng), không gửi cả hai.
- Không còn câu hỏi mở với module 3. Nếu module 3 đổi các tên trên thì sửa lại mục này và task 5.x.
