# Truy vết report Module 03

## Nguồn và cách đọc

- Nguồn: `.claude/plans/module-03-report.md`, bản markitdown của `sub-documents (1).pdf`. Gồm FR-53..FR-68 và BR-49..BR-71, tổng 39 mã, cùng UC-3.1..UC-3.17.
- Bằng chứng hiện trạng: `.claude/plans/module-03-gap-audit.md`, đối chiếu code tại `9dddc773` (vài dẫn chứng ghi theo `311790f3`, tổ tiên của `9dddc773`). Bảng trạng thái lấy theo mục 1 của `.claude/plans/module-03-report-alignment.plan.md`.
- Trạng thái là kết quả đọc code, không phải kết quả chạy test.
  - **khớp**: code đã làm đúng report.
  - **khớp một phần**: có làm, còn thiếu hoặc sai một phần.
  - **thiếu**: chưa có.
  - **lệch có chủ đích**: code theo quyết định cũ (archive hoặc `direct-owner-cutover`); report này thay quyết định đó.
- Cột "Pha 0" ghi mục sửa trong commit `0b3c1a6f` (merge `4c8ad0a8`), nếu có:
  - 0.1: đọc cấu hình giá chỉ cho owner; `court_id` của tổ chức khác trả 403.
  - 0.2: rule giá phải trỏ tới cơ sở và sân của tổ chức; sân phải thuộc cơ sở.
  - 0.3: xoá "bộ quy tắc mẫu" ghi giá bịa.
  - 0.4: giá riêng của sân thắng rule weekly rộng hơn.
  - 0.5: slot đi qua mốc giá bị từ chối, không rơi xuống giá base.
  - 0.6: ranh giới nửa đêm, `23:59` = phút 1440.
  - 0.7: thời hạn khuyến mãi lưu đúng giờ ứng dụng; kết thúc phải sau bắt đầu.
  - 0.8: khoá mã và hình thức giảm sau khi phát hành.
- Cột "Task" trỏ tới `tasks.md`. Cột "Test dự kiến" là test sẽ viết hoặc viết lại. Mọi dòng đang ở trạng thái **chưa nghiệm thu** cho tới task 7.3.
- Tên capability viết tắt trong bảng:
  - `rp`: `reservations-and-pricing`.
  - `oa`: `organization-access`.
  - `pos`: `point-of-sale-and-loyalty`.
  - `drop`: `owner-hosted-drop-in-sessions`.
  - `abm`: `advanced-booking-marketplace`.
  - `api`: `api-alignment`.

## Functional requirements (FR-53..FR-68)

| ID | Report yêu cầu | Trạng thái tại `9dddc773` | Pha 0 | Requirement | Task | Test dự kiến |
| --- | --- | --- | --- | --- | --- | --- |
| FR-53 | Xem bảng giá của cơ sở được quản lý: loại, ngày áp dụng, khoảng giờ, đơn giá, thời gian hiệu lực, các sân đang áp dụng. | khớp một phần | 0.1 | `rp`: Venue price tables; `rp`: Court price table links; `oa`: Role-based owner operations | 2.2, 2.12 | `PriceTableManagementTest`; vitest màn bảng giá |
| FR-54 | Tạo bảng giá thường theo thứ hoặc lễ theo ngày cụ thể, với khoảng giờ, đơn giá VNĐ/giờ, thời gian hiệu lực. Kiểm thông tin bắt buộc, đơn giá, không chồng lấn. | khớp một phần | 0.3 | `rp`: Venue price tables; `rp`: Price table versions and effective time | 2.1, 2.2 | `PriceTableSchemaTest`; `PriceTableManagementTest` |
| FR-55 | Áp bảng giá cho một hoặc nhiều sân cùng cơ sở. Kiểm phạm vi sân, hiệu lực, đủ và không trùng giá. Liên kết đồng bộ với màn sân của module 2. | khớp một phần | 0.2 | `rp`: Court price table links; `rp`: Price coverage check | 2.4, 2.5 | `PriceTableLinkActionTest`; `PriceCoverageTest`. Mở và resume sân do module 2 nối độ phủ (task 3.3–3.5 của họ). |
| FR-56 | Cập nhật bảng giá và thời điểm giá mới có hiệu lực. Lưu giá đã dùng; không đổi đơn đã xác nhận. | khớp một phần | – | `rp`: Price table versions and effective time; `rp`: Court quotes from price tables | 3.1, 3.2 | `PriceTableVersionTest`; `CourtPricesTookEffectTest` |
| FR-57 | Ngừng bảng giá để không gán mới. Sân đang dùng chuyển sang bảng thay thế; không kết thúc nếu làm thiếu giá. Giữ bảng đã dùng để tra cứu. | khớp một phần | – | `rp`: Court price table links; `rp`: Price coverage check | 2.5 | `PriceTableLinkActionTest` |
| FR-58 | Xác định giá theo sân, ngày, giờ; ưu tiên bảng lễ. Tính theo thời lượng ở từng khoảng giá, cộng rồi bỏ phần thập phân. Hiện tiền thuê trước khi xác nhận. | khớp một phần | 0.4, 0.5, 0.6 | `rp`: Per-minute rental calculation; `rp`: Public price display; `rp`: Court quotes from price tables | 2.3, 2.7, 2.8 | `RentalPriceCalculatorTest`; `AvailabilityFromPriceTablesTest`; `PublicPriceDisplayTest` |
| FR-59 | Xem chương trình của cơ sở: loại voucher, hình thức và giá trị giảm, điều kiện, thời hạn, sân, trạng thái, số lượng sử dụng. | khớp một phần | – | `rp`: Venue promotion programs; `rp`: Voucher usage tracking | 4.3, 4.7, 6.3 | `PromotionProgramManagementTest`; `VoucherUsageTrackingTest` |
| FR-60 | Thiết lập voucher công khai: phần trăm hoặc số tiền cố định, thời hạn, sân, tổng lượt; tiền thuê tối thiểu và mức giảm tối đa tuỳ chọn. Phát hành cho khách đủ điều kiện. | khớp một phần | – | `rp`: Venue promotion programs | 4.1, 4.2, 4.7 | `PromotionProgramManagementTest` |
| FR-61 | Thiết lập voucher riêng: sinh mã không trùng cùng QR, mỗi mã dùng một lần. Owner xem và lấy mã, QR để gửi khách. | thiếu | – | `rp`: Venue promotion programs; `rp`: Atomic voucher use | 4.2, 4.3, 4.7 | `PromotionProgramManagementTest`; vitest màn chương trình |
| FR-62 | Cập nhật chương trình. Điều kiện mới áp cho đơn chưa xác nhận; không đổi tiền giảm hay lịch sử của đơn đã xác nhận. | khớp một phần | 0.7, 0.8 | `rp`: Venue promotion programs; `rp`: Atomic voucher use | 4.4, 6.2 | `PromotionProgramUpdateTest`; `PromotionProgramChangePendingTest` |
| FR-63 | Ngừng chương trình. Voucher công khai không hiện nữa; mã riêng chưa dùng và voucher đã chọn chưa xác nhận không dùng được. Giữ thông tin và lịch sử. | khớp một phần | – | `rp`: Venue promotion programs; `rp`: Atomic voucher use | 4.4, 6.2 | `PromotionProgramUpdateTest`; `PromotionProgramChangePendingTest` |
| FR-64 | Hiện voucher công khai còn hiệu lực, còn lượt, đúng điều kiện. Tự chọn tiền giảm lớn nhất; hoà thì hết hạn sớm hơn, rồi tạo trước. Cho chọn voucher khác. | thiếu | – | `rp`: Voucher choice during booking | 5.3, 5.5 | `VoucherChoiceTest`; vitest form đặt sân |
| FR-65 | Nhập mã hoặc quét QR voucher riêng. Kiểm mã, thời hạn, trạng thái, điều kiện; báo khi không hợp lệ. Mã hợp lệ thay voucher đang chọn. | khớp một phần | – | `rp`: Voucher choice during booking; `api`: Bilingual actionable error dialogs | 5.3, 5.5 | `VoucherChoiceTest`; vitest form đặt sân |
| FR-66 | Tính tiền giảm theo phần trăm hoặc cố định, xét trần, không vượt tiền thuê, bỏ phần thập phân. Hiện T, D, P. Tối đa một voucher; tính lại khi đổi sân hoặc giờ. | khớp một phần | – | `rp`: Voucher eligibility and discount; `rp`: Voucher choice during booking | 5.1, 5.5 | `VoucherDiscountTest`; vitest form đặt sân |
| FR-67 | Kiểm lại điều kiện và lượt lúc xác nhận. Chỉ trừ lượt hoặc đánh dấu mã khi xác nhận thành công; chống vượt lượt và dùng trùng mã khi đồng thời. Voucher không còn hợp lệ thì báo. | khớp một phần | – | `rp`: Atomic voucher use; `rp`: Authoritative ordinary checkout and voucher result | 5.2, 5.4, 6.1 | `OrdinaryCheckoutVoucherTest`; `VoucherUsageServiceTest`; `VoucherConfirmRaceTest` (chuyển khoản và ví legacy). Xử lý từng đường xác nhận: test của module 4 (task 1.3). |
| FR-68 | Xem tổng, đã dùng, còn lại của voucher công khai; danh sách và tình trạng mã riêng; tra đơn đã dùng, thời điểm, tiền giảm. Đơn huỷ sau xác nhận vẫn tính đã dùng. | khớp một phần | 0.8 (một phần) | `rp`: Voucher usage tracking | 6.3 | `VoucherUsageTrackingTest` |

## Business rules (BR-49..BR-71)

| ID | Report yêu cầu | Trạng thái tại `9dddc773` | Pha 0 | Requirement | Task | Test dự kiến |
| --- | --- | --- | --- | --- | --- | --- |
| BR-49 | Chỉ owner tạo, cập nhật, áp dụng, ngừng bảng giá và chương trình của cơ sở thuộc tổ chức mình. Nhân viên không được. | khớp một phần | 0.1, 0.2 | `oa`: Role-based owner operations | 2.2, 2.4, 2.5, 2.12, 3.1, 4.2, 4.3, 4.4, 4.7, 6.3 | `PriceTableManagementTest`, `PriceCoverageTest`, `PriceTableLinkActionTest`, `PriceTableVersionTest`, `PromotionProgramManagementTest`, `PromotionProgramUpdateTest`, `VoucherUsageTrackingTest` (staff 403, owner tổ chức khác 403) |
| BR-50 | Bảng giá thuộc một cơ sở, áp được cho nhiều sân cùng cơ sở. Màn sân và màn giá cập nhật cùng một liên kết. | lệch có chủ đích | 0.2 | `rp`: Venue price tables; `rp`: Court price table links | 2.1, 2.5 | `PriceTableLinkActionTest` |
| BR-51 | Bảng giá có tên, loại, ngày áp dụng, khoảng giờ, đơn giá nguyên > 0 VNĐ/giờ, thời điểm hiệu lực. | khớp một phần | – | `rp`: Venue price tables; `rp`: Price table versions and effective time | 2.2, 3.1 | `PriceTableManagementTest` |
| BR-52 | Khoảng giờ cùng ngày không chồng lấn. Mỗi thời điểm mở nhận đặt có đúng một đơn giá. Không mở giờ chưa có giá. | khớp một phần | 0.3, 0.4, 0.5 | `rp`: Venue price tables; `rp`: Price coverage check; `rp`: Public price display; `rp`: Home availability identifies its selected court and date | 2.2, 2.4, 2.7 | `PriceCoverageTest`; `AvailabilityFromPriceTablesTest`. Mốc 30 phút và panel trang chủ: task 5.1 của module 4 (báo ở task 1.3). Điều kiện mở sân và nhận đặt: module 2 (task 3.3–3.5 của họ). |
| BR-53 | Bảng lễ ưu tiên vào ngày lễ và phải phủ toàn bộ giờ mở; không lấy giá thường bù. | lệch có chủ đích | 0.5 (một phần) | `rp`: Price coverage check; `rp`: Per-minute rental calculation; `rp`: Court quotes from price tables | 2.3, 2.4 | `RentalPriceCalculatorTest`; `PriceCoverageTest` |
| BR-54 | Khoảng giờ nửa mở `[start, end)`; hai khoảng liền nhau dùng chung mốc. | khớp một phần | 0.5, 0.6 | `rp`: Venue price tables; `rp`: Per-minute rental calculation | 2.2, 2.3 | `RentalPriceCalculatorTest` |
| BR-55 | Giờ chọn theo mốc 30 phút. `T = ⌊Σ dᵢ·pᵢ / 60⌋`. | khớp một phần | 0.5 | `rp`: Per-minute rental calculation | 2.3 | `RentalPriceCalculatorTest` (15:30–17:00 = 190.000). Lưới 30 phút thuộc module 4 và bật cùng task 5.1 của họ (D29). |
| BR-56 | Giá mới chỉ áp cho đơn chưa xác nhận có giờ chơi trong hiệu lực. Đơn đã xác nhận giữ giá. Giá đổi trước khi xác nhận thì kiểm lại. | khớp một phần | – | `rp`: Price table versions and effective time; `rp`: Court quotes from price tables | 3.1, 3.2 | `PriceTableVersionTest`; `CourtPricesTookEffectTest` |
| BR-57 | Bảng đã ngừng không gán mới. Sân đang dùng giữ giá tới khi chuyển. Không kết thúc nếu thiếu giá. Giữ bảng đã dùng để tra cứu. | khớp một phần | – | `rp`: Court price table links; `rp`: Price coverage check | 2.5 | `PriceTableLinkActionTest` |
| BR-58 | Chương trình thuộc một cơ sở và xác định sân áp dụng. Voucher chỉ dùng cho đơn đặt sân trong phạm vi. | lệch có chủ đích | – | `rp`: Venue promotion programs; `rp`: Voucher scope; `pos`: Authoritative POS receipt and identity; `abm`: Peer-to-peer marketplace transfer | 2.10, 4.2, 4.5, 5.4, 6.4 | `PromotionProgramManagementTest`; `PosVoucherRejectedTest`; `RecurringVoucherRejectedTest` |
| BR-59 | Chương trình có tên, loại, hình thức, giá trị, bắt đầu, kết thúc sau bắt đầu, sân, tổng lượt hoặc số mã nguyên > 0. | khớp một phần | 0.7 | `rp`: Venue promotion programs | 4.2 | `PromotionProgramManagementTest` |
| BR-60 | Một hình thức giảm. Phần trăm > 0 và ≤ 100, số tiền cố định nguyên > 0. Tiền thuê tối thiểu và mức giảm tối đa (chỉ phần trăm) tuỳ chọn, nguyên > 0. | khớp một phần | – | `rp`: Venue promotion programs | 4.2 | `PromotionProgramManagementTest` (12,5% hợp lệ, 12,345% → 422; design D27) |
| BR-61 | Lượt dùng chung mọi khách. Chỉ hiện voucher còn hiệu lực, còn lượt, đúng điều kiện. Mỗi đơn xác nhận có voucher trừ một lượt. | thiếu | – | `rp`: Voucher choice during booking; `rp`: Atomic voucher use | 5.2, 5.3 | `VoucherChoiceTest`; `VoucherUsageServiceTest` |
| BR-62 | Mã tự sinh, không trùng, dùng một lần, không gắn tài khoản. Không có trong danh sách công khai. QR và mã ký tự là cùng một voucher. | khớp một phần | 0.8 | `rp`: Venue promotion programs; `rp`: Voucher choice during booking | 4.2, 5.3 | `PromotionProgramManagementTest`; `VoucherChoiceTest` |
| BR-63 | Hợp lệ khi chương trình chưa dừng, còn lượt hoặc mã chưa dùng, đúng sân, đạt tiền thuê tối thiểu. Thời hạn theo thời điểm xác nhận, không theo ngày chơi. | khớp một phần | 0.7 | `rp`: Voucher eligibility and discount | 5.1, 5.2 | `VoucherDiscountTest`; `VoucherUsageServiceTest` (kiểm lại lúc xác nhận theo lúc nhận chuyển khoản, design D11) |
| BR-64 | Mỗi đơn tối đa một voucher, không cộng dồn. Chọn voucher khác thì thay voucher đang chọn. | khớp | – | `rp`: Voucher choice during booking; `rp`: Authoritative ordinary checkout and voucher result | 2.10, 5.4 | `OrdinaryCheckoutVoucherTest`; `CheckoutWithoutLoyaltyTest`. Q2 = A bỏ thêm giảm hội viên và điểm khỏi đặt sân. |
| BR-65 | Tự chọn voucher công khai có tiền giảm lớn nhất sau khi bỏ phần thập phân; hoà thì hết hạn sớm hơn, rồi tạo trước. | thiếu | – | `rp`: Voucher choice during booking | 5.3 | `VoucherChoiceTest` |
| BR-66 | Công thức D cho phần trăm có trần, không trần, cố định; `P = T − D`. | khớp một phần | – | `rp`: Voucher eligibility and discount; `rp`: Authoritative ordinary checkout and voucher result | 2.10, 5.1 | `VoucherDiscountTest`; `CheckoutWithoutLoyaltyTest` |
| BR-67 | Bỏ phần thập phân, không làm tròn lên. Cộng các khoảng giá trước khi bỏ; tiền phải trả = tiền thuê đã xử lý − tiền giảm đã xử lý. | khớp một phần | – | `rp`: Per-minute rental calculation; `rp`: Voucher eligibility and discount; `drop`: Court and shuttlecock only equal calculation | 2.3, 2.11, 5.1 | `RentalPriceCalculatorTest`; `DropInDefaultRateTest`; `VoucherDiscountTest` |
| BR-68 | Đổi sân hoặc giờ thì tính lại và bỏ voucher không hợp lệ kèm thông báo. Lúc xác nhận kiểm lại giá, điều kiện, lượt; không tự xác nhận khi có thay đổi. | khớp một phần | – | `rp`: Voucher choice during booking; `rp`: Authoritative ordinary checkout and voucher result; `api`: Bilingual actionable error dialogs; `rp`: Atomic voucher use | 5.2, 5.4, 5.5 | `OrdinaryCheckoutVoucherTest`; `VoucherUsageServiceTest`; vitest form đặt sân |
| BR-69 | Chọn, nhập, quét không giữ và không trừ lượt. Ghi nhận khi xác nhận; xác nhận và trừ lượt nguyên tử; đồng thời chỉ một đơn thắng. | khớp một phần | – | `rp`: Atomic voucher use | 5.2 | `VoucherUsageServiceTest`. Lượt được giữ khi tạo đơn chờ thanh toán (design D11). |
| BR-70 | Huỷ đơn đã xác nhận không trả voucher. Chuyển sân, đổi giờ, huỷ do sự cố không tạo thêm lượt. | khớp | – | `rp`: Atomic voucher use; `rp`: Voucher usage tracking | 6.1; task 4.8–4.9 của module 2 nếu lệnh tới sau 6.1 | `VoucherNoRefundTest`; test lệnh 4.8–4.9 của module 2 nếu lệnh tới sau 6.1 |
| BR-71 | Cập nhật hoặc ngừng không đổi tiền giảm của đơn đã xác nhận. Chương trình đã ngừng không áp cho đơn mới, kể cả mã chưa dùng và voucher đã chọn. Giữ thông tin và lịch sử. | khớp | 0.8 | `rp`: Venue promotion programs; `rp`: Atomic voucher use | 4.4, 5.2, 6.2 | `PromotionProgramUpdateTest`; `PromotionProgramChangePendingTest` |

## Use case

| UC | Tên | FR | Ghi chú |
| --- | --- | --- | --- |
| UC-3.1 | Xem danh sách bảng giá | FR-53 | Pha 2 |
| UC-3.2 | Xem chi tiết bảng giá | FR-53 | Pha 2 |
| UC-3.3 | Tạo bảng giá thuê sân | FR-54 | Pha 2 |
| UC-3.4 | Lựa chọn sân áp dụng bảng giá | FR-55 | Pha 2 |
| UC-3.5 | Cập nhật bảng giá thuê sân | FR-56 | Pha 3 |
| UC-3.6 | Ngừng áp dụng bảng giá | FR-57 | Pha 2 |
| UC-3.7 | Xem tiền thuê sân dự kiến | FR-58 | Pha 2 |
| UC-3.8 | Xem danh sách chương trình khuyến mãi | FR-59 | Pha 4 |
| UC-3.9 | Xem chi tiết chương trình khuyến mãi | FR-59 | Pha 4, Pha 6 (số lượng dùng) |
| UC-3.10 | Thiết lập và phát hành voucher công khai | FR-60 | Pha 4 |
| UC-3.11 | Thiết lập và phát hành voucher riêng | FR-61 | Pha 4 |
| UC-3.12 | Cập nhật chương trình khuyến mãi | FR-62 | Pha 4, Pha 6 (đơn chờ) |
| UC-3.13 | Ngừng chương trình khuyến mãi | FR-63 | Pha 4, Pha 6 (đơn chờ) |
| UC-3.14 | Xem và lựa chọn voucher công khai | FR-64, FR-66 | Pha 5 |
| UC-3.15 | Áp dụng voucher riêng bằng mã hoặc QR | FR-65, FR-66 | Pha 5 |
| UC-3.16 | Áp dụng voucher khi xác nhận đơn đặt sân | FR-67 | Pha 5, Pha 6 (cần module 4 nối `consume`) |
| UC-3.17 | Theo dõi lịch sử sử dụng | FR-68 | Pha 6 |

## Mã cũ bị thay

Archive `2026-09-27-align-report-and-direct-owner-payments` (`report-traceability.md`, dòng 415–426) đã nghiệm thu các mã FR-PRM theo mô hình cũ. Report Module 03 thay chúng như sau:

| Mã cũ | Nội dung cũ | Thay bằng |
| --- | --- | --- |
| FR-PRM-01..05 | Bảng giá, quy tắc giá theo thời gian, hiệu lực và ưu tiên, xác định giá, lưu giá lúc đặt | FR-53..FR-58: bảng giá thuộc cơ sở, phiên bản, liên kết, độ phủ, engine theo phút (design D1–D6) |
| FR-PRM-06..08 | Mã giảm giá, chương trình khuyến mãi, điều kiện ưu đãi | FR-59..FR-68: chương trình theo cơ sở, voucher công khai và riêng (design D7–D12, D22) |
| FR-PRM-09..11 | Chương trình thành viên, quyền lợi, điểm tích luỹ | Chỉ còn ở POS; đặt sân không giảm hội viên và không dùng điểm (design D21, Q2 = A) |
| FR-PRM-12 | Số tiền cuối cùng | `P = T − D` (BR-66, BR-67) |

## Đồng bộ spec ngoài module

- "Role-based owner operations" dựng trên text của `align-module-02-venue-court` (commit `92021735`). "Peer-to-peer marketplace transfer" dựng trên text của `direct-owner-cutover`. Trước khi archive phải dựng lại từ spec chính (task 7.4).
- "Immutable price quotes" bị REMOVED. Câu về đơn chờ thanh toán và scenario "Price rules change after quote acceptance" của `align-module-04-booking` nằm trong "Court quotes from price tables". Module 4 bỏ khối MODIFIED của họ (task 1.3).
- "Court quotes from price tables" nhắc tới "Pending bookings at a price change" của module 4, nên module 4 archive ngay sau change này (task 7.5).
- `venue-court-operations`: không có delta. Module 2 giữ "Court price table link" và "Court opening conditions"; change này đặc tả liên kết và độ phủ mà hai requirement đó dẫn tới.
- "Home availability identifies its selected court and date" được MODIFIED: availability chuyển sang mốc 30 phút (design D18), giữ đủ bốn scenario. Mốc 30 phút bật trong merge task 5.1 của module 4 (D29).
- `platform-readiness` dòng 202 ("promotions and points") không sửa: đó là danh sách phạm vi kiểm thử, khuyến mãi và điểm (tích ở POS) vẫn còn.

## Phần giao cho module 4

Module 4 gọi tới, change này đặc tả và làm:
- Engine `RentalPriceCalculator::quote(Court, startsAt, endsAt, ?VoucherChoice, ?Reservation $heldBy = null)` (design D17, task 2.3, 5.1).
- `VoucherUsageService` (design D11, task 5.2). Change này gọi `hold` ở hai đường tạo đơn, và `consume` cho đơn xác nhận ngay khi tạo (task 5.4); module 4 gọi `consume` ở các lần xác nhận sau, `release` ở `releaseHeld` và `ReservationService::release`, và xử lý `VoucherNoLongerApplies` (D11, D29).
- Sự kiện `CourtPricesTookEffect(courtIds, effectiveAt)` lúc công bố và lúc đổi liên kết (design D16, task 3.2).
- `AvailabilityController` viết lại (design D18, task 2.7) và giá "từ" (task 2.8) cho FR-69, FR-70 của module 4. Module 4 đổi bước lưới sang 30 phút và sửa panel trang chủ trong merge task 5.1 của họ (D29).
