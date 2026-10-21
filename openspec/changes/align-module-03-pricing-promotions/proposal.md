## Why

Report Module 03 (`module-03-report.md`: FR-53..FR-68, BR-49..BR-71, 8 bước, UC-3.1..UC-3.17) mô tả cách chủ cơ sở quản lý giá và khuyến mãi:

- Bảng giá thuộc cơ sở, loại thông thường hoặc ngày lễ, có thời điểm hiệu lực, áp cho nhiều sân cùng cơ sở. Mỗi giờ mở nhận đặt có đúng một đơn giá.
- Tiền thuê tính theo phút qua các khoảng giá: `T = ⌊Σ dᵢ·pᵢ / 60⌋`.
- Chương trình khuyến mãi thuộc một cơ sở, phát voucher công khai (tự chọn voucher giảm nhiều nhất) hoặc voucher riêng (N mã dùng một lần, kèm QR). Mỗi đơn tối đa một voucher, `P = T − D`.
- Chỉ chủ cơ sở quản lý giá và khuyến mãi.

Đối chiếu code `9dddc773` (`module-03-gap-audit.md`; vài dẫn chứng ghi theo `311790f3`, tổ tiên của `9dddc773`) cho kết quả:

| Kết quả | Số mã |
|---|---|
| Khớp | 3 |
| Khớp một phần | 29 |
| Thiếu hẳn | 4 |
| Lệch có chủ đích theo spec cũ | 3 |

Khác biệt mô hình lớn nhất:
- **Giá:** code có `PriceRule` theo phạm vi tổ chức/cơ sở/môn/sân với tầng `date_exception/holiday > peak > weekly > base`, cộng `CourtPriceSlot` theo từng giờ. Report có bảng giá thuộc cơ sở, phủ kín, không chồng lấn, không giá dự phòng.
- **Tính tiền:** code tính theo slot 1 giờ. Report tính theo phút trên khoảng `[start, end)`.
- **Khuyến mãi:** code có campaign và coupon cấp tổ chức, nhiều lượt, nhóm khách, luật kết hợp với hội viên và điểm. Report có voucher công khai và voucher riêng của một cơ sở.

Pha 0 (merge `4c8ad0a8`) đã vá 8 lỗi prod:
- đọc chéo tổ chức cấu hình giá;
- rule giá trỏ tới cơ sở hoặc sân của tổ chức khác;
- "bộ quy tắc mẫu" ghi giá bịa;
- giá riêng của sân thua rule rộng hơn;
- slot đi qua mốc giá rơi xuống giá base;
- ranh giới nửa đêm;
- thời hạn khuyến mãi lệch 7 giờ;
- sửa được mã và hình thức giảm của voucher đã phát hành.

Change này đặc tả phần còn lại để Pha 2–7 bám report. Report thay các quyết định cũ:
- "Immutable price quotes": rule giá theo phạm vi và tầng ưu tiên, giá base, bù giá ngày lễ bằng giá thường.
- "Complete tenant promotion lifecycle" (D25 cũ): campaign cấp tổ chức, nhóm khách, luật kết hợp.
- "Authoritative ordinary checkout and promotion result" và "Authoritative loyalty conversion and preview" (FR-PRM-09/10, D27 cũ): giảm hội viên và dùng điểm khi đặt sân.

Người dùng chốt ngày 2026-10-21:
- Q2 = A: bỏ giảm hội viên và điểm khỏi đặt sân, giữ hội viên ở POS.
- Q3 theo khuyến nghị: giá gợi ý của buổi chơi do chủ sân tổ chức lấy từ bảng giá; giá bán lại tối đa là số tiền khách thực trả sau giảm.

## What Changes

- **Bảng giá thuộc cơ sở (D1, D15; FR-53, FR-54, BR-50, BR-51, BR-54).**
  - **BREAKING:** bỏ `PriceRule` và `CourtPriceSlot`.
  - Bảng giá có tên, loại `normal / holiday` (không đổi được) và trạng thái `active / retired`. Không xoá bảng giá.
  - Khoảng giờ `[start, end)` theo giờ cơ sở, `23:59` là hết ngày, không chồng lấn trong cùng ngày. Đơn giá nguyên > 0.
- **Phiên bản và thời điểm hiệu lực (D1, D6; FR-56, BR-56).**
  - Lưu nội dung là công bố một phiên bản; không có nháp. Phiên bản bất biến, có `published_at` và `effective_at`.
  - Giá cho giờ chơi t của đơn xác nhận lúc c: phiên bản công bố muộn nhất trong các phiên bản công bố trước c và có hiệu lực trước t.
  - Công bố cần quyền kinh doanh còn hạn và phải qua kiểm độ phủ.
- **Liên kết sân – bảng giá theo thời gian (D2; FR-55, FR-57, BR-50, BR-57).**
  - Mỗi sân tại mỗi thời điểm có tối đa một liên kết thường và một liên kết lễ. Bảng phải cùng cơ sở.
  - Thời điểm chuyển mặc định là bây giờ, không được ở quá khứ. Thay đổi mới nhất thắng: liên kết cùng loại đã lên lịch sau thời điểm chuyển bị xoá.
  - Màn sân (module 2) và màn giá dùng chung liên kết.
  - Bảng đã ngừng không được gán mới và không nhận phiên bản mới; sân đang dùng giữ giá tới khi chuyển.
- **Kiểm độ phủ (D3, D4; BR-52, BR-53).**
  - 422 `PRICE_COVERAGE_INCOMPLETE` liệt kê sân, thứ hoặc ngày, và khoảng thiếu.
  - Chạy khi công bố và khi đổi liên kết. Module 2 dùng cùng service khi mở và resume sân, và đưa khoảng thiếu vào 422 gộp của họ. Thêm `GET /admin/courts/{court}/price-coverage`.
  - Ngày lễ không bù giá thường.
- **Tính tiền theo phút (D5, D17; FR-58, BR-55, BR-67).**
  - Một engine `RentalPriceCalculator::quote` dùng chung cho báo giá, availability, giữ chỗ, checkout và kiểm lại đơn chờ.
  - Phút không có giá → 422; bỏ mọi giá dự phòng.
  - Snapshot từng phần giá kèm phiên bản, và voucher kèm hạn.
- **Sự kiện giá có hiệu lực (D16).** `CourtPricesTookEffect` phát lúc công bố và lúc đổi liên kết, không phát khi tới `effective_at`.
- **Hiển thị giá công khai (D18; FR-58; FR-69, FR-70 do module 4 giao).**
  - Viết lại `AvailabilityController`: chỉ mở giờ có giá, so chồng lấn nửa mở, hold quá hạn là trống. Module 4 bật mốc 30 phút cho mọi sân và panel trang chủ trong merge task 5.1 của họ; tới đó lưới theo `slot_duration` (D29).
  - Giá "từ" trên danh mục; không có giá thì không hiển thị. Bỏ ước tính làm tròn lên.
- **Chương trình khuyến mãi theo cơ sở (D7, D8, D9, D13, D14; FR-59..FR-63, BR-58..BR-62).**
  - **BREAKING:** bỏ campaign và coupon cấp tổ chức, nhóm khách và luật kết hợp.
  - Chương trình thuộc một cơ sở và một tập sân; voucher công khai (tổng lượt) hoặc riêng (N mã một lần, QR là deep link tới trang đặt sân `/courts/{court_slug}/schedule?voucher=<code>`).
  - Giảm phần trăm (> 0 và ≤ 100, tối đa hai chữ số thập phân; D27) hoặc số tiền cố định. Mức giảm tối đa chỉ cho phần trăm. Tiền thuê tối thiểu tuỳ chọn.
  - Phát hành ngay khi tạo; trạng thái "Sắp diễn ra / Đang diễn ra / Đã kết thúc / Đã dừng".
  - Loại voucher, hình thức giảm, mã riêng và số mã không đổi được. Dừng có bước xác nhận và không mở lại được.
- **Cập nhật hoặc dừng khi có đơn chờ (D19; FR-62, FR-63, BR-71).** Đơn chờ thanh toán mất voucher hoặc đổi tiền giảm thì hết hạn và khách được báo.
- **Chọn voucher khi đặt sân (D10, D20; FR-64..FR-66, BR-63..BR-66, BR-68).**
  - `GET /venues/{venue}/vouchers` chỉ trả voucher công khai hợp lệ, kèm D.
  - Tự chọn D lớn nhất; hoà thì hết hạn sớm hơn, rồi tạo trước.
  - Nhập mã hoặc mở QR; mã không hợp lệ có lý do cụ thể.
  - Checkout áp đúng voucher được gửi. Không hợp lệ → 422 `VOUCHER_INVALID` kèm lý do và số tiền khi không có voucher.
- **Giữ, tiêu thụ và theo dõi voucher (D11; FR-67, FR-68, BR-69, BR-70).**
  - Checkout giữ một lượt khi tạo đơn (cùng transaction) và tiêu ngay với đơn xác nhận ngay khi tạo (0đ, ví legacy); module 4 tiêu khi xác nhận về sau và nhả đúng một lần khi đơn chờ kết thúc. `held + used ≤ tổng`.
  - Lúc xác nhận kiểm lại voucher theo thời điểm nhận chuyển khoản. Không còn áp được thì đơn hết hạn, chuyển khoản thành ngoại lệ thanh toán và khách được báo.
  - Đơn đã xác nhận bị huỷ, chuyển sân hay đổi giờ không trả lượt.
  - Theo dõi tổng, đã dùng, đang giữ, còn lại; danh sách mã riêng; các đơn đã dùng.
- **Phạm vi voucher (D12; BR-58).** POS và đặt định kỳ gửi mã → 422, không âm thầm bỏ. Bán lại không áp voucher.
- **Bỏ giảm hội viên và điểm khỏi đặt sân (D21; Q2 = A).**
  - **BREAKING:** bỏ `/api/loyalty/preview`, ô chọn điểm, ô coupon và việc giữ điểm trong checkout đặt sân, từ Pha 2 (D28). Request đặt sân hoặc đặt định kỳ còn gửi `coupon_code` hay `loyalty_points` nhận 422.
  - Hội viên và tích điểm vẫn ở POS. Không luồng nào còn đổi điểm (D21 ghi lý do).
  - Ví legacy vẫn trả số tiền phải trả, không còn điểm.
  - Đơn cũ giữ các khoản giảm đã ghi.
- **Buổi chơi và bán lại (D23, D24; Q3).**
  - Giá gợi ý của buổi chơi là đơn giá theo bảng giá tại giờ bắt đầu. Tiền sân `⌊phút × giá / 60⌋`, thay cho làm tròn nửa lên.
  - Giá bán lại tối đa là số tiền thực trả sau mọi khoản giảm; với đơn nhiều slot cũ thì chia theo tỷ lệ tiền thuê.
- **Quyền (D25; BR-49).** Chỉ owner quản lý bảng giá và khuyến mãi. Staff nhận 403 và không thấy menu.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `reservations-and-pricing`:
  - Thêm "Venue price tables", "Price table versions and effective time", "Court price table links", "Price coverage check", "Per-minute rental calculation", "Public price display", "Venue promotion programs", "Voucher eligibility and discount", "Voucher choice during booking", "Atomic voucher use", "Voucher usage tracking", "Voucher scope", "Court quotes from price tables", "Authoritative ordinary checkout and voucher result".
  - Sửa "Home availability identifies its selected court and date": slot là mốc 30 phút.
  - Bỏ "Immutable price quotes", "Authoritative ordinary checkout and promotion result", "Complete tenant promotion lifecycle". Hai requirement đầu được thay bằng requirement tên mới vì OpenSpec không cho khối MODIFIED bỏ scenario cũ (D26).
- `organization-access`: sửa "Role-based owner operations", dựng trên text của `align-module-02-venue-court`: giá và khuyến mãi chỉ owner.
- `point-of-sale-and-loyalty`:
  - Thêm "Versioned POS memberships and point earning".
  - Sửa "Authoritative POS receipt and identity": POS không nhận voucher.
  - Bỏ "Versioned tenant memberships and loyalty", "Authoritative loyalty conversion and preview", "Source-bound idempotent redemption".
- `owner-hosted-drop-in-sessions`: sửa "Post-session cost capture" (giá gợi ý từ bảng giá) và "Court and shuttlecock only equal calculation" (bỏ phần thập phân).
- `advanced-booking-marketplace`: sửa "Peer-to-peer marketplace transfer", dựng trên text của `direct-owner-cutover`: giá tối đa là số tiền thực trả.
- `api-alignment`: sửa "Bilingual actionable error dialogs": thêm màn giá và khuyến mãi, checkout đặt sân, `PRICE_COVERAGE_INCOMPLETE` và `VOUCHER_INVALID`.

## Impact

- **Backend theo pha** (`module-03-report-alignment.plan.md`):
  - Pha 2: bảng `price_tables`, `price_table_versions`, `price_table_intervals`, `court_price_table_links`; engine theo phút; service độ phủ; chuyển dữ liệu giá; viết lại `AvailabilityController`; giá "từ"; xoá `PriceRule`, `CourtPriceSlot`, `ManageCourtPrices`, `PriceTierResolver` và mọi nơi đọc chúng; `PriceQuoteService` tính bằng engine và giữ hợp đồng `slots` của `PriceQuote` tới task 5.1 của module 4, từ đó module 4 đổi hoặc bỏ nó (D29); `CourtPriceRuleService` giữ tên lớp, đổi sang kiểm liên kết; bỏ coupon, giảm hội viên và điểm khỏi đặt sân và đặt định kỳ (D28); `MatchService` lấy giá từ engine. Cần Pha 2 của module 4 đã merge.
  - Pha 3: công bố phiên bản có `effective_at`, sự kiện `CourtPricesTookEffect`.
  - Pha 4: `promotion_programs`, `promotion_program_courts`, `voucher_codes`, `voucher_uses`; gỡ coupon khỏi POS; xoá campaign, coupon và các chỗ còn gọi tới chúng.
  - Pha 5, hai lần merge (D29): trước Pha 5 của module 4 là phần voucher của engine, `VoucherUsageService` (`hold / release / consume`), danh sách và kiểm voucher; sau task 5.1, 5.5, 5.7 của module 4 là checkout áp đúng voucher, giữ lượt, tiêu lượt cho đơn xác nhận ngay khi tạo, và FE chọn voucher.
  - Pha 6: kiểm lại đơn chờ khi chương trình đổi; theo dõi; trần giá bán lại.
- **API:**
  - Thêm owner `GET/POST /admin/venues/{venue}/price-tables`, `GET /admin/price-tables/{table}`, `POST /admin/price-tables/{table}/versions` (không có PATCH: sửa giá là công bố phiên bản mới), áp/kết thúc liên kết, ngừng bảng, `GET /admin/courts/{court}/price-coverage`, CRUD chương trình (không có xoá), dừng chương trình, mã và QR, lịch sử dùng.
  - Thêm `GET /venues/{venue}/vouchers` và kiểm mã riêng.
  - Bỏ `price-rules`, `pricing/calendar`, `/admin/courts/{id}/prices*`, các route campaign/coupon, `loyalty/preview`.
  - Mã lỗi mới: `PRICE_COVERAGE_INCOMPLETE`, `VOUCHER_INVALID`.
- **Frontend:**
  - Màn bảng giá theo cơ sở (danh sách, chi tiết, tạo, phiên bản mới, áp sân, ngừng) thay `PricingCalendarView`, `CourtPricingModal`, `useAdminPricingMatrix`.
  - Màn chương trình khuyến mãi (danh sách, chi tiết, tạo, sửa, dừng, mã và QR, lịch sử dùng).
  - Đặt sân: chọn voucher, nhập mã, mở `?voucher=`; tóm tắt T, voucher, D, P. Bỏ `LoyaltyPointSelector`, `CouponCodeField` và `CouponManagerModal`.
  - QR mã riêng vẽ bằng gói `qrcode` đã cài.
  - Giá "từ" trên danh mục; bản dịch vi/en.
- **Dữ liệu:**
  - Chuyển `PriceRule` và `CourtPriceSlot` sang bảng giá, có script so sánh 14 ngày tới và mọi ngày có trong bảng lễ đã chuyển, kèm kiểm độ phủ; lệch hoặc thiếu thì dừng.
  - Không chuyển campaign và coupon (prod là dữ liệu demo). Backup prod rồi xoá bảng cũ, người dùng xác nhận trước khi chạy trên prod; reseed voucher demo.
  - Snapshot của đơn cũ giữ nguyên.
- **Phối hợp:**
  - Thứ tự archive: `direct-owner-cutover` → `align-module-02-venue-court` → change này → `align-module-04-booking`.
  - Module 4: bỏ khối MODIFIED "Immutable price quotes"; sự kiện giá chỉ phát lúc công bố và lúc đổi liên kết; lý do nhả `voucher_changed`; chữ ký engine (thêm `$heldBy`) và service voucher mới; module 3 khoá chương trình trước khi tính giá, gọi `hold`, và `consume` cho đơn xác nhận ngay khi tạo, module 4 gọi `consume` ở các lần xác nhận sau và `release` ở mọi đường nhả, và xử lý `VoucherNoLongerApplies`; cổng Pha 5 và lưới 30 phút theo D29; thứ tự kiểm `VOUCHER_INVALID` → `PRICE_CHANGED` → `PAYMENT_WINDOW_CLOSED`.
  - Module 2: "Operator handling of affected bookings" (thân và scenario) đổi "coupon uses and points" thành "voucher use"; module 2 nối service độ phủ vào mở sân, resume sân và điều kiện đặt sân.
- **Non-goals:**
  - Lịch phục vụ, ngày nghỉ và điều kiện mở sân: module 2.
  - Form đặt sân, hạn thanh toán, xác nhận và huỷ đơn chờ: module 4.
  - Đổi điểm ở POS: chưa có luồng đang chạy; cần change riêng nếu muốn.
  - Thư viện quét QR trong app (D9).
