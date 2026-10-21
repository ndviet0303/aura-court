## Context

Lý do và phạm vi xem `proposal.md`. Phần này chỉ ghi hiện trạng code sau Pha 0 (merge `4c8ad0a8`) và các ràng buộc quyết định cách làm.

- **Giá.**
  - `PriceRule` có phạm vi tổ chức, cơ sở, cơ sở + môn hoặc sân, và tầng `date_exception/holiday > peak > weekly > base`. `PriceQuoteService` gọi `PriceTierResolver` để chọn giá cho từng slot.
  - Rule `base` không có giờ được coi là phủ `[0, 1440]` (`PriceTierResolver.php:113-115`), nên giờ chưa cấu hình vẫn có giá.
  - Không khớp tầng nào thì trả 422 "Khung giờ … chưa mở bán." (`PriceTierResolver.php:88`).
  - `CourtPriceSlot` giữ giá theo từng giờ của sân (`ManageCourtPrices`, `/admin/courts/{id}/prices*`). Pha 0 cho nó thắng rule weekly rộng hơn, và chỉ xét dòng có cùng độ dài slot.
- **Tính tiền và availability.** Mỗi slot 1 giờ một giá. `AvailabilityController` sinh slot theo `slot_duration` của sân.
- **Khuyến mãi.**
  - `PromotionCampaign` và `Coupon` thuộc tổ chức, có nhóm khách và luật kết hợp, quản lý qua `PromotionController`.
  - Pha 0 thêm cast `AppTimezoneDateTime` và khoá mã cùng hình thức giảm sau khi phát hành.
- **Checkout đặt sân.**
  - `OrdinaryCheckoutService` (`Services/Booking`) cộng coupon, giảm hội viên, điểm và ví legacy.
  - `loyalty/preview` (`api.php:409`) báo giá điểm. `loyalty/redeem` (`api.php:410`) đã trả 422 `DIRECT_REDEMPTION_RETIRED`.
- **POS.**
  - `PosService.php:227-233` nhận `coupon_code` (request ở `PosController.php:225, 259`).
  - `PosService::awardPoints` (dòng 1044) được gọi khi hoàn tất đơn (dòng 631) và từ `PosQrPaymentService.php:152`.
  - `PosService::redeemPoints` (dòng 1066) và `PosController::redeemPoints` (dòng 673) không có route; chỉ test gọi tới.
- **Đặt định kỳ.** `AdvancedBookingController.php:67` nhận `coupon_code`, nhưng series tạo ra không dùng mã đó.
- **Bán lại.** Giá tối đa là `entitlement.amount`, tức giá gốc trước giảm (`MarketplaceService.php:39`, `PeerToPeerResaleService.php:256`).
- **Buổi chơi do chủ sân tổ chức.** `MatchService::getDefaultRates` (dòng 1056) đọc `CourtPriceSlot`. Tiền sân làm tròn nửa lên (`MatchService.php:972`).
- **Các change song song.**
  - `direct-owner-cutover` chưa archive, đang giữ "Peer-to-peer marketplace transfer" và một bản "Role-based owner operations".
  - `align-module-02-venue-court` (commit `92021735`, chưa ở main) thêm "Court price table link" (một liên kết, cùng cơ sở, chỉ owner, để quản lý giá định nghĩa hiệu lực) và "Court opening conditions" (độ phủ giá là điều kiện mở và resume sân). Change đó cũng sửa "Role-based owner operations". Module 2 tạo `OpenCourt`/`ResumeCourt` ở Pha 2 của họ và tự nối `PriceCoverageService` vào mở sân, resume sân và điều kiện đặt sân (task 3.3–3.5 của họ).
  - `align-module-04-booking` (đã ở main) cần engine theo phút, sự kiện giá có hiệu lực, và `consume` / `release` của service voucher. Module 4 không gọi `hold` (task 5.5 của họ chỉ nối `consume` và `release`). Change đó MODIFIED "Immutable price quotes" và dùng `releaseHeld(…)` để nhả đơn chờ. Pha 2 của module 4 đã merge ở `851c173e`: có `Reservation::scopeBlocksInventory()`, helper `ReservationIntake` dùng chung cho checkout và giữ chỗ, và `HeldReservationRelease::releaseHeld`. Pha 3 của module 4 (huỷ thanh toán, chi tiết đơn) đã merge ở `7bf9874c` và thêm `CustomerPaymentCancelTest`, test này đang gửi `coupon_code`. Đầu vào đặt sân vẫn là `slots` (giờ bắt đầu, mỗi slot dài `slot_duration`) kèm token báo giá; module 4 đổi sang `starts_at/ends_at` ở task 5.1 của họ (D29).
- **Nơi còn đọc mô hình cũ** (rà lại sau review):
  - `PriceQuoteService::create` tính luôn coupon và giảm hội viên cho checkout, giữ chỗ và đặt định kỳ. `PriceQuoteService::reprice` (thêm ở Pha 2 của module 4) tính lại giá lúc gửi trong `OrdinaryCheckoutService` và `ReservationService::hold`. `PriceQuoteService::create` còn lưu `PriceQuote` (token, hạn 15 phút, `slot_snapshot`) và kiểm điều kiện đặt, lịch, khoá sân và chồng lấn trước khi giữ chỗ. `LoyaltyPolicyService::preview` (route `loyalty/preview`) cũng gọi nó; phần còn lại của `LoyaltyPolicyService` chỉ `OrdinaryCheckoutService` dùng để tính điểm.
  - `CourtPriceRuleService` (`isPriced` trong `VerifyEffectiveBookingEligibility`, `pricedCourtIds` trong `CourtController`), `CourtController` nạp `priceSlots`, `EloquentVenueRepository:31`, công suất trong `AdminController:367`, `EvaluateVenueReadiness`, `SubmitVenueForPublication` đều đọc `PriceRule` hoặc `CourtPriceSlot`.
  - Coupon còn ở `HeldReservationRelease::releaseHeld` (dòng 43), `ReservationService::release` (dòng 233-236; gọi từ `ReservationService::confirm`, `SplitPaymentService::expire`, `RecurringReleaseService`), `ReservationIntake::voucherEndsAt` (dòng 136-142, đọc `Coupon` và `PromotionCampaign` để chặn hạn thanh toán), `ReservationService::hold`, `OrdinaryCheckoutService`, `PosService` (dòng 287, 342-344, 463-468, 536, 659-662, 1152) và `PosQrPaymentService:135-137`.
  - 56 file test tạo `PriceRule`, `CourtPriceSlot` hoặc `Coupon` trực tiếp.

## Goals / Non-Goals

**Goals:**
- Một engine giá duy nhất cho báo giá, availability, giữ chỗ, checkout, kiểm lại đơn chờ, đặt định kỳ và giá gợi ý của buổi chơi.
- Một service độ phủ duy nhất, dùng cho công bố giá, đổi liên kết và mở sân.
- Một service voucher duy nhất giữ bộ đếm. Checkout của change này gọi `hold`, và gọi `consume` khi chính checkout xác nhận đơn; module 4 gọi `consume` ở các lần xác nhận sau và `release` ở mọi đường nhả (D11, D29).
- Cắt mô hình cũ một lần: không giữ `PriceRule`, `CourtPriceSlot`, campaign hay coupon song song với mô hình mới.

**Non-Goals:**
- Lịch phục vụ, ngày nghỉ, điều kiện mở sân và trạng thái sân: module 2.
- Form đặt sân, hạn thanh toán, xác nhận, huỷ đơn chờ, kiểm lại đơn chờ khi giá đổi: module 4. Module 3 phát sự kiện và cung cấp engine.
- Đổi điểm ở POS: chưa có luồng đang chạy (D21).

## Decisions

### D1. Bảng giá thuộc cơ sở và có phiên bản
- Chọn:
  - `price_tables` (`venue_id`, `name`, `kind` = `normal / holiday`, `status` = `active / retired`).
  - Nội dung giá ở `price_table_versions` (`published_at`, `effective_at`) và `price_table_intervals` (`weekdays` hoặc `dates`, `start_minute`, `end_minute` ≤ 1440, `price_per_hour` > 0).
  - Lưu nội dung là công bố một phiên bản; không có nháp. Phiên bản đã công bố không sửa được.
  - Không xoá bảng giá.
- Phương án khác:
  - Giữ `PriceRule` và thêm tên. Loại: vẫn còn phạm vi và tầng ưu tiên mà report không có.
  - Có bản nháp trước khi công bố. Loại: report không có bước nháp; thêm trạng thái chỉ làm khó kiểm độ phủ.
- Lý do: FR-53, FR-54, FR-56, BR-51, BR-56, BR-57. Phiên bản giữ được giá đã dùng để tra cứu và cho phép lên lịch đổi giá.

### D2. Liên kết sân – bảng giá theo thời gian
- Chọn:
  - `court_price_table_links` (`court_id`, `price_table_id`, `kind`, `starts_at`, `ends_at` nullable).
  - Mỗi sân tại mỗi thời điểm có tối đa một liên kết thường và một liên kết lễ, chặn bằng ràng buộc exclusion trên khoảng thời gian.
  - Áp bảng cho nhiều sân cùng lúc với một thời điểm chuyển `s`. Mặc định là bây giờ, không được ở quá khứ.
  - Thay đổi mới nhất thắng: liên kết cùng loại đang hiệu lực tại `s` kết thúc ở `s`, các liên kết cùng loại đã lên lịch bắt đầu sau `s` bị xoá, liên kết mới bắt đầu ở `s`. Kết thúc liên kết cũng xoá các liên kết cùng loại lên lịch sau thời điểm kết thúc. Kiểm độ phủ chạy trên bộ liên kết sau thay đổi.
  - Liên kết bị xoá chưa từng có hiệu lực. Đơn đã xác nhận cho giờ chơi sau `s` vẫn giữ bảng và phiên bản trong snapshot, nên không mất bằng chứng giá.
  - Bảng phải cùng cơ sở với sân, nếu không thì 422.
  - Bảng đã ngừng không gán mới và không nhận phiên bản mới; liên kết đang có vẫn chạy tới khi bị thay hoặc kết thúc.
  - Màn sân của module 2 và màn giá gọi cùng một action.
- Phương án khác:
  - Cột `courts.price_table_id`. Loại: không giữ được lịch sử và thời điểm chuyển, không có chỗ cho bảng lễ.
  - Từ chối áp khi sân đã có liên kết lên lịch sau `s`. Loại: owner phải tự huỷ lịch cũ trước; report không có bước đó.
  - Giữ liên kết lên lịch sau `s` và cắt liên kết mới tại đó. Loại: owner áp bảng C nhưng tới ngày lịch cũ thì giá lại đổi sang bảng B mà màn hình không báo.
- Lý do: FR-55, FR-57, BR-50, BR-57. "Court price table link" của module 2 để quản lý giá định nghĩa hiệu lực và cách resolve; requirement "Court price table links" của change này làm việc đó.

### D3. Kiểm độ phủ là điều kiện của mọi thay đổi giá và của việc mở sân
- Chọn:
  - `PriceCoverageService` tính từ một thời điểm trở đi, cho từng sân `active` hoặc `paused`.
  - Duyệt theo mẫu thứ trong tuần của từng đoạn (phiên bản × liên kết) và từng ngày cụ thể có trong bảng lễ hoặc trong ngoại lệ lịch phục vụ. Không quét từng ngày trong tương lai vô hạn.
  - Thiếu thì 422 `PRICE_COVERAGE_INCOMPLETE` với `missing_items`: sân, thứ hoặc ngày, giờ bắt đầu, giờ kết thúc.
  - Chạy khi công bố phiên bản, cho mọi sân `active` hoặc `paused` có liên kết đang hoặc sẽ hiệu lực tới bảng đó, và khi áp, thay hoặc kết thúc liên kết. Sân `inactive` không được kiểm ở các bước này.
  - Mở và resume sân: module 2 gọi service này như một điều kiện của "Court opening conditions" (task 3.3–3.5 của họ). Khoảng thiếu nằm trong `missing_items` của 422 gộp mà module 2 trả; không trả `PRICE_COVERAGE_INCOMPLETE` riêng. Change này chỉ cung cấp service, dạng kết quả và mã lỗi.
  - Thêm `GET /admin/courts/{court}/price-coverage` (chỉ owner) để màn sân hiển thị các khoảng thiếu.
  - Bỏ khái niệm giá base phủ cả ngày.
- Phương án khác: giữ base làm giá dự phòng. Loại: BR-52 cấm mở giờ chưa có giá, và base vét chính là nguồn của lỗi prod số 5.
- Lý do: BR-52, BR-57, STEP-2 của report, "Court opening conditions" của module 2.

### D4. Ngày lễ không bù giá thường
- Chọn: vào ngày bảng lễ của sân có liệt kê, chỉ khoảng giờ của bảng lễ được tính. Giờ không có giá lễ thì không bán và không mở được sân.
- Phương án khác: bù bằng giá thường (spec cũ). Loại: BR-53 cấm.
- Lý do: BR-53.

### D5. Tính tiền theo phút
- Chọn:
  - Engine chia `[start, end)` tại mọi mốc đổi giá: biên khoảng giờ, `effective_at` của phiên bản và thời điểm chuyển liên kết.
  - `T = ⌊Σ dᵢ·pᵢ / 60⌋`: cộng phần chính xác của mọi đoạn trước, bỏ phần thập phân một lần. Dùng số nguyên (`Σ dᵢ·pᵢ` rồi chia nguyên cho 60), không dùng float.
  - Phút không có giá → 422; không dùng giá base, giá mặc định, giá lân cận hay giá đầu tiên tìm thấy.
  - Bỏ `CourtPriceSlot`.
- Phương án khác: giữ slot 1 giờ. Loại: module 4 đã chọn lưới 30 phút (D14 của họ).
- Lý do: BR-54, BR-55, BR-67 và ví dụ 15:30–17:00 = 190.000 của report.

### D6. Giá theo thời điểm xác nhận
- Chọn:
  - Với giờ chơi t của đơn xác nhận lúc c, phiên bản áp dụng là phiên bản công bố muộn nhất trong các phiên bản có `published_at ≤ c` và `effective_at ≤ t`.
  - Vì vậy phiên bản mới thay cả phiên bản cũ đã lên lịch nhưng chưa tới hiệu lực.
  - Đơn đã xác nhận giữ snapshot. Đơn chờ thanh toán do module 4 kiểm lại khi nhận sự kiện D16.
  - `expected_total`: module 4 từ chối 409 `PRICE_CHANGED` khi số tính lại cao hơn số khách đã xem; bằng hoặc thấp hơn thì tạo đơn theo số tính lại (D5 của module 4).
- Phương án khác: giữ snapshot báo giá 15 phút. Loại: BR-56 tính theo đơn chưa xác nhận, không theo báo giá.
- Lý do: BR-56, BR-68 và bảng ba trường hợp công bố/hiệu lực của report (dòng 285–292).

### D7. Chương trình khuyến mãi thuộc một cơ sở
- Chọn: `promotion_programs.venue_id` cùng bảng sân áp dụng (`promotion_program_courts`, không rỗng, cùng cơ sở). Bỏ phạm vi tổ chức, nhóm khách (`eligibility_group`) và luật kết hợp với hội viên.
- Phương án khác: giữ D25/D26 cũ. Loại: report không có các khái niệm này.
- Lý do: BR-58, BR-63, BR-64.

### D8. Hai loại voucher
- Chọn:
  - `public`: hiện trong danh sách cho mọi khách, một tổng lượt (`max_uses`; số đang giữ và đã dùng đếm từ `voucher_uses`, D11).
  - `private`: hệ thống sinh N mã duy nhất trên toàn nền tảng, mỗi mã dùng một lần. Bảng `voucher_codes` (`program_id`, `code` unique) không có cột trạng thái: mã còn trống, đang giữ hay đã dùng, và đơn đã dùng nó, đọc từ `voucher_uses` (D11). Sinh mã có thử lại khi va chạm unique.
  - Mã riêng không gắn với tài khoản nào.
- Phương án khác: một mã nhiều lượt (hiện tại). Loại: FR-61, BR-62 yêu cầu mã một lần.
- Lý do: FR-60, FR-61, BR-61, BR-62.

### D9. QR là deep link
- Chọn:
  - QR chứa URL `/courts/{court_slug}/schedule?voucher=<code>`. Route này là `ScheduleView`; `useCourtSchedule` đọc slug của sân và cho chọn sân khác trong cùng cơ sở.
  - Sân trong URL là sân có id nhỏ nhất trong tập sân của chương trình tại lúc owner mở danh sách mã. Khách đổi sân thì mã được kiểm lại theo sân đang chọn.
  - Quét bằng camera điện thoại mở trang đặt sân với mã điền sẵn. FE sinh QR bằng gói `qrcode` đã có trong `frontend/package.json`; không thêm thư viện quét trong app.
- Phương án khác:
  - URL `/{venue}/book?voucher=<code>` (bản trước). Loại: không có route này; thêm trang đặt sân cấp cơ sở chỉ để phục vụ một link.
  - Thêm thư viện quét QR. Loại: cần duyệt dependency mới, trong khi deep link đã đủ cho FR-65.
- Rủi ro: owner bỏ sân trong URL khỏi tập sân sau khi đã gửi QR. Khách mở link sẽ thấy lý do `court_not_covered` và chọn sân khác; mã vẫn dùng được.
- Lý do: FR-61, FR-65, BR-62 ("mã QR và mã ký tự là cùng một voucher").

### D10. Tự chọn voucher
- Chọn:
  - `GET /venues/{venue}/vouchers?court_id&starts_at&ends_at` chỉ trả voucher công khai áp được cho đơn tại lúc gọi, kèm D đã tính.
  - Mặc định chọn D lớn nhất sau khi bỏ phần thập phân; hoà thì `ends_at` sớm hơn, rồi `created_at` sớm hơn.
  - Chọn, nhập hay quét không giữ và không trừ lượt.
  - Đổi sân hoặc giờ: tính lại. Voucher khách tự chọn hoặc tự nhập mà còn áp được thì giữ. Voucher không còn áp được thì bỏ kèm thông báo. Khi voucher bị bỏ, khi chưa áp voucher nào, hoặc khi đang dùng voucher mặc định, thì chọn lại mặc định.
- Lý do: FR-64, FR-65, FR-66, BR-64, BR-65, BR-68, BR-69.

### D11. Service voucher nguyên tử cho module 4
- Chọn:
  - `VoucherUsageService::hold(Reservation, VoucherChoice)`; `release(Reservation)`; `consume(Reservation, CarbonInterface $confirmedAt)`.
  - `hold` do change này gọi trong transaction tạo đơn, trên cả hai đường: `OrdinaryCheckoutService::checkout` và `ReservationService::hold`. Thứ tự trong transaction: khoá sân và cơ sở, nhả các hold quá hạn (`releaseOverdueHolds`), khoá dòng chương trình được gửi (D17), tính lại giá, chèn đơn, gọi `hold`, rồi mới `ReservationPaymentService::initiate`. `hold` thêm một dòng lượt `voucher_uses` trạng thái `held` cho đơn (kèm mã riêng nếu có và D). Số đang giữ và đã dùng đếm từ các dòng lượt; luôn giữ `held + used ≤ max_uses`, và mỗi mã riêng có nhiều nhất một dòng `held` hoặc `used` (chỉ mục unique một phần). Dưới khoá, `hold` kiểm lại voucher còn áp được; hỏng thì ném `VoucherNoLongerApplies` kèm lý do của D20, checkout trả 422 `VOUCHER_INVALID` và không tạo gì. `hold` không trả gì: hạn thanh toán và D lấy từ snapshot, vốn đúng vì chương trình đã bị khoá trước khi tính giá. Idempotent theo reservation: gọi lại cho cùng đơn không giữ thêm lượt.
  - Transaction nào xác nhận đơn thì gọi `consume` (D29). Đơn được xác nhận ngay trong transaction tạo nó thì change này gọi `consume` ngay sau `hold`: ở `OrdinaryCheckoutService::checkout` là đơn 0đ và đơn trả bằng ví legacy; ở `ReservationService::hold` chỉ là đơn 0đ của khách chuyển khoản trực tiếp. Đơn legacy tạo qua `ReservationService::hold` vẫn ở `held` và được `consume` lúc `ReservationService::confirm`. Module 4 gọi `consume` ở các lần xác nhận sau: `OwnerPaymentAttemptService::processReceipt` (chuyển khoản trực tiếp, chạy trong job), `SplitPaymentService::confirmPaidAttempt` (gọi từ `processReceipt`, `payShare`, `coverShortfall`) và `ReservationService::confirm` (ví legacy). Các đường xác nhận của `RecurringSeriesService` không cần `consume`, vì đơn định kỳ không giữ voucher (D12). `held → used`. Idempotent theo reservation.
  - `consume` kiểm lại giống D19: voucher còn áp được cho đơn theo "Voucher eligibility and discount" (tính cả lượt đơn đang giữ), và D bằng tiền giảm đã ghi trên đơn. Thời hạn (bắt đầu, kết thúc) xét tại `$confirmedAt` do nơi gọi truyền: `processReceipt` truyền `$receipt->event_at`, `confirmPaidAttempt` truyền `$paidAt` (cùng giá trị module 4 ghi vào `confirmed_at`), checkout và `ReservationService::confirm` truyền `now()`. Chương trình đã dừng thì không áp được, bất kể `$confirmedAt`.
  - Kiểm lại hỏng → `consume` ném `VoucherNoLongerApplies` và không đổi bộ đếm. Đơn kết thúc `expired` với lý do `voucher_changed`, lượt được nhả một lần và khách được báo. Việc nhả phải commit: không nhả rồi ném lỗi trong cùng một transaction. Module 4 xử lý theo từng đường:
    - `processReceipt` (đơn trả một lần): gọi `consume` sau khi `applyAttemptChecks` ra ALLOCATED và đơn đã khoá qua kiểm `held`, trước khi lưu khoản nhận và tạo `OwnerPaymentAllocation`. Hỏng thì khoản nhận thành EXCEPTION mã `voucher_changed`, gọi `releaseHeld(…, 'expired', 'voucher_changed')`, và để `recordReceiptException` ghi ngoại lệ thanh toán như các ngoại lệ khác; không ném lại, để job không chạy lại.
    - `SplitPaymentService::confirmPaidAttempt`: khi nhóm đủ tiền, khoá đơn trước (hiện đơn chỉ bị khoá sau khi nhóm thành `confirmed`; dùng `HeldReservationRelease::lock` để giữ thứ tự attempt → reservation → chương trình), gọi `consume`, rồi mới đánh dấu nhóm `confirmed`. Hỏng thì làm như `SplitPaymentService::expire` (nhóm và phần chia còn chờ sang `expired`, hết hạn các attempt `split_share` còn chờ, ghi mọi phần chia và khoản bù đã trả, kể cả phần vừa nhận, là ngoại lệ thanh toán đúng một lần qua `SplitExpiredShareExceptions::record`) nhưng nhả đơn bằng `releaseHeld(…, 'expired', 'voucher_changed')` trên chính bản đơn đã khoá; không ném lại. Nhóm đã `expired` nên `expireDue` không ghi lần hai. `payShare` và `coverShortfall` chỉ gọi lại `confirmPaidAttempt` cho khoản nhận mà `processReceipt` đã xử lý, nên lỗi này chỉ xảy ra qua `processReceipt`.
    - `ReservationService::confirm` (ví legacy): gọi `consume` trước khi trừ ví. Hỏng thì nhả đơn trong transaction của service, không trừ ví, không ghi ngoại lệ thanh toán, rồi ném `VoucherNoLongerApplies` sau khi transaction đó xong. `ReservationController::confirm` bắt lỗi này bên trong closure của `IdempotencyService::run` (closure chạy trong `DB::transaction`) và trả 422 `VOUCHER_INVALID` kèm lý do, để transaction ngoài commit việc nhả.
    - Ở đường tạo đơn của change này, `consume` chạy ngay sau `hold` dưới cùng khoá; nếu hỏng thì đi theo `hold`: huỷ transaction, 422 `VOUCHER_INVALID`, không tạo gì.
  - `release` nhả đúng một lần, không gia hạn voucher; với đơn đã xác nhận thì không làm gì. Module 4 gọi `release` ở mọi đường nhả đơn `held`: `releaseHeld` và `ReservationService::release` (gọi từ `SplitPaymentService::expire`, `RecurringReleaseService`, và `ReservationService::confirm` khi hold đã quá hạn). Nhánh cuối hiện gọi `release` rồi `abort(409)` trong cùng transaction nên bị rollback; lượt của đơn đó được `releaseHeld` nhả sau (`ExpirePendingBookings`, `releaseOverdueHolds`).
  - `consume` và `release` không làm gì khi đơn không giữ voucher, nên module 4 nối được trước khi checkout bắt đầu gọi `hold` (D29).
  - Thứ tự khoá chung: sân → cơ sở → attempt rồi reservation của đơn cần nhả hoặc xác nhận → chương trình. `hold` khoá dòng chương trình `FOR UPDATE`; `consume` khoá chương trình `FOR SHARE` (chặn lệnh sửa hoặc dừng của chủ sân tới khi commit). Nơi gọi `consume` mà sau đó còn chèn dòng có khoá ngoại tới cơ sở phải khoá cơ sở `FOR KEY SHARE` trước `consume`: `processReceipt` khoá ngay sau khi khoá đơn, vì insert `OwnerPaymentAllocation` sau đó khoá ngầm cơ sở, xung đột với khoá `FOR UPDATE` checkout giữ trên cơ sở trong lúc chờ chương trình. Thứ tự attempt → reservation → cơ sở đã có trên main ở đường này (insert allocation), nên khoá sớm không thêm vòng mới. Các đường chia tiền chèn allocation trước `confirmPaidAttempt`; `ReservationService::confirm` không chạm cơ sở nên không khoá cơ sở; `release` chỉ sửa dòng lượt của đơn, không khoá chương trình hay mã. Vì vậy các transaction nhả nhiều đơn liền nhau (`ExpirePendingBookings`, `releaseOverdueHolds`, D19, listener giá của module 4) không giữ khoá chương trình khi khoá đơn tiếp theo. Lệnh tạo hoặc cập nhật của chủ sân có gửi tập sân khoá mọi sân trong tập đó (`FOR SHARE`, theo id) trước khi chèn hoặc khoá chương trình, không chỉ phần chênh đọc trước khoá (một lệnh cập nhật khác commit trước có thể làm phần chênh lớn hơn): khoá ngoại của dòng `promotion_program_courts` mới khoá `FOR KEY SHARE` dòng sân, xung đột với khoá `FOR UPDATE` mà checkout giữ trên sân trong lúc chờ khoá chương trình; với lệnh tạo, chèn chương trình còn khoá ngầm cơ sở trước khi chèn liên kết sân, ngược thứ tự sân → cơ sở của checkout. Trên main vẫn có đường khoá cơ sở rồi sân: `ManageCourtBlocks::createBlock` (`FOR UPDATE` cả hai), `SaveVenueScheduleException` và `SaveVenueOperatingSchedule` (cơ sở `FOR UPDATE` rồi khoá ngầm sân khi ghi dòng có `court_id`); chúng đã ngược với checkout từ trước. Lệnh tạo và cập nhật chương trình theo thứ tự của checkout và chạy `DB::transaction(…, 3)` để Laravel thử lại khi PostgreSQL huỷ một bên vì deadlock với các thao tác hiếm đó. Checkout khoá dòng `users` `FOR UPDATE` sau chương trình (`OrdinaryCheckoutService:82`, để tuần tự hoá điểm); task 2.10 bỏ khoá này cùng phần điểm, vì `ReservationService::confirm` giữ khoá ngầm trên dòng người dùng (insert `idempotency_records`) trong lúc chờ chương trình.
  - Đơn đã xác nhận bị huỷ, chuyển sân hay đổi giờ không trả lượt và không tạo thêm lượt.
- Phương án khác:
  - Chỉ đếm lúc xác nhận, không giữ lượt. Loại: đơn chờ thanh toán tới 10 phút; hai đơn chờ cùng giữ lượt cuối thì một đơn đã trả tiền sẽ bị từ chối lúc xác nhận.
  - Module 4 tự sửa bộ đếm. Loại: bộ đếm thuộc module 3, đã thống nhất ngày 2026-10-20.
  - Module 4 gọi `hold`. Loại: task 5.5 của module 4 chỉ nối `consume` và `release`; checkout là chỗ change này viết lại để áp voucher (task 5.4). Hai module cùng sửa một transaction dễ gọi `hold` hai lần hoặc không gọi. Cùng lý do, `consume` của đơn xác nhận ngay khi tạo cũng do change này gọi.
  - `consume` không kiểm lại, vì D19 đã nhả đơn chờ khi chương trình đổi. Loại: D19 chạy sau commit; chuyển khoản tới giữa lúc dừng chương trình và lúc D19 chạy sẽ xác nhận đơn bằng voucher của chương trình đã dừng, trái BR-71.
  - Vẫn xác nhận đơn nhưng bỏ voucher và đòi thêm tiền. Loại: khách đã trả đúng số đã đồng ý; tự đổi số tiền trái BR-68 ("không tự xác nhận khi có thay đổi").
  - Bộ đếm `held_count`/`used_count` trên dòng chương trình (bản trước). Loại: `release` phải khoá dòng chương trình; transaction nhả nhiều đơn liền nhau giữ khoá chương trình của đơn trước khi khoá đơn sau, ngược thứ tự với checkout và `processReceipt`, nên có thể deadlock.
  - Đổi `createBlock` và hai action lịch sang sân → cơ sở. Loại: sửa thứ tự khoá ở code lịch phục vụ của module khác; đổi riêng `createBlock` lại tạo vòng mới với hai action lịch (kiểm chứng vòng 9).
  - Khoá cơ sở ngay trong `consume`. Loại: `ReservationService::confirm` khoá đơn rồi mới tới cơ sở, ngược với checkout (cơ sở rồi đơn quá hạn qua `releaseOverdueHolds`); đường này trên main không chạm cơ sở nên đó là vòng mới.
  - Gọi `consume` sau khi chèn allocation trong `processReceipt`. Loại: `consume` hỏng thì khoản nhận thành ngoại lệ mà allocation đã chèn vẫn commit.
  - Checkout khoá sân và cơ sở bằng `FOR NO KEY UPDATE` để không xung đột với khoá ngầm của khoá ngoại. Loại: đổi chế độ khoá ở code của module 4 và các đường khác đang dựa vào khoá sân để chặn chèn đơn.
  - `consume` xét thời hạn theo lúc chạy. Loại: job `ProcessOwnerPaymentReceipt` chạy lại tới 8 lần có backoff; khoản nhận tới trước khi chương trình kết thúc mà job chạy sau đó sẽ bị từ chối oan, và lệch `confirmed_at` mà module 4 ghi.
  - Để `VoucherNoLongerApplies` lan ra khỏi closure của `IdempotencyService::run`. Loại: transaction ngoài rollback luôn việc nhả, đơn còn giữ lượt tới khi `releaseHeld` chạy.
- Lý do: FR-67, BR-61, BR-63, BR-69, BR-70, BR-71. Hạn thanh toán không quá hạn voucher lúc tạo đơn (D3 của module 4 cho đơn trả trực tiếp; task 5.4 chặn thêm đơn legacy tạo qua `ReservationService::hold`), nên thời hạn chỉ hỏng khi owner rút ngắn chương trình (D19 xử lý) hoặc chuyển khoản tới sau hạn (module 4 đã ghi là ngoại lệ).
- Ghi chú: BR-69 nói "chọn, nhập mã hoặc quét QR không giữ chỗ"; lượt chỉ được giữ khi đơn chờ thanh toán đã tạo, và chỉ được ghi là đã dùng khi đơn xác nhận. Màn theo dõi tách "đang giữ" khỏi "đã dùng" (FR-68; requirement "Voucher usage tracking", task 6.3).

### D12. Phạm vi voucher
- Chọn: voucher chỉ áp cho đơn đặt sân thường. POS và đặt định kỳ gửi `coupon_code` hay mã voucher → 422, không âm thầm bỏ. Bán lại không áp voucher.
- Phương án khác: giữ coupon ở POS. Loại: BR-58 chỉ nói về đơn đặt sân, và lượt voucher đang bị đơn bán hàng tiêu thụ.
- Lý do: BR-58. Đặt định kỳ không có trong report; trả 422 tránh việc khách tưởng mã đã được áp.

### D13. Múi giờ khuyến mãi (đã làm ở Pha 0)
- Chọn: mọi thời điểm nhận vào được đổi sang `config('app.timezone')` trước khi lưu (cast `AppTimezoneDateTime`). Không migrate dữ liệu cũ.
- Lý do: không phân biệt được dòng tạo qua UI (lệch) với dòng từ seeder hay test (đúng). Prod là dữ liệu demo và sẽ reseed.

### D14. Điều khoản cố định từ lúc tạo
- Chọn: chương trình phát hành ngay khi tạo. Loại voucher, hình thức giảm, các mã riêng và số mã không đổi được từ lúc đó. Tên, giá trị giảm, thời hạn, tập sân, tiền thuê tối thiểu, mức giảm tối đa và (với công khai) tổng lượt sửa được; tổng lượt không nhỏ hơn `held + used`.
- Phương án khác: khoá sau lần kích hoạt đầu (Pha 0, D14 cũ). Loại: mô hình mới không còn trạng thái nháp.
- Lý do: FR-62, BR-71. Đổi mã hay hình thức giảm làm hỏng mã và QR đã gửi khách, và làm lẫn lịch sử.

### D15. Mốc kết thúc trong ngày
- Chọn: `23:59` được hiểu là hết ngày (phút 1440), cho cả khoảng giờ của bảng giá và lịch hoạt động.
- Phương án khác: nhận giá trị `24:00`. Loại: `date_format:H:i` không nhận `24:00`, và UI đã dùng "00:00 – 23:59" cho cả ngày.
- Lý do: không có quy ước này thì cơ sở mở tới nửa đêm sẽ thiếu giá cho 23:59–24:00.

### D16. Sự kiện giá có hiệu lực chỉ phát lúc công bố và lúc đổi liên kết
- Chọn:
  - `CourtPricesTookEffect(courtIds, effectiveAt)` phát sau commit khi công bố phiên bản, với `effectiveAt = max(effective_at, now)` và các sân đang hoặc sẽ dùng bảng đó.
  - Cũng phát khi áp, thay hoặc kết thúc liên kết, với `effectiveAt` là thời điểm chuyển.
  - Không phát khi tới `effective_at`.
- Phương án khác: phát cả khi tới `effective_at` (hợp đồng (a) ban đầu trong plan; D11 của module 4). Loại: theo D6, giá mà một đơn xác nhận từ giờ trở đi phải trả chỉ đổi khi có phiên bản mới được công bố hoặc liên kết đổi. Lúc tới `effective_at`, không đơn chờ nào đổi tiền, nên phát lần nữa chỉ làm module 4 tính lại vô ích.
- Lý do: BR-56, BR-87 (module 4). Module 4 cần sửa câu ở D11 và Open Questions của họ (task 1.3).

### D17. Chữ ký engine
- Chọn:
  - `RentalPriceCalculator::quote(Court $court, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?VoucherChoice $voucher, ?Reservation $heldBy = null)` trả `{rental, discount, total}` cùng snapshot: các phần giá (bảng, phiên bản, số phút, đơn giá) và voucher (chương trình, điều khoản, `ends_at`) hoặc `null`.
  - `VoucherChoice` là id chương trình công khai hoặc mã riêng. Request đặt sân gửi `voucher_program_id` (công khai) hoặc `voucher_code` (riêng), không gửi cả hai; gửi cả hai → 422.
  - `$heldBy`: khi tính lại một đơn `held` (D19, listener giá của module 4), lượt hoặc mã mà chính đơn đó đang giữ không bị tính là "đang giữ cho đơn khác" hay "hết lượt". Thiếu tham số này thì đơn đang giữ lượt cuối hoặc giữ mã riêng sẽ bị coi là mất voucher và bị nhả oan.
  - Bỏ tham số `?customer` của chữ ký dự kiến: voucher không còn điều kiện theo khách và đặt sân không còn giảm hội viên.
  - Hạn thanh toán lấy từ hạn voucher trong snapshot trước khi chèn đơn (D3 của module 4; task 5.4 của change này nối vào `ReservationIntake::paymentDeadline`, D29). Đơn legacy tạo qua `ReservationService::hold` giữ hạn báo giá theo thiết kế của module 4; task 5.4 chặn hạn đó bằng hạn voucher trong snapshot.
  - Khi request có voucher, transaction tạo đơn khoá dòng chương trình được gửi (`FOR UPDATE`) sau `releaseOverdueHolds` và trước khi tính lại giá; thứ tự khoá là sân → cơ sở → attempt và reservation của hold quá hạn → chương trình (D11). Mã riêng không cần khoá riêng: mọi lần giữ mã của chương trình đã đi qua khoá chương trình, và chỉ mục unique một phần trên dòng lượt chặn hai lượt `held`/`used` cho cùng mã. Snapshot (D, `ends_at`) vì vậy đúng tới lúc commit. Lệnh cập nhật hoặc dừng chương trình của chủ sân ghi vào cùng dòng nên phải chờ checkout commit; D19 chạy sau commit của chủ sân nên thấy đơn mới.
  - Chi tiết đơn đọc voucher (tên chương trình, mã riêng nếu có, D) từ dòng `voucher_uses` của đơn qua `Reservation::voucherUse`, khi dòng ở trạng thái `held` hoặc `used`; không chép sang cột hay `metadata` của đơn.
- Phương án khác:
  - `hold` trả hạn voucher và checkout hạ `hold_expires_at` khi hạn đó sớm hơn (bản trước). Loại: `hold` phải chạy sau khi chèn đơn mà trước `ReservationPaymentService::initiate` (hàm này chép `hold_expires_at` vào attempt), thêm một nhánh, và khi D ở `hold` khác snapshot thì không có lý do lỗi nào khớp.
  - Khoá chương trình ngay sau khoá sân. Loại: nằm trước `releaseOverdueHolds`, vốn khoá attempt rồi reservation của hold quá hạn; job hết hạn và webhook khoá theo thứ tự attempt → reservation → chương trình, nên hai thứ tự ngược nhau gây deadlock.
  - Từ chối đơn khi hạn đổi giữa lúc tính giá và lúc `hold`. Loại: vẫn còn khe đó, và khách nhận lỗi trong khi voucher vẫn áp được.
  - Thêm lý do `discount_changed` hoặc trả 409 `PRICE_CHANGED` khi D ở `hold` khác snapshot. Loại: khi đã khoá trước lúc tính giá thì D không đổi được trong transaction.
- Lý do: chủ sân có thể rút ngắn hoặc dừng chương trình giữa lúc tính lại giá và lúc `hold`; D19 chạy sau commit nên có thể bỏ sót đơn commit muộn hơn. Khoá trước khi tính giá đóng khe này mà không cần kiểm lại hạn hay D. Giá phải trả: các checkout cùng một chương trình chạy nối tiếp nhau trong một transaction ngắn, như khoá sân hiện nay.

### D18. Availability và giá "từ"
- Chọn:
  - Viết lại `AvailabilityController`: sinh mốc từ lịch phục vụ, giá lấy từ engine, so chồng lấn nửa mở, dùng lại `Reservation::scopeBlocksInventory()` của module 4 để coi hold quá hạn là trống. Giờ không có giá thì không mở. Pha 2 vì vậy chờ Pha 2 của module 4 merge.
  - Bước lưới nằm ở một chỗ. Ở Pha 2 bước là `slot_duration` của sân, vì đầu vào đặt sân vẫn là `slots` (D29). Module 4 đổi bước sang 30 phút cho mọi sân trong merge task 5.1 của họ, cùng lúc đầu vào đổi sang `starts_at/ends_at`, và viết test bốn mốc 30 phút của đơn 19:00–21:00 (D29).
  - Panel availability ở trang chủ hiện các mốc 30 phút từ merge đó (module 4 sửa); requirement "Home availability identifies its selected court and date" được sửa theo (MODIFIED, giữ ở change này).
  - Giá "từ" của cơ sở hoặc sân là đơn giá theo giờ thấp nhất mà một đơn xác nhận bây giờ có thể trả trên các sân `active`. Không có giá thì không hiển thị.
  - API trả `from_price` cho từng sân trong danh sách sân công khai (cache 300 giây theo `courts_version`) và trong chi tiết sân; giá "từ" của cơ sở là giá nhỏ nhất trong các sân của nó. Áp, thay, kết thúc liên kết và công bố phiên bản tăng `courts_version` sau commit. Thay đổi lên lịch hiện chậm nhất 300 giây sau thời điểm hiệu lực (không có job, D16).
  - Bỏ ước tính làm tròn lên và giá dự phòng trên trang sân.
- Lý do: FR-58, BR-52; FR-69 và FR-70 do module 4 giao (mục "Phần thuộc module 3" trong traceability của họ).

### D19. Cập nhật hoặc dừng chương trình khi có đơn chờ
- Chọn: sau commit, tìm các đơn `held` đang giữ voucher của chương trình và tính lại bằng engine với `$heldBy` là chính đơn đó (D17). Voucher không còn áp được, D đổi, hoặc hạn thanh toán của đơn sau thời điểm kết thúc mới của chương trình → `releaseHeld(…, 'expired', 'voucher_changed')` của module 4, và gửi thông báo cho khách. Còn lại thì giữ nguyên đơn. Chuyển khoản tới trước khi bước này chạy do `consume` chặn (D11).
- Phương án khác: để đơn tự hết hạn sau tối đa 10 phút. Loại: nếu khách chuyển tiền trong lúc đó thì sẽ trả số tiền khác số tiền đã đồng ý, hoặc dùng voucher của chương trình đã dừng (trái BR-71).
- Lý do: FR-62, FR-63, BR-71. Dùng chung bước nhả của module 4, giống cách xử lý khi giá đổi.

### D20. Lỗi voucher khi checkout
- Chọn: checkout kiểm voucher trước khi so `expected_total`. Voucher không áp được → 422 `VOUCHER_INVALID` với `reason` (`not_found`, `not_started`, `expired`, `used`, `held_elsewhere`, `stopped`, `exhausted`, `court_not_covered`, `minimum_not_met`) và `{rental, discount: 0, total}`. Không tạo gì.
- Phương án khác: âm thầm bỏ voucher rồi trả 409 `PRICE_CHANGED`. Loại: khách không biết vì sao tiền tăng, trái BR-68 ("thông báo cho khách").
- Lý do: FR-65, FR-67, BR-68.

### D21. Bỏ giảm hội viên và điểm khỏi đặt sân (Q2 = A)
- Chọn:
  - `OrdinaryCheckoutService` chỉ còn tiền thuê, một voucher và `P = T − D`. Xoá `loyalty/preview`, `LoyaltyPointSelector`, phần điểm của form đặt sân và việc giữ điểm.
  - Ví legacy vẫn trả số tiền phải trả.
  - Hội viên và tích điểm vẫn ở POS. `PosService::redeemPoints` và `PosController::redeemPoints` không có route nên bị xoá; `loyalty/redeem` vẫn trả 422.
  - Đơn cũ hiển thị các khoản giảm đã ghi.
  - Thời điểm: Pha 2, cùng lúc bỏ phần tính giá cũ của `PriceQuoteService` (D28).
- Phương án khác: B – giữ, ghi là lệch có chủ đích. Loại: người dùng chọn A.
- Lý do: BR-64, BR-66. Hệ quả: điểm chỉ còn được tích, không còn đổi ở đâu. Đây là quyết định sản phẩm cần người dùng xem lại (Open Questions).

### D22. Không chuyển campaign và coupon cũ
- Chọn: không chuyển sang chương trình mới. Pha 4 backup prod, kiểm khoá ngoại tới các bảng cũ, rồi xoá bảng campaign và coupon. Người dùng xác nhận trước khi chạy trên prod. Snapshot coupon trong đơn cũ giữ nguyên. Reseed voucher demo.
- Phương án khác: chuyển coupon đang active thành voucher công khai giữ nguyên mã (rủi ro trong plan). Loại: coupon cũ cấp tổ chức, có nhóm khách và nhiều lượt, không có tập sân; chuyển sẽ phải bịa phạm vi. Prod là dữ liệu demo.
- Lý do: AGENTS.md (cutover sạch, không shim). Giá thì vẫn chuyển (Pha 2) vì đang bán.

### D23. Giá bán lại tối đa là số tiền thực trả
- Chọn:
  - Giá tối đa của một slot là số tiền phải trả của đơn sau mọi khoản giảm đã ghi (voucher, coupon, hội viên, điểm). Với đơn nhiều slot cũ thì chia theo tỷ lệ tiền thuê của slot, bỏ phần thập phân. Đơn mới chỉ có một slot nên giá tối đa là P.
  - API trả `max_resale_price` cho từng slot; `MarketplaceSellModal` và `useResaleMarketplace` hiển thị và chặn theo giá trị này thay vì tự tính từ giá gốc. Metadata của listing lưu trần đã dùng. Quyền của người mua không đổi.
- Phương án khác: giữ giá gốc trước giảm. Loại: khách mua bằng voucher rồi bán lại với giá gốc là kiếm lời từ khuyến mãi của cơ sở.
- Lý do: Q3 do người dùng chốt.

### D24. Buổi chơi lấy giá từ bảng giá
- Chọn: giá gợi ý là đơn giá theo bảng giá tại giờ bắt đầu buổi, qua engine. Không có giá thì không gợi ý. Tiền sân `⌊phút × giá / 60⌋` thay cho `round(…, PHP_ROUND_HALF_UP)`, cả ở server và ở phần xem trước hoá đơn phía FE (`useDropInSessionBill`). Form hoá đơn lấy giá sân gợi ý từ endpoint `rates`, không dùng giá cứng; không có giá thì để trống cho chủ nhập.
- Lý do: Q3; BR-67 (bỏ phần thập phân, không làm tròn lên). `CourtPriceSlot` bị xoá ở Pha 2.

### D25. Chỉ owner quản lý giá và khuyến mãi
- Chọn: route giá và khuyến mãi dùng `RequireOrganizationRole:organization_owner`. Staff nhận 403, menu ẩn với staff. Owner của tổ chức khác nhận 403.
- Lý do: BR-49. Module 2 đã ghi "change prices" và "price-table link" là việc chỉ owner.

### D26. Thay requirement bằng REMOVED + ADDED khi phải bỏ scenario
- Chọn: "Immutable price quotes", "Authoritative ordinary checkout and promotion result" và "Versioned tenant memberships and loyalty" được REMOVED và thay bằng requirement tên mới.
- Phương án khác: MODIFIED giữ tên scenario cũ nhưng viết lại nội dung. Loại: tên như "Venue and sport rule" hay "Concurrent points" sẽ mô tả sai hành vi.
- Lý do: OpenSpec từ chối khối MODIFIED thiếu scenario đang có ở spec chính (cả lúc validate và lúc archive). REMOVED bắt buộc ghi Reason và Migration nên việc bỏ được ghi rõ.

### D27. Phần trăm giảm có tới hai chữ số thập phân
- Chọn: `0 < r ≤ 100`, tối đa hai chữ số thập phân. Lưu số nguyên theo phần trăm của phần trăm (`percent_bp`, 12,5% = 1250). `D = ⌊min(T·r_bp / 10000, C, T)⌋` tính bằng `intdiv`, không dùng float.
- Phương án khác:
  - Chỉ nhận số nguyên (bản trước). Loại: BR-60 chỉ ghi "> 0 và ≤ 100"; chặn 12,5% là thêm luật report không có.
  - Lưu `decimal(5,2)` và tính bằng float. Loại: sai số float có thể làm D lệch 1đ so với công thức bỏ phần thập phân của BR-66, BR-67.
  - Nhận số lẻ bất kỳ. Loại: không lưu chính xác được và màn hình phải làm tròn khi hiển thị.
- Lý do: BR-60, BR-66, BR-67.

### D28. Thứ tự cắt mô hình cũ giữa các pha
- Chọn:
  - Pha 2 bỏ phần tính giá cũ của `PriceQuoteService` (`PriceTierResolver`, `DiscountService`, giảm hội viên) và cho nó tính bằng engine, nên mọi thứ nó đang tính cũng đi trong Pha 2. `PriceQuoteService` giữ hợp đồng `slots` của `PriceQuote` tới task 5.1 của module 4; từ đó module 4 đổi `PriceQuote` sang `starts_at/ends_at` hoặc bỏ nó (D29). Pha 2 gồm:
    - Checkout, hold, quote, tạo và xem trước đặt định kỳ nhận `coupon_code` hoặc `loyalty_points` không rỗng → 422. FE bỏ ô coupon và ô điểm của form đặt sân trong cùng merge.
    - Giảm hội viên trên đơn đặt sân, kể cả đặt định kỳ, dừng.
    - Xoá cả `LoyaltyPolicyService` (chỉ `preview` và phần điểm của checkout dùng), `LoyaltyController::preview` cùng constructor của nó, và route `loyalty/preview`. `LoyaltyController::redeem` vẫn trả 422 `DIRECT_REDEMPTION_RETIRED`.
    - Bỏ lời gọi giữ coupon ở checkout và hold, và phần đọc `Coupon`/`PromotionCampaign` trong `ReservationIntake::voucherEndsAt` (đơn mới không còn coupon). Lời gọi nhả coupon ở `releaseHeld` và `ReservationService::release` giữ tới Pha 4 để đơn chờ cũ vẫn trả lượt.
    - Thay mọi nơi đọc `PriceRule` hoặc `CourtPriceSlot` (Context, "Nơi còn đọc mô hình cũ"). "Sân có giá" của `CourtPriceRuleService` đổi thành "sân có liên kết thường đang hiệu lực"; module 2 dựng điều kiện đặt sân bằng kiểm độ phủ trên đó (task 3.5 của họ). Công suất ở `AdminController:367` lấy từ lịch phục vụ.
    - Giá "từ" làm ở Pha 2 (không đợi Pha 3) vì trang công khai đang đọc `price_slots`.
  - Pha 4 gỡ phần coupon còn lại: các chỗ ở `PosService` và `PosQrPaymentService`, nhả coupon ở `releaseHeld` và `ReservationService::release`, `QuoteCouponService`, phần coupon của `DiscountService`, quan hệ `Booking::coupon`. Đơn QR POS đang chờ mà đã giữ coupon được cho hết hạn và nhả coupon trước migration xoá bảng.
  - `VoucherUsageService` làm ở Pha 5, trước task checkout áp voucher. Không bật voucher khi bộ đếm chưa chạy, và không gọi `hold` khi module 4 chưa nối `consume` và `release` (D29).
  - Giữa Pha 2 và task 5.4, đơn đặt sân không có khoản giảm nào.
- Phương án khác:
  - Giữ một adapter `PriceQuoteService` gọi engine mới và vẫn cộng coupon, hội viên tới task 5.4. Loại: là shim; AGENTS.md yêu cầu cutover sạch.
  - Gộp Pha 2, 4, 5 thành một merge. Loại: diff quá lớn, khó review và khó rollback; Pha 2 đã chạm giá đang bán.
  - Âm thầm bỏ `coupon_code` và `loyalty_points`. Loại: khách tưởng mã đã được áp (cùng lý do D12).
- Lý do: AGENTS.md (cutover sạch, không shim); D12, D21, D22. Prod là dữ liệu demo nên khoảng không có giảm giá giữa Pha 2 và task 5.4 không ảnh hưởng khách thật.

### D29. Cổng nối với module 4: lưới, báo giá và lượt voucher
- Chọn:
  - Pha 2 giữ đầu vào đặt sân hiện tại: `slots` là các giờ bắt đầu, mỗi slot dài `slot_duration` của sân. Engine tính mỗi slot là `[start, start + slot_duration)`, nên slot đi qua mốc đổi giá được tính theo phút. Tổng tiền tính bằng một lần gọi engine cho mỗi đoạn slot liền nhau, nên phần thập phân chỉ bỏ một lần mỗi đoạn (D5); giá từng slot trong `slot_snapshot` lấy từ engine, slot cuối của đoạn nhận phần chênh để tổng các slot bằng tổng của đoạn. Bước lưới của `AvailabilityController` là `slot_duration`.
  - `PriceQuoteService` giữ hợp đồng `slots` của `PriceQuote` (token, hạn 15 phút, `slot_snapshot`) và các kiểm tra điều kiện đặt, lịch, khoá sân, chồng lấn; chỉ phần tính tiền chuyển sang engine. Giá vẫn được tính lại lúc gửi bằng `reprice` (D6); token và hạn 15 phút chỉ dùng cho các kiểm tra trước khi giữ chỗ, không làm căn cứ giá. Từ task 5.1 của module 4, họ đổi `PriceQuote` sang `starts_at/ends_at` hoặc bỏ nó.
  - Module 4 bật lưới 30 phút cho mọi sân và panel trang chủ trong merge task 5.1 của họ, khi đầu vào đổi sang `starts_at/ends_at`: họ sửa bước lưới trong `AvailabilityController` mà Pha 2 của change này viết lại, sửa panel trang chủ, và viết test bốn mốc 30 phút.
  - Pha 5 chia hai lần merge. Task 5.1–5.3 (phần voucher của engine, `VoucherUsageService`, danh sách voucher) merge trước, là hợp đồng cho Pha 5 của module 4. Pha 5 của module 4 chờ task 5.1–5.3 và Pha 3 (sự kiện giá). Task 5.4–5.5 (checkout áp voucher và gọi `hold`, FE chọn voucher) merge sau task 5.1, 5.5 và 5.7 của module 4.
  - `consume` và `release` không làm gì khi đơn không giữ voucher, nên module 4 nối được trước khi có `hold`.
  - Transaction nào xác nhận đơn thì gọi `consume`. Đơn được xác nhận ngay trong transaction tạo nó gọi `consume` ngay sau `hold` (task 5.4: đơn 0đ và ví legacy ở checkout, đơn 0đ trực tiếp ở `ReservationService::hold`). Module 4 gọi `consume` ở `OwnerPaymentAttemptService::processReceipt`, `SplitPaymentService::confirmPaidAttempt` và `ReservationService::confirm`, xử lý `VoucherNoLongerApplies` theo từng đường (D11), và gọi `release` ở `releaseHeld` và `ReservationService::release`.
  - File của module 4 mà change này sửa: Pha 2 cho `PriceQuoteService::create` và `reprice` tính bằng engine, và bỏ phần đọc coupon trong `ReservationIntake::voucherEndsAt`; Pha 4 bỏ nhánh nhả coupon trong `releaseHeld` và `ReservationService::release`; task 5.4 nối hạn voucher của snapshot engine vào `ReservationIntake::paymentDeadline`, chặn hạn đơn legacy trong `ReservationService::hold` bằng hạn voucher, và thêm khoá chương trình sau `releaseOverdueHolds`, trước `reprice`, ở hai đường tạo đơn (D17). Mỗi việc đều báo module 4 trước (task 1.3).
  - Pha 2 cũng sửa các test trên `main` đang gửi `coupon_code` cho đặt sân, trong đó có test của module 4 (task 2.10).
  - Chiều ngược lại: trong merge task 5.1 của họ, module 4 sửa `AvailabilityController` (bước lưới) và panel trang chủ mà Pha 2 của change này viết lại.
- Phương án khác:
  - Bật lưới 30 phút ngay Pha 2 và hiểu `slots` là các mốc 30 phút liền nhau. Loại: phải đổi nghĩa `slots`, `slot_snapshot` và số `ReservationSlot` trong khi module 4 sắp bỏ hẳn `slots`. Module 4 đã ghi "tới lúc đó lưới 1 giờ vẫn chạy như cũ".
  - Bật lưới 30 phút ở Pha 2 mà giữ nghĩa `slots`. Loại: sân 60 phút sẽ hiện giá 30 phút cho một mốc nhưng đặt và tính 60 phút, nên khách luôn nhận `PRICE_CHANGED`.
  - Xoá `PriceQuoteService` ở Pha 2. Loại: token báo giá và các kiểm tra trước khi giữ chỗ chưa có chỗ khác; module 4 thay hợp đồng này ở task 5.1.
  - Merge cả Pha 5 trước Pha 5 của module 4. Loại: lượt đã giữ không ai nhả hay tiêu, lượt `held` rò mãi.
  - Module 4 gọi `consume` cho đơn 0đ (bản trước). Loại: đơn 0đ và đơn ví legacy được xác nhận trong transaction checkout mà change này viết lại; một transaction có hai module cùng sửa dễ gọi thiếu hoặc gọi hai lần.
- Lý do: AGENTS.md (an toàn nhất, cutover sạch); D11, D18, D28; design và task 5.1 của module 4.

### Phối hợp và thứ tự archive
- Thứ tự archive: `direct-owner-cutover` → `align-module-02-venue-court` → change này → `align-module-04-booking`.
- Khối MODIFIED trùng header:
  - "Role-based owner operations": dựng trên text của module 2.
  - "Peer-to-peer marketplace transfer": dựng trên text của cutover; chỉ archive được sau cutover.
  - Trước khi archive, dựng lại các khối này từ text đang có ở spec chính và chỉ giữ phần sửa của change này (task 7.4).
- Module 4:
  - Câu "a booking still pending payment SHALL follow 'Pending bookings at a price change'" và scenario "Price rules change after quote acceptance" của họ nằm nguyên văn trong "Court quotes from price tables". Module 4 phải bỏ khối MODIFIED "Immutable price quotes"; nếu không, archive của họ báo "not found".
  - Change này nhắc tới "Pending bookings at a price change" của module 4 trước khi requirement đó vào spec chính, nên module 4 cần archive ngay sau change này.
  - Module 4 ghi lý do nhả `voucher_changed` (D19), thời điểm phát sự kiện (D16) và chữ ký (D17). Chữ "points" đã bỏ ở `851c173e`; `releaseHeld` vẫn hoàn điểm cho đơn cũ có giữ điểm.
  - Ai gọi gì (D11, D29): change này gọi `hold` ở hai đường tạo đơn, và `consume` cho đơn xác nhận ngay khi tạo (checkout 0đ và ví legacy; `ReservationService::hold` 0đ trực tiếp). Module 4 gọi `consume` ở `OwnerPaymentAttemptService::processReceipt`, `SplitPaymentService::confirmPaidAttempt` và `ReservationService::confirm`, gọi `release` ở `releaseHeld` và `ReservationService::release`. Module 4 bỏ chữ "đơn 0đ" ở task 5.5 và D12 của họ, và chuyển FR-75, BR-82 trong traceability của họ sang task 5.4 của change này. `consume` ném `VoucherNoLongerApplies` thì module 4 xử lý theo từng đường như D11.
  - Thứ tự kiểm khi tạo đơn: voucher (`VOUCHER_INVALID`) → so `expected_total` (`PRICE_CHANGED`) → hạn thanh toán (`PAYMENT_WINDOW_CLOSED`). Module 4 bỏ vế "or with a voucher that ends at that moment" khỏi scenario "No payment window left", vì voucher hết hạn đúng lúc gửi đã bị `VOUCHER_INVALID` chặn trước.
  - Pha 4 của change này bỏ nhánh nhả coupon trong `releaseHeld` và `ReservationService::release` (D28).
  - Pha 2 của change này dùng `Reservation::scopeBlocksInventory()`, cho `PriceQuoteService::create` và `reprice` tính bằng engine, và bỏ phần đọc coupon trong `ReservationIntake::voucherEndsAt`. Task 5.4 nối hạn voucher của snapshot engine vào `ReservationIntake::paymentDeadline`. Các việc này sửa file của module 4 (D29).
  - Cổng (D29): Pha 5 của module 4 chờ Pha 3 và task 5.1–5.3 của change này; task 5.4–5.5 của change này chờ task 5.1, 5.5 và 5.7 của module 4. Module 4 bật lưới 30 phút trong merge task 5.1 của họ.
- Module 2:
  - "Operator handling of affected bookings": đổi "reserved, unconsumed coupon uses and points" trong thân requirement thành "a held voucher use", và scenario ở dòng 51 của delta thành "a held voucher use SHALL be released exactly once". Sửa theo ở design dòng 146 và tasks dòng 361 của họ ("trả coupon và điểm đã giữ").
  - Module 2 nối `PriceCoverageService` vào `OpenCourt`, `ResumeCourt` và điều kiện đặt sân (task 3.3–3.5 của họ). Khoảng thiếu nằm trong `missing_items` của 422 gộp (D3).
  - Pha 2 của change này giữ tên lớp `CourtPriceRuleService` và đổi `isPriced`, `pricedCourtIds` sang kiểm liên kết; task 3.5 của module 2 dựng trên cả hai (D28).
- Không có delta `venue-court-operations`: module 2 giữ "Court price table link" và "Court opening conditions" (thống nhất ngày 2026-10-21).
- Không sửa `platform-readiness` dòng 202 ("promotions and points"): đây là danh sách phạm vi kiểm thử; khuyến mãi và điểm (tích ở POS) vẫn còn.

## Risks / Trade-offs

- **[Chuyển giá làm đổi giá đang bán]** Chuyển `PriceRule` và `CourtPriceSlot` sang bảng giá có thể lệch với giá cũ.
  → Script so sánh mọi sân × giờ × (14 ngày tới và mọi ngày có trong bảng lễ đã chuyển), chạy trong transaction, và kiểm độ phủ trên các liên kết đã chuyển. Lệch thì rollback và in danh sách. Backup prod trước.
- **[Availability rỗng]** Bỏ `CourtPriceSlot` mà chưa có bảng giá thì không giờ nào mở.
  → Viết lại `AvailabilityController` và chuyển dữ liệu trong cùng pha; test sân chỉ có bảng giá.
- **[Lệch hợp đồng với module 4]** D11, D16 và D17 khác tên, thời điểm và người gọi so với bản đã gửi trước đó.
  → Gửi module 4 ngay trong Pha 1 (task 1.3). Module 4 chưa bắt đầu Pha 5.
- **[Không có giảm giá giữa Pha 2 và task 5.4]** Pha 2 bỏ coupon, giảm hội viên và điểm khỏi đặt sân; voucher mới bật ở task 5.4, sau Pha 5 của module 4 (D28, D29).
  → Prod là dữ liệu demo. Nếu có khách thật trước task 5.4 thì làm Pha 3, Pha 4, task 5.1–5.3, Pha 5 của module 4 và task 5.4–5.5 liền sau Pha 2.
- **[Lưới theo `slot_duration` còn tới Pha 5 của module 4]** Khách chưa chọn được mốc :30 trên sân 60 phút cho tới merge task 5.1 của module 4 (D29).
  → Đây là hành vi đang chạy; engine đã tính đúng slot đi qua mốc đổi giá.
- **[Chuyển khoản tới khi voucher vừa hỏng]** Khách đã trả tiền nhưng đơn không được xác nhận (D11).
  → Chuyển khoản được ghi là ngoại lệ thanh toán để owner hoàn; khách được báo. Chỉ xảy ra khi owner dừng hoặc sửa chương trình trong lúc đơn đang chờ.
- **[Khách hội viên mất ưu đãi khi đặt online]** Hệ quả của Q2 = A; điểm chỉ còn được tích.
  → Người dùng đã chọn. Ghi vào Open Questions để quyết có làm đổi điểm ở POS không.
- **[Lượt bị giữ bởi đơn chờ]** Lượt cuối có thể bị một đơn chờ giữ tới 10 phút.
  → Hạn thanh toán ngắn; màn theo dõi hiện riêng "đang giữ".
- **[Xoá bảng campaign và coupon trên prod]** Không đảo được.
  → Backup trước, kiểm khoá ngoại, người dùng xác nhận trước khi chạy migration trên prod.
- **[Cửa sổ deploy]** Push main tự deploy frontend; backend chờ `glab ci run -b main`.
  → Hẹn giờ với người dùng trước mỗi lần push có cắt hợp đồng API (Pha 2, Pha 4, Pha 5), chạy backend ngay sau push.
- **[Xung đột với session module 2 và 4]** Cùng đụng `CourtController`, `OrdinaryCheckoutService`, form đặt sân.
  → Mỗi pha một worktree; ranh giới file ghi trong tasks; fetch trước khi merge.

## Migration Plan

1. **Pha 2:**
   - Tạo bảng giá, phiên bản, khoảng giờ, liên kết.
   - Chạy chuyển dữ liệu: mỗi cơ sở gộp các sân có cùng nội dung giá thành một bảng thường; liên kết bắt đầu lúc chạy.
   - Ngày lễ: với từng sân, lấy các ngày mà rule `holiday/date_exception` chạm tới sau khi xếp hạng phạm vi cũ. Mỗi ngày đó được ghi cả ngày theo đúng giá resolver cũ tính; giờ ngoài khung lễ giữ giá peak/weekly/base cũ, vì D4 cấm bù. Các sân có cùng nội dung ngày lễ dùng chung một bảng lễ, mỗi sân một liên kết lễ.
   - Script so sánh chạy trên 14 ngày tới và trên mọi ngày có trong bảng lễ đã chuyển; kiểm độ phủ trên các liên kết đã chuyển.
   - Chạy script so sánh; lệch thì dừng, không đổi gì.
   - Xoá `price_rules`, `court_price_slots` trong migration kế tiếp của cùng merge, sau khi so sánh đạt.
   - Điều kiện: Pha 2 của module 4 (`scopeBlocksInventory`) đã merge (đạt từ `851c173e`).
2. **Pha 3:** chỉ thêm cột và sự kiện. Deploy theo thứ tự nào cũng được.
3. **Pha 4:**
   - Tạo bảng chương trình, sân áp dụng, mã voucher và lượt dùng (`voucher_uses`).
   - Cho hết hạn các đơn chờ và đơn QR POS còn giữ coupon, nhả coupon của chúng.
   - Backup prod; kiểm khoá ngoại; người dùng xác nhận; xoá bảng campaign và coupon. Reseed voucher demo.
4. **Pha 5, Pha 6:** không đổi schema; lượt giữ voucher của đơn nằm ở `voucher_uses`.
5. **Điểm cũ:** `releaseReservedPoints` giữ tới khi mọi đơn chờ còn giữ điểm từ trước Pha 2 đã hết hạn; sau đó xoá trong Pha 4.
6. **Rollback:**
   - Pha 2 và Pha 4 xoá bảng cũ nên chỉ đảo được bằng bản backup.
   - Các pha khác revert code là đủ.

## Open Questions

- Điểm chỉ còn được tích sau Q2 = A, vì POS không có luồng đổi điểm đang chạy. Có làm đổi điểm ở POS trong một change riêng không? Không chặn change này.
