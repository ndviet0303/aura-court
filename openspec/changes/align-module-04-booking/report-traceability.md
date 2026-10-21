# Truy vết report Module 04

## Nguồn và cách đọc

- Nguồn: `.claude/plans/module-04-report.md`, bản markitdown của `sub-documents (2).pdf`. Gồm FR-69..FR-81 và BR-72..BR-88, tổng 30 mã, cùng UC-4.1..UC-4.12.
- Bằng chứng hiện trạng: `.claude/plans/module-04-gap-audit.md`, đối chiếu code tại `311790f3`. Bảng trạng thái lấy theo mục 1 của `.claude/plans/module-04-report-alignment.plan.md`.
- Trạng thái là kết quả đọc code, không phải kết quả chạy test.
  - **khớp**: code đã làm đúng report.
  - **khớp một phần**: có làm, còn thiếu hoặc sai một phần.
  - **thiếu**: chưa có.
  - **ngược**: code làm trái report.
  - **lệch có chủ đích**: code theo quyết định cũ (archive hoặc `direct-owner-cutover`); report này thay quyết định đó.
- Cột "Pha 0" ghi mục sửa trong commit `ff8b41a6` (merge `7312b44c`), nếu có:
  - 0.1: webhook SePay nền tảng từ chối chữ ký khi secret rỗng.
  - 0.2: tra mã đơn ở quầy chỉ trong cơ sở được phân công.
  - 0.3: QR đơn và QR thẻ ví vẽ tại chỗ.
  - 0.4: lịch sân dùng ngày địa phương.
  - 0.5: lịch sử đơn có phân trang, số đếm từ `total`, nhãn trạng thái và QR theo D10.
  - 0.6: `finance/audit-logs` lọc theo tổ chức.
- Cột "Task" trỏ tới `tasks.md`. Cột "Test dự kiến" là test sẽ viết hoặc viết lại. Mọi dòng đang ở trạng thái **chưa nghiệm thu** cho tới task 6.3.
- Tên capability viết tắt trong bảng:
  - `rp`: `reservations-and-pricing`.
  - `vco`: `venue-court-operations`.
  - `dop`: `direct-owner-payments`.
  - `osa`: `on-site-attendance`.
  - `flp`: `financial-ledger-and-payouts`.

## Functional requirements (FR-69..FR-81)

| ID | Report yêu cầu | Trạng thái tại `311790f3` | Pha 0 | Requirement | Task | Test dự kiến |
| --- | --- | --- | --- | --- | --- | --- |
| FR-69 | Tìm cơ sở, xem thông tin công khai và các sân trực thuộc, kèm giá "từ". | khớp một phần | – | Không đổi ở change này. Danh mục thuộc module 2, giá "từ" thuộc module 3 (design D13). | 6.4 | Thuộc module 2 và 3 |
| FR-70 | Lịch phục vụ và các khoảng được đặt theo ngày, dựa trên cơ sở, sân, thời gian bị chặn, đơn đã xác nhận, giữ chỗ còn hạn và giá. Xem lịch không giữ chỗ. | khớp một phần | 0.4 | `rp`: Active inventory exclusivity (hold quá hạn không chặn); `rp`: Thirty-minute booking interval. Availability viết lại thuộc module 3 (Pha 2 của họ); bước 30 phút do task 5.1. | 1.2, 2.4, 5.1, 5.7 | `OverdueHoldTest`; `ThirtyMinuteBookingIntervalTest`; test bốn mốc 30 phút của đơn 19:00–21:00; vitest form chọn giờ |
| FR-71 | Chọn một sân và một khoảng liên tục theo mốc 30 phút, tối thiểu 30 phút. Lấy họ tên, SĐT, email từ tài khoản, bắt bổ sung SĐT khi thiếu. Hiện tiền thuê, voucher, tiền giảm, tiền phải trả trước khi gửi. | khớp một phần | – | `rp`: Thirty-minute booking interval; `rp`: Booker contact snapshot; `rp`: Customer-agreed amount | 2.8, 5.7 | Vitest `BookingView`; `BookerSnapshotTest` |
| FR-72 | Khi gửi: kiểm tài khoản, thời gian, sân trống, giá, voucher. Đơn cần trả thì tạo Chờ thanh toán, giữ sân, tạm giữ voucher, chặn giữ chỗ đồng thời, hạn theo BR-79. | khớp một phần | – | `rp`: Payment deadline for pending bookings; `rp`: Customer-agreed amount; `rp`: Booker contact snapshot; `rp`: Active inventory exclusivity | 2.1–2.5, 5.1 | `PaymentDeadlineTest`; `AgreedAmountTest`; `BookerSnapshotTest`; ca voucher: `OrdinaryCheckoutVoucherTest` (task 5.4 của module 3) |
| FR-73 | Hiện mã thanh toán, QR, số tiền, tài khoản nhận và hạn. Mở lại thông tin của đơn còn hạn mà không gia hạn. | khớp | – | `rp`: Payment deadline for pending bookings (scenario "Reopening the payment information"). Hạn attempt chuyển sang đúng `hold_expires_at` theo D3. | 2.5 | `PaymentDeadlineTest` |
| FR-74 | Đối chiếu giao dịch với mã, số tiền và hạn. Hợp lệ thì Đã xác nhận và ghi nhận voucher. Nhận lại cùng giao dịch không xác nhận hay trừ lượt lặp. | khớp một phần | – | `dop`: Receiver-bound payment evidence (giữ nguyên); `rp`: Payment deadline for pending bookings; design D12 | 5.5 | `VoucherConsumeOnConfirmTest` |
| FR-75 | Đơn 0đ: kiểm điều kiện, xác nhận và ghi nhận voucher, không tạo yêu cầu thanh toán. | khớp một phần | – | `rp`: Payment deadline for pending bookings (chỉ áp cho đơn có tiền phải trả > 0); module 3 "Atomic voucher use" | Task 5.4 của module 3 | `OrdinaryCheckoutVoucherTest` (module 3) |
| FR-76 | Khách huỷ khi đơn còn Chờ thanh toán. Kiểm lại trạng thái, chuyển Đã hủy thanh toán, nhả chỗ và voucher. Từ chối khi đơn đã xác nhận. | lệch có chủ đích | – | `rp`: Customer cancels a pending-payment booking; `rp`: Final bookings and released availability | 3.1, 3.4, 3.5 | `CustomerPaymentCancelTest`; vitest nút "Hủy thanh toán" |
| FR-77 | Tự chuyển Hết hạn thanh toán khi tới hạn, nhả chỗ và voucher, không gia hạn voucher, không tự khôi phục khi tiền tới sau. | khớp | – | `rp`: Customer cancels a pending-payment booking (dùng chung bước nhả); `rp`: Active inventory exclusivity | 2.1 | `HeldReservationReleaseTest` |
| FR-78 | Khi giá mới có hiệu lực, kiểm lại đơn Chờ thanh toán. Tiền đổi thì yêu cầu cũ hết hiệu lực, nhả chỗ và voucher, báo khách. Tiền không đổi thì giữ nguyên. Không tính lại đơn đã xác nhận. | lệch có chủ đích | – | `rp`: Pending bookings at a price change; module 3 "Court quotes from price tables" | 5.6 | `PendingPriceChangeTest` |
| FR-79 | Khách xem đơn của mình: cơ sở, sân, thời gian, người đặt, các khoản tiền, voucher, kết quả thanh toán, trạng thái, check-in/check-out, thay đổi do sự cố. | khớp một phần | 0.5 | `rp`: Customer booking list and detail. Voucher trong chi tiết do task 5.4–5.5 của module 3. | 3.3, 3.4, 3.6 | Vitest `ReservationDetailModal`; test API chi tiết đơn |
| FR-80 | Mã đơn và QR để xuất trình khi nhận, trả sân; liên kết đúng đơn; phân biệt với QR thanh toán. | khớp một phần | 0.3 | `rp`: Customer booking list and detail; `osa`: Attendance lookup scope | 3.4 | `QrImage.spec.ts` (Pha 0); vitest QR chỉ hiện khi đã xác nhận hoặc đang sử dụng |
| FR-81 | Ghi nhận khoản thiếu, thừa, sai mã, lặp, muộn; không tự xác nhận hay khôi phục. Chủ cơ sở xem giao dịch và đơn liên quan để đối chiếu và xử lý ngoài hệ thống. | ghi nhận: khớp; tra cứu của owner: lệch có chủ đích | 0.6 | `dop`: Owner lookup of payment exceptions; `flp`: Settlement and payout lifecycle; `dop`: Receiver-bound payment evidence | 4.1–4.4 | `OwnerPaymentExceptionLookupTest`; vitest `OwnerPaymentExceptionsView`, `AdminLayout` |

## Business rules (BR-72..BR-88)

| ID | Report yêu cầu | Trạng thái tại `311790f3` | Pha 0 | Requirement | Task | Test dự kiến |
| --- | --- | --- | --- | --- | --- | --- |
| BR-72 | Khách vãng lai xem thông tin và lịch. Gửi yêu cầu phải đăng nhập bằng tài khoản không bị khoá, đã xác thực email. | khớp | – | Không đổi | – | Test có sẵn |
| BR-73 | Mỗi đơn một sân, một khoảng liên tục. Một tài khoản đặt được nhiều đơn; mỗi yêu cầu kiểm độc lập. | lệch có chủ đích | – | `rp`: Thirty-minute booking interval; `rp`: Active inventory exclusivity | 2.4, 5.1 | `ThirtyMinuteBookingIntervalTest` ("Gapped selection"); `OverdueHoldTest` ("Configured booking window") |
| BR-74 | Họ tên, SĐT, email lấy từ tài khoản; bổ sung SĐT trước khi gửi; lưu cùng đơn, sửa hồ sơ sau không đổi đơn. | thiếu | – | `rp`: Booker contact snapshot | 2.2, 2.6, 2.7, 2.8 | `BookerSnapshotTest` |
| BR-75 | Giờ bắt đầu và kết thúc theo mốc 30 phút, kết thúc sau bắt đầu, tối thiểu 30 phút, không bắt đầu ở quá khứ. Đơn cần trả phải còn thời gian thanh toán trước giờ bắt đầu. | lệch có chủ đích | – | `rp`: Thirty-minute booking interval; REMOVED `rp`: One-hour booking slots; `rp`: Payment deadline for pending bookings ("No payment window left"); `vco`: Courts carry their own sport and booking interval | 2.2, 5.1–5.4 | `ThirtyMinuteBookingIntervalTest`; `PaymentDeadlineTest` |
| BR-76 | Toàn bộ thời gian nằm trong lịch phục vụ của sân và giờ cơ sở, có giá, không bị chặn; cơ sở và sân nhận đặt, không đình chỉ. | khớp một phần | – | Không đổi ở change này. Thuộc module 2 ("Unified public and new-booking eligibility") và module 3 (độ phủ giá). | 6.4 | Thuộc module 2 và 3 |
| BR-77 | Không giao với đơn đã xác nhận hoặc giữ chỗ còn hạn trên cùng sân; khoảng nửa mở; tranh đồng thời chỉ một bên giữ được. | khớp một phần | – | `rp`: Active inventory exclusivity | 2.2, 2.4 | `OverdueHoldTest` ("Overdue hold does not block", "Live hold blocks") |
| BR-78 | Xem lịch, chọn sân, chọn voucher chưa giữ chỗ. Chỉ giữ sau khi gửi và kiểm thành công. Lượt đang giữ không cấp cho yêu cầu khác và chưa tính là đã dùng. | khớp | – | Không đổi. Bộ đếm voucher thuộc module 3. | 5.5 | Thuộc module 3 (`hold / release / consume`) |
| BR-79 | Hạn thanh toán = mốc sớm nhất của giữ chỗ + 10 phút, giờ bắt đầu, hạn voucher. Mở lại màn thanh toán không gia hạn. | ngược | – | `rp`: Payment deadline for pending bookings | 2.2, 2.5 | `PaymentDeadlineTest`; ca voucher: `OrdinaryCheckoutVoucherTest` (module 3) |
| BR-80 | Tiền tính theo module 3, tối đa một voucher. Khách xem tiền thuê, tiền giảm, tổng trước khi gửi. | khớp một phần | – | `rp`: Customer-agreed amount | 2.2, 2.3, 2.8 | `AgreedAmountTest`; vitest `PRICE_CHANGED`; ca giá tăng có voucher: `OrdinaryCheckoutVoucherTest` (module 3) |
| BR-81 | Chỉ tự xác nhận khi đúng mã, đủ tiền, đúng hạn. Tài khoản nhận là của chủ cơ sở. Ảnh hay thông báo của khách không xác nhận đơn. | khớp (trừ thời điểm khai tài khoản, design D13) | 0.1 | `dop`: Receiver-bound payment evidence; `rp`: Payment deadline for pending bookings | 2.5 | Test có sẵn; `PaymentDeadlineTest` |
| BR-82 | Đơn 0đ xác nhận sau khi kiểm sân và voucher, không tạo yêu cầu thanh toán, voucher ghi nhận cùng lúc. | khớp một phần | – | Module 3 "Atomic voucher use" (scenario "Zero amount payable"); design D12 | Task 5.4 của module 3 | `OrdinaryCheckoutVoucherTest` (module 3) |
| BR-83 | Xác nhận đơn và ghi nhận voucher cùng thành công; nhận lại cùng giao dịch không tạo thêm đơn, xác nhận hay trừ lượt. | khớp một phần | – | Design D12 | 5.5 | `VoucherConsumeOnConfirmTest` |
| BR-84 | Chỉ huỷ khi Chờ thanh toán. Huỷ hoặc hết hạn nhả chỗ và voucher, không ghi đã dùng, không gia hạn voucher. Không huỷ đơn đã xác nhận bằng chức năng này. | khớp một phần | – | `rp`: Customer cancels a pending-payment booking; `rp`: Final bookings and released availability | 2.1, 3.1 | `CustomerPaymentCancelTest`; `HeldReservationReleaseTest` |
| BR-85 | Xác nhận trước thì từ chối huỷ. Huỷ trước thì tiền tới sau là thanh toán bất thường, không khôi phục đơn. | thiếu | – | `rp`: Customer cancels a pending-payment booking | 3.1, 3.2, 3.5 | `CustomerPaymentCancelTest` (đua huỷ và xác nhận) |
| BR-86 | Khoản bất thường không tự xác nhận hay khôi phục; không gán theo số tiền trùng; hoàn tiền ngoài hệ thống. | khớp | – | `dop`: Receiver-bound payment evidence; `dop`: Owner lookup of payment exceptions | 4.1 | `OwnerPaymentExceptionLookupTest` ("Unknown payment code") |
| BR-87 | Kiểm lại đơn chờ tại mốc chuyển giá như FR-78. | lệch có chủ đích | – | `rp`: Pending bookings at a price change | 5.6 | `PendingPriceChangeTest` |
| BR-88 | Khách chỉ xem đơn của mình. Chủ cơ sở chỉ tra cứu khoản bất thường và đơn liên quan của tổ chức mình. | khớp một phần | 0.2 | `rp`: Customer booking list and detail; `dop`: Owner lookup of payment exceptions; `osa`: Attendance lookup scope | 4.2 | `OwnerPaymentExceptionLookupTest`; `AttendanceOperationsAndReadinessTest` (Pha 0) |

## Use case

| UC | Tên | FR | Ghi chú |
| --- | --- | --- | --- |
| UC-4.1 | Tìm kiếm cơ sở thể thao | FR-69 | Thuộc module 2 và 3 |
| UC-4.2 | Xem thông tin cơ sở và các sân trực thuộc | FR-69 | Thuộc module 2 và 3 |
| UC-4.3 | Tra cứu lịch sân còn trống | FR-70 | Pha 2 (hold quá hạn), Pha 5 (lưới 30 phút) |
| UC-4.4 | Thiết lập thông tin đặt sân | FR-71 | Pha 2, Pha 5 |
| UC-4.5 | Gửi yêu cầu đặt sân | FR-72, FR-75, FR-77, FR-78 | Pha 2, Pha 5 |
| UC-4.6 | Xem thông tin thanh toán | FR-73 | Pha 2 |
| UC-4.7 | Tiếp nhận kết quả thanh toán và xác nhận đơn | FR-74, FR-81 | Pha 4, Pha 5 |
| UC-4.8 | Hủy yêu cầu đang chờ thanh toán | FR-76 | Pha 3 |
| UC-4.9 | Xem danh sách đơn của khách hàng | FR-79 | Pha 0 đã làm phân trang và nhãn |
| UC-4.10 | Xem chi tiết đơn của khách hàng | FR-79 | Pha 3 |
| UC-4.11 | Xem mã và QR của đơn đã xác nhận | FR-80 | Pha 0 đã làm |
| UC-4.12 | Tra cứu giao dịch thanh toán bất thường | FR-81 | Pha 4 |

## Đồng bộ spec ngoài module

- "One-hour booking slots" do `direct-owner-cutover` thêm. Change này REMOVED nó nên chỉ archive được sau cutover (task 6.6).
- "Final bookings and released availability" và "Courts carry their own sport and booking interval" dựng trên text của `align-module-02-venue-court`. "Settlement and payout lifecycle" dựng trên text của cutover. Trước khi archive phải dựng lại từ spec chính (task 6.5).
- "Immutable price quotes": module 3 REMOVED requirement này. Câu về đơn chờ thanh toán và scenario "Price rules change after quote acceptance" nằm nguyên văn trong "Court quotes from price tables" của module 3. Change này không còn khối MODIFIED cho nó và archive ngay sau change của module 3 (task 6.6).
- "Home availability identifies its selected court and date": module 3 giữ khối MODIFIED (mốc 30 phút). Change này không thêm khối trùng.

## Phần thuộc module 3

Change này chỉ gọi tới. Các phần dưới đây do change của module 3 đặc tả và làm:
- Engine tính tiền theo phút `T = ⌊Σ dᵢ·pᵢ / 60⌋` trên `[starts_at, ends_at)` (BR-55), qua `RentalPriceCalculator::quote`. Snapshot có hạn voucher. Dùng ở D3, D5, D11, D14.
- `VoucherUsageService`: module 3 gọi `hold` lúc tạo đơn và `consume` cho đơn xác nhận ngay khi tạo (FR-75, BR-82). Change này gọi `consume` và `release` ở D2, D12.
- Sự kiện `CourtPricesTookEffect`, phát lúc công bố phiên bản và lúc đổi liên kết. Dùng ở D11.
- Viết lại `AvailabilityController` ở Pha 2 của module 3: so khoảng nửa mở, coi hold quá hạn là trống, bước `slot_duration` (FR-70). Change này đổi bước sang 30 phút ở task 5.1.
- Giá "từ" ở danh mục (FR-69).
