## Why

Report Module 05 (`.claude/plans/module-05-report.md`: FR-82..FR-100, BR-89..BR-118, 7 bước, UC-5.1..UC-5.18) mô tả ba phần:

- **Gói sử dụng nền tảng theo từng cơ sở.**
  - Bốn kỳ hạn 30/90/180/360 ngày, mỗi ngày đúng 24 giờ. Mỗi kỳ hạn có giá riêng và thời điểm hiệu lực riêng.
  - Phí trả bằng chuyển khoản vào tài khoản AuraCourt, theo yêu cầu có hạn 10 phút. Chỉ kích hoạt khi đúng mã, đủ tiền và đến trước hạn.
  - Gia hạn phải cùng kỳ hạn. Owner chấm dứt được ngay.
  - Nhắc hạn ở các mốc 7/3/1/0 ngày.
  - Gói chỉ chặn việc nhận đơn mới.
- **Tài chính của chủ cơ sở.**
  - Danh sách giao dịch kèm Δ.
  - Năm chỉ số R/H/U/N/S tách riêng, theo giờ VN và khoảng nửa mở.
  - Chỉ owner xem được. Xuất Excel.
- **Quản trị phí nền tảng.** Danh sách yêu cầu, tình trạng gói, báo cáo phí đã thu, xuất Excel.

Đối chiếu code `d8648d7c` (`.claude/plans/module-05-gap-audit.md`) cho kết quả:

| Kết quả | Số mã |
|---|---|
| Khớp | 4 |
| Khớp một phần | 8 |
| Thiếu hẳn | 20 |
| Làm ngược report | 8 |
| Lệch có chủ đích theo spec cũ | 9 |

Code làm theo spec `platform-subscriptions` cũ:
- Gói theo tổ chức, plan có phiên bản, kỳ 1/6/12 tháng lịch.
- Có quota và trial. Huỷ theo kiểu dừng gia hạn ở cuối kỳ.
- Trả qua PayOS và đang chạy sandbox. Khoản trả muộn vẫn kích hoạt.

Trên prod hiện có các lỗi:
- Cổng gói đang tắt sau cờ.
- Mua gói chỉ ra QR giả.
- Staff đọc và xuất được báo cáo tài chính tổng hợp.
- Báo cáo trộn đặt sân với POS và hoàn tiền, dùng khoảng thời gian gồm cả hai đầu.

Người dùng muốn bật hết để trình bày demo trên prod (2026-10-22). Vì vậy change này nhắm tới chạy thật: kiểm gói luôn bật, thanh toán phí chạy thật qua SePay của AuraCourt, seed có sẵn gói cho các cơ sở.

## What Changes

- **Gói theo cơ sở, cutover sạch (D1, D2; BR-91, BR-92).**
  - **BREAKING:** xoá `platform_plans`, `platform_subscriptions`, `platform_payment_attempts` và `platform_provider_receipts`.
  - Tạo `package_prices`, `venue_packages`, `package_payment_requests` và `package_payment_receipts`.
  - Kỳ hạn cố định 30/90/180/360 ngày, `ends_at = base + d × 24h`.
  - **BREAKING:** bỏ trial, quota cơ sở/sân/nhân sự, feature của plan, `auto_renew`, cancel-renewal, plan CRUD và catalog.
- **Giá gói (D3; BR-93, BR-94, FR-82..FR-84).**
  - Admin đặt giá cho từng kỳ hạn kèm `effective_at`. Giá mới thay giá đã lên lịch nhưng chưa hiệu lực của cùng kỳ.
  - Công bố giá thì báo in-app và email cho mọi owner.
  - Yêu cầu thanh toán chốt giá đang hiệu lực lúc tạo.
- **Yêu cầu thanh toán phí (D4, D5, D7; BR-95, BR-115, FR-85, FR-86, FR-88).**
  - `POST partner/organizations/{org}/venues/{venue}/package-requests` nhận `term_days`. Loại mua mới hay gia hạn suy ra từ gói hiện tại.
  - Response gồm mã yêu cầu, mã thanh toán `PKG…`, số tiền, tài khoản AuraCourt, QR VietQR và hạn 10 phút.
  - Mỗi cơ sở chỉ có một yêu cầu đang chờ. Mở lại trả đúng yêu cầu đó. Không có thao tác huỷ.
  - Hết hạn suy ra từ `expires_at` lúc đọc; job mỗi phút ghi `expired`.
- **Kích hoạt và gia hạn (D5, D6; BR-96..BR-100, FR-87, FR-90).**
  - Webhook SePay nền tảng có nhánh `PKG…` đặt trước nhánh nạp ví.
  - Mọi giao dịch mang mã gói được lưu kèm kết quả. Chỉ `applied` mới kích hoạt.
  - Kích hoạt chạy dưới lock của cơ sở:
    - Còn hạn và cùng kỳ hạn: cộng từ `ends_at`.
    - Chưa có gói, hết hạn hoặc đã chấm dứt: bắt đầu lúc trả.
    - Còn hạn mà khác kỳ hạn: `term_conflict`, không kích hoạt.
  - **BREAKING:** bỏ PayOS cho phí gói (`ProviderNeutralSubscriptionPaymentAdapter`, nhánh nền tảng của `ProviderEventHandler`).
- **Chấm dứt trước hạn (D8; BR-101, FR-91).** `POST …/package/terminate` với `confirm_consequences: true`. Hết hiệu lực ngay. Giữ lịch sử, không đụng đơn đã xác nhận.
- **Cổng nhận đặt (D9, D10; BR-102..BR-104, FR-92).**
  - `VerifyEffectiveBookingEligibility` kiểm gói của chính cơ sở tại `now`.
  - **BREAKING:** bỏ cờ `partner.enforce_subscription_check` và nhánh shadow; kiểm gói luôn chạy.
  - Đơn giữ chỗ trước khi hết hạn vẫn trả được tới hạn ban đầu.
  - Quản lý giá và khuyến mãi không còn đòi gói.
- **Nhắc hạn (D11; BR-105, FR-93).** Gửi in-app và email ở các mốc −7, −3, −1 ngày và đúng lúc hết hạn. Lịch nhắc theo hạn hiện tại. Gói đã chấm dứt không nhận nhắc.
- **Tài chính của chủ cơ sở (D12, D13; BR-89, BR-106..BR-113, BR-118, FR-94, FR-95).**
  - `GET partner/organizations/{org}/finance/transactions`: giao dịch nhận tiền của module 04, kèm Pᵢ, Aⱼ, Δ và kết quả đối chiếu.
  - `GET partner/organizations/{org}/finance/report`: R/H/U/N/S kèm số đơn duy nhất, theo giờ VN và `[start, end)`, lọc theo cơ sở và sân.
  - Chỉ owner gọi được.
  - **BREAKING:** xoá `admin/reports`, `admin/reports.csv` và `admin/reports/csv`.
- **Quản trị phí nền tảng (D15; BR-114..BR-116, FR-97..FR-99).**
  - `admin/platform-fees/{prices, requests, requests/{code}, venues, venues/{venue}, report}`.
  - FE `/admin/platform-fees`.
  - Dashboard admin đổi sang tóm tắt phí nền tảng.
- **Xuất Excel (D14; BR-117, FR-96, FR-100).** Server dựng xlsx từ cùng dataset với màn hình. Thư viện chờ người dùng duyệt (Q1).
- **Pha 0 (sửa lỗi prod):** staff nhận 403 ở `admin/reports*`.
- **Demo (D16):**
  - Seed bốn giá và gói cho mọi cơ sở, kèm một cơ sở sắp hết hạn và lịch sử yêu cầu.
  - Kiểm env SePay trên prod. Reseed theo quy trình có backup.

## Capabilities

### New Capabilities

- `owner-finance`: tra cứu giao dịch và Δ, năm chỉ số tài chính của cơ sở, xuất Excel, chỉ owner.

### Modified Capabilities

- `platform-subscriptions`: viết lại toàn bộ.
  - REMOVED năm requirement cũ (plan có phiên bản, kích hoạt, trial, hết hạn, hoàn chỉnh hợp đồng).
  - ADDED: kỳ hạn và giá gói, yêu cầu thanh toán, kích hoạt và gia hạn, chấm dứt, cổng nhận đặt, nhắc hạn, gói của cơ sở cho owner, quản trị và báo cáo phí nền tảng.
- `organization-access`:
  - "Role-based owner operations": tài chính tổng hợp và quản lý gói chỉ dành cho owner (BR-89). Khối này dựng trên text của `align-module-03-pricing-promotions`.
  - "Real staff invitation and permission lifecycle": bỏ quota nhân sự.
- `api-alignment` "Report-aligned end-to-end contracts": scenario "Subscription journey" (chọn plan, trial) đổi thành hành trình gói theo cơ sở.

Không sửa:
- `direct-owner-payments` "Separate platform subscription receiver": tài khoản SePay riêng của AuraCourt cùng namespace `PKG` đã đúng requirement này.
- `venue-court-operations` "Unified public and new-booking eligibility", "Venue opening conditions", "Single organization-scoped venue write path": `align-module-02-venue-court` đang sửa các requirement này; nhờ module 2 đổi "plan/trial" thành gói của cơ sở và bỏ hạn mức cơ sở (design, mục Phối hợp).
- `reservations-and-pricing` "Price table versions and effective time", "Venue promotion programs": `align-module-03-pricing-promotions` thêm các requirement này; nhờ module 3 bỏ câu "business entitlement" (D10).

## Impact

- **Backend theo pha** (`.claude/plans/module-05-report-alignment.plan.md`):
  - Pha 0: chặn staff ở báo cáo cũ.
  - Pha 2: schema và model gói, giá, admin giá.
  - Pha 3: yêu cầu thanh toán, nhánh webhook, kích hoạt, chấm dứt.
  - Pha 4: cổng nhận đặt, nhắc hạn.
  - Pha 5: tài chính owner.
  - Pha 6: quản trị phí nền tảng.
  - Pha 7: seed, demo, nghiệm thu.
- **Xoá:**
  - `app/Domain/Subscription/Actions/*`, gồm 10 action.
  - `PlatformPlanController`, `PlatformSubscriptionController` và các request, resource kèm theo.
  - `ProviderNeutralSubscriptionPaymentAdapter`, `SubscriptionPaymentAdapterInterface`.
  - Model `PlatformPlan`, `PlatformSubscription`, `PlatformPaymentAttempt`, `PlatformProviderReceipt`.
  - `AdminController::report/reportCsv/buildReportDataset`.
  - Cờ `enforce_subscription_check`.
- **API:**
  - Thêm route owner gói, route owner tài chính và route admin phí nền tảng.
  - Xoá `partner/organizations/{id}/subscription/*`, `partner/subscription/confirm-payment`, `platform/plans`, `admin/platform-plans*` và `admin/reports*`.
  - Mã lỗi mới:
    - `PACKAGE_REQUEST_PENDING`
    - `PACKAGE_TERM_LOCKED`
    - `PACKAGE_PRICE_UNAVAILABLE`
    - `PACKAGE_NOT_ACTIVE`
    - `TERMINATION_NOT_CONFIRMED`
- **Frontend:**
  - `/partner/subscription` viết lại thành danh sách gói theo cơ sở. Có luồng mua và gia hạn với QR đếm ngược, và hộp xác nhận chấm dứt.
  - `/admin/finance` cho owner: giao dịch và báo cáo, xuất Excel.
  - `/admin/platform-fees` cho platform admin.
  - Dashboard `/admin` đổi khối báo cáo.
  - Bản dịch vi/en.
- **Dữ liệu:**
  - Migration xoá bốn bảng `platform_*` cũ và tạo bốn bảng mới. Nhắc hạn dùng outbox có sẵn, khoá idempotent bằng `message_key`.
  - Prod chỉ có gói do seed tạo; reseed demo tạo lại dữ liệu.
  - Ledger giữ `postPlatformSubscriptionReceipt` với nguồn là receipt gói.
- **Phụ thuộc:** xlsx cần một thư viện mới (Q1, chờ duyệt). Có thể cần thêm `ext-zip` cho image prod.
- **Phối hợp:**
  - Module 2: điều kiện "valid plan/trial", task 2.3 về quota, thứ tự lock.
  - Module 3: bỏ yêu cầu gói ở quản lý giá và khuyến mãi.
  - Module 4: chỉ đọc dữ liệu nhận tiền.
- **Non-goals:**
  - Hoàn tiền, chi phí vận hành, chia tiền (BR-118).
  - Huỷ yêu cầu thanh toán phí (BR-95).
  - Voucher cho phí gói.
  - POS trong báo cáo tài chính của owner.
  - Ghi nhận no-show hay kết thúc đơn do sự cố. Module 05 chỉ đọc kết quả của module 2 và 4.
