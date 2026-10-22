# Truy vết report Module 05

## Nguồn và cách đọc

- Nguồn: `.claude/plans/module-05-report.md`, bản markitdown của bốn PDF module 05 và `FR.pdf`. Gồm FR-82..FR-100 và BR-89..BR-118, tổng 49 mã, cùng UC-5.1..UC-5.18.
- Bằng chứng hiện trạng: `.claude/plans/module-05-gap-audit.md`, đối chiếu code tại `d8648d7c`. Bảng trạng thái lấy theo mục Tóm tắt của audit.
- Trạng thái là kết quả đọc code, không phải kết quả chạy test.
  - **khớp**: code đã làm đúng report.
  - **khớp một phần**: có làm, còn thiếu hoặc sai một phần.
  - **thiếu**: chưa có.
  - **ngược**: code làm trái report.
  - **lệch có chủ đích**: code theo spec `platform-subscriptions` cũ; report này thay quyết định đó.
- Cột "Pha 0" ghi task của Pha 0 nếu có. Chỉ có 0.1: staff bị chặn ở báo cáo tài chính cũ.
- Cột "Task" trỏ tới `tasks.md`. Cột "Test dự kiến" là test sẽ viết hoặc viết lại. Mọi dòng đang ở trạng thái **chưa nghiệm thu** cho tới task 8.3.
- Tên capability viết tắt trong bảng:
  - `ps`: `platform-subscriptions`.
  - `of`: `owner-finance`.
  - `oa`: `organization-access`.
  - `aa`: `api-alignment`.

## Functional requirements (FR-82..FR-100)

| ID | Report yêu cầu | Trạng thái tại `d8648d7c` | Pha 0 | Requirement | Task | Test dự kiến |
|---|---|---|---|---|---|---|
| FR-82 | Xem bốn kỳ hạn và giá đang áp dụng | lệch có chủ đích | – | ps "Venue package terms and prices", ps "Owner view of venue packages" | 2.3, 2.6, 3.6, 3.7 | `PackagePriceScheduleTest`, `OwnerVenuePackageViewTest` |
| FR-83 | Admin đặt giá từng kỳ với thời điểm hiệu lực | lệch có chủ đích | – | ps "Venue package terms and prices" | 2.3, 2.6 | `PackagePriceScheduleTest` |
| FR-84 | Báo owner khi đổi giá | thiếu | – | ps "Venue package terms and prices" | 2.4, 2.5 | `PackagePriceScheduleTest`, `DurableNotificationServiceTest` |
| FR-85 | Đăng ký gói cho một cơ sở | ngược | – | ps "Package payment requests", aa "Report-aligned end-to-end contracts" | 3.1, 3.7 | `PackagePaymentRequestTest` |
| FR-86 | Mã yêu cầu, mã thanh toán, QR, tài khoản AuraCourt, hạn 10 phút | thiếu | – | ps "Package payment requests" | 3.1, 3.7 | `PackagePaymentRequestTest` |
| FR-87 | Đối chiếu giao dịch rồi kích hoạt hoặc gia hạn | khớp một phần | – | ps "Verified package activation and renewal" | 3.2, 3.3, 3.4 | `PackagePaymentWebhookTest`, `PackageActivationAndRenewalTest`, `FinancialEntityPostingTest` |
| FR-88 | Yêu cầu quá 10 phút thành hết hạn | thiếu | – | ps "Package payment requests" | 3.1, 3.3, 4.4 | `PackagePaymentRequestTest`, `PackageRequestExpiryTest` |
| FR-89 | Owner xem gói từng cơ sở | ngược | – | ps "Owner view of venue packages" | 3.6, 3.7 | `OwnerVenuePackageViewTest` |
| FR-90 | Gia hạn cùng kỳ | lệch có chủ đích | – | ps "Package payment requests", ps "Verified package activation and renewal" | 3.1, 3.3 | `PackagePaymentRequestTest`, `PackageActivationAndRenewalTest` |
| FR-91 | Chấm dứt gói trước hạn | lệch có chủ đích | – | ps "Early package termination" | 3.5, 3.7 | `PackageTerminationTest` |
| FR-92 | Gói chặn yêu cầu đặt mới | khớp một phần | – | ps "Package gates new bookings only" | 4.1, 4.2, 4.3, 4.6 | `VenuePackageBookingGateTest` |
| FR-93 | Nhắc hết hạn 7/3/1/0 ngày, in-app và email | thiếu | – | ps "Package expiry reminders" | 2.4, 4.5 | `PackageExpiryReminderTest` |
| FR-94 | Owner đối chiếu giao dịch đặt sân, Δ | khớp một phần | – | of "Owner transaction reconciliation" | 5.2, 5.5 | `OwnerFinanceTransactionsTest` |
| FR-95 | Báo cáo năm chỉ số tách riêng | ngược | – | of "Owner financial report metrics", of "Financial reporting period" | 5.3, 5.5 | `OwnerFinanceReportTest` |
| FR-96 | Owner xuất Excel | thiếu | – | of "Owner-only finance access and export" | 5.1, 5.4 | `OwnerFinanceExportTest` |
| FR-97 | Admin theo dõi yêu cầu và giao dịch phí | thiếu | – | ps "Platform fee administration and reports" | 6.1, 6.5 | `PlatformFeeAdminTest` |
| FR-98 | Admin theo dõi tình trạng gói, nhóm 7/3/1 ngày | thiếu | – | ps "Platform fee administration and reports" | 6.2, 6.5 | `PlatformFeeAdminTest` |
| FR-99 | Báo cáo thu phí theo kỳ, cơ sở, kỳ hạn | thiếu | – | ps "Platform fee administration and reports" | 6.3, 6.5, 6.6 | `PlatformFeeAdminTest` |
| FR-100 | Admin xuất Excel ba danh sách | thiếu | – | ps "Platform fee administration and reports" | 5.1, 6.1, 6.2, 6.3 | `PlatformFeeAdminTest` |

## Business rules (BR-89..BR-118)

| ID | Report yêu cầu | Trạng thái tại `d8648d7c` | Pha 0 | Requirement | Task | Test dự kiến |
|---|---|---|---|---|---|---|
| BR-89 | Chỉ owner xem tài chính và quản lý gói; staff không | khớp một phần | 0.1 | oa "Role-based owner operations", of "Owner-only finance access and export" | 0.1, 3.1, 3.5, 3.6, 5.2–5.5, 6.7 | `AdminReportOwnerScopeTest`, `PackagePaymentRequestTest`, `OwnerFinanceExportTest` |
| BR-90 | Tiền đặt sân và phí nền tảng tách riêng | khớp | – | ps "Platform fee administration and reports" | 3.2, 6.3 | `PackagePaymentWebhookTest`, `PlatformFeeAdminTest` |
| BR-91 | Gói gắn với một cơ sở | ngược | – | ps "Venue package terms and prices" | 2.1, 2.2, 3.3, 4.1 | `PackageActivationAndRenewalTest`, `VenuePackageBookingGateTest` |
| BR-92 | Bốn kỳ 30/90/180/360 ngày, ngày = 24 giờ | ngược | – | ps "Venue package terms and prices" | 2.1, 2.2 | `VenuePackageStatusTest` |
| BR-93 | Giá riêng từng kỳ, chốt lúc tạo yêu cầu, không voucher | lệch có chủ đích | – | ps "Venue package terms and prices", ps "Package payment requests" | 2.3, 3.1 | `PackagePriceScheduleTest`, `PackagePaymentRequestTest` |
| BR-94 | Đổi giá có thời điểm hiệu lực và thông báo | thiếu | – | ps "Venue package terms and prices" | 2.3, 2.5 | `PackagePriceScheduleTest` |
| BR-95 | Yêu cầu hiệu lực 10 phút, mở lại không gia hạn, không huỷ | thiếu | – | ps "Package payment requests" | 3.1, 4.4 | `PackagePaymentRequestTest`, `PackageRequestExpiryTest` |
| BR-96 | Chỉ kích hoạt khi đúng mã, đủ tiền, trước hạn | lệch có chủ đích | – | ps "Verified package activation and renewal" | 3.3 | `PackageActivationAndRenewalTest` |
| BR-97 | Một giao dịch chỉ dùng một lần | khớp | – | ps "Verified package activation and renewal" | 3.2, 3.3, 3.4 | `PackageActivationAndRenewalTest`, `FinancialEntityPostingTest` |
| BR-98 | Gói mới bắt đầu lúc thanh toán thành công | khớp một phần | – | ps "Verified package activation and renewal" | 3.3 | `PackageActivationAndRenewalTest` |
| BR-99 | Gia hạn cùng kỳ, cộng từ hạn hiện tại | lệch có chủ đích | – | ps "Package payment requests", ps "Verified package activation and renewal" | 3.1, 3.3 | `PackagePaymentRequestTest`, `PackageActivationAndRenewalTest` |
| BR-100 | Kỳ khác chỉ sau khi hết hạn hoặc chấm dứt | lệch có chủ đích | – | ps "Package payment requests" | 3.1, 3.5 | `PackagePaymentRequestTest`, `PackageTerminationTest` |
| BR-101 | Chấm dứt có xác nhận, hiệu lực ngay, không hoàn tiền | lệch có chủ đích | – | ps "Early package termination" | 3.5, 3.7 | `PackageTerminationTest` |
| BR-102 | Tạm ngừng hay đình chỉ không dừng thời hạn | khớp | – | ps "Verified package activation and renewal" | 3.3 | `PackageActivationAndRenewalTest` |
| BR-103 | Gói chỉ là điều kiện nhận đặt mới | khớp một phần | – | ps "Package gates new bookings only" | 4.1, 4.2, 4.3, 4.6 | `VenuePackageBookingGateTest` |
| BR-104 | Giữ chỗ trước mốc hết hạn được trả tiếp | khớp, chưa có test | – | ps "Package gates new bookings only" | 4.3 | `VenuePackageBookingGateTest` |
| BR-105 | Nhắc hạn bốn mốc, đổi lịch sau gia hạn | thiếu | – | ps "Package expiry reminders" | 2.4, 4.5 | `PackageExpiryReminderTest` |
| BR-106 | Dùng dữ liệu có sẵn, Pᵢ = Tᵢ − Dᵢ đã ghi nhận | khớp một phần | – | of "Owner transaction reconciliation", of "Owner financial report metrics" | 5.2, 5.3 | `OwnerFinanceTransactionsTest`, `OwnerFinanceReportTest` |
| BR-107 | Δⱼ = Aⱼ − Pᵢ, không gán đơn theo số tiền | thiếu | – | of "Owner transaction reconciliation" | 5.2 | `OwnerFinanceTransactionsTest` |
| BR-108 | Giờ VN, khoảng nửa mở, kỳ theo lịch | ngược | – | of "Financial reporting period", ps "Platform fee administration and reports" | 5.3, 6.3 | `OwnerFinanceReportTest`, `PlatformFeeAdminTest` |
| BR-109 | R và H | thiếu | – | of "Owner financial report metrics" | 5.3 | `OwnerFinanceReportTest` |
| BR-110 | U theo thời điểm thanh toán, trạng thái lúc xem | thiếu | – | of "Owner financial report metrics" | 5.3 | `OwnerFinanceReportTest` |
| BR-111 | N, chỉ đọc kết quả | thiếu | – | of "Owner financial report metrics" | 5.3 | `OwnerFinanceReportTest` |
| BR-112 | S, loại đơn đang xử lý và đơn đã dời | thiếu | – | of "Owner financial report metrics" | 5.3 | `OwnerFinanceReportTest` |
| BR-113 | Đếm mã đơn duy nhất, không cộng tổng | ngược | – | of "Owner financial report metrics" | 5.3, 5.5 | `OwnerFinanceReportTest` |
| BR-114 | Phí đã thu theo yêu cầu đã trả | ngược | – | ps "Platform fee administration and reports" | 6.3 | `PlatformFeeAdminTest` |
| BR-115 | Chờ thanh toán và hết hạn thanh toán, không là công nợ | thiếu | – | ps "Package payment requests", ps "Platform fee administration and reports" | 3.1, 4.4, 6.1 | `PackageRequestExpiryTest`, `PlatformFeeAdminTest` |
| BR-116 | Nhóm sắp hết hạn 7/3/1 ngày, đếm duy nhất | thiếu | – | ps "Platform fee administration and reports" | 6.2 | `PlatformFeeAdminTest` |
| BR-117 | Excel khớp màn hình, có đủ phần đầu file | thiếu | – | of "Owner-only finance access and export", ps "Platform fee administration and reports" | 5.1, 5.4, 6.1–6.3 | `OwnerFinanceExportTest`, `PlatformFeeAdminTest` |
| BR-118 | Không chi phí, hoàn tiền, chia tiền | khớp một phần | – | of "Owner financial report metrics" | 5.3, 6.7 | `OwnerFinanceReportTest` |

## Use case (UC-5.1..UC-5.18)

| UC | Tên | FR | Requirement | Task |
|---|---|---|---|---|
| UC-5.1 | Xem thông tin gói và giá | FR-82 | ps "Venue package terms and prices" | 2.3, 2.6, 3.6, 3.7 |
| UC-5.2 | Thiết lập giá gói | FR-83 | ps "Venue package terms and prices" | 2.3, 2.6 |
| UC-5.3 | Cập nhật và công bố thay đổi giá | FR-83, FR-84 | ps "Venue package terms and prices" | 2.3, 2.5 |
| UC-5.4 | Đăng ký gói cho cơ sở | FR-85 | ps "Package payment requests" | 3.1, 3.7 |
| UC-5.5 | Xem thông tin thanh toán phí | FR-86 | ps "Package payment requests" | 3.1, 3.7 |
| UC-5.6 | Tiếp nhận kết quả thanh toán, kích hoạt hoặc gia hạn | FR-87 | ps "Verified package activation and renewal" | 3.2, 3.3, 3.4 |
| UC-5.7 | Xem gói của cơ sở | FR-89 | ps "Owner view of venue packages" | 3.6, 3.7 |
| UC-5.8 | Gia hạn gói | FR-90 | ps "Package payment requests", ps "Verified package activation and renewal" | 3.1, 3.3, 3.7 |
| UC-5.9 | Chấm dứt gói trước hạn | FR-91 | ps "Early package termination" | 3.5, 3.7 |
| UC-5.10 | Tra cứu và đối chiếu giao dịch đặt sân | FR-94 | of "Owner transaction reconciliation" | 5.2, 5.5 |
| UC-5.11 | Xem báo cáo tài chính cơ sở | FR-95 | of "Owner financial report metrics", of "Financial reporting period" | 5.3, 5.5 |
| UC-5.12 | Xuất báo cáo tài chính cơ sở | FR-96 | of "Owner-only finance access and export" | 5.1, 5.4 |
| UC-5.13 | Xem danh sách yêu cầu thanh toán phí | FR-97 | ps "Platform fee administration and reports" | 6.1, 6.5 |
| UC-5.14 | Xem chi tiết yêu cầu và giao dịch phí | FR-97 | ps "Platform fee administration and reports" | 6.1, 6.5 |
| UC-5.15 | Xem danh sách và tình trạng gói | FR-98 | ps "Platform fee administration and reports" | 6.2, 6.5 |
| UC-5.16 | Xem chi tiết gói của cơ sở | FR-98 | ps "Platform fee administration and reports" | 6.2, 6.5 |
| UC-5.17 | Xem báo cáo thu phí | FR-99 | ps "Platform fee administration and reports" | 6.3, 6.5 |
| UC-5.18 | Xuất báo cáo thu phí và tình trạng gói | FR-100 | ps "Platform fee administration and reports" | 6.1, 6.2, 6.3 |

FR-88 (hết hạn yêu cầu), FR-92 (cổng nhận đặt) và FR-93 (nhắc hạn) không có UC riêng trong report: tác nhân là hệ thống.

## Lỗi đang chạy trên prod (audit, mục "Lỗi đang chạy trên prod")

| Lỗi | Mã | Task |
|---|---|---|
| Staff đọc và xuất được báo cáo tài chính tổng hợp | BR-89 | 0.1 |
| Cổng gói tắt vì cờ `partner.enforce_subscription_check` mặc định false | BR-103, FR-92 | 4.1, 4.2, 4.3 |
| Mua gói chỉ ra QR sandbox | FR-86, FR-87 | 3.1, 3.2, 3.7, 7.4 |
| Thanh toán đến muộn vẫn kích hoạt gói | BR-96 | 3.3 |
| Mốc kỳ báo cáo bị đếm hai lần (`whereBetween`) | BR-108 | 5.3, 6.7 |
