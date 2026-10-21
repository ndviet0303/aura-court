## Context

Lý do và phạm vi xem `proposal.md`. Bằng chứng từng mã xem `.claude/plans/module-05-gap-audit.md`. Phần này chỉ ghi hiện trạng code tại `d8648d7c` và các ràng buộc quyết định cách làm.

- **Gói hiện tại theo tổ chức.**
  - Bảng `platform_plans`, `platform_subscriptions` (khoá theo `organization_id`), `platform_payment_attempts`, `platform_provider_receipts` (`2026_09_10_000003_create_platform_subscription_tables.php`).
  - 10 action trong `app/Domain/Subscription/Actions/`. Kỳ 1/6/12 tháng lịch (`PlatformPlan::monthsForBillingCycle`), có trial và quota cơ sở/sân/nhân sự.
  - `ConfirmSubscriptionPayment` kích hoạt cả khoản trả muộn, `abort(422)` khi lệch tiền, và bật `auto_renew`.
- **Thanh toán phí đang giả.**
  - `InitiateSubscriptionPayment` gọi `ProviderNeutralSubscriptionPaymentAdapter`, adapter này trả QR sandbox (dòng 18-32).
  - `ProviderEventHandler` bỏ qua im lặng sự kiện chưa xác minh (dòng 43-50).
- **Cổng nhận đặt đang tắt.**
  - `VerifyEffectiveBookingEligibility::getEntitlement($orgId)` khoá `platform_subscriptions`.
  - Nhánh shadow chạy khi `config('partner.enforce_subscription_check')` false, mà mặc định là false (`config/partner.php:37`).
  - Hai đường tạo đơn (`ReservationService:67`, `OrdinaryCheckoutService:58`) gọi `getEntitlement` trước khi khoá sân. `ReservationService::confirm` không kiểm lại, nên BR-104 đã đúng.
- **Webhook SePay của AuraCourt** (`routes/api.php:114` → `SepayWebhookController` → `ProcessQrPayment`).
  - Xác thực HMAC hoặc API key.
  - Chống trùng theo `Payment::external_transaction_id`. Nhận diện nạp ví bằng `ORD…`, `DEP-…`, `NAP|WALLET…`.
  - Mọi giao dịch vào đều tạo một `Payment`, kể cả khi không khớp gì.
  - Cấu hình nằm ở `config/services.php` `sepay` (`bank`, `account`, `account_name`, `api_key`, `hmac_secret`). `VietQrImage::url` dựng ảnh QR.
- **Ledger.** `FinancialPostingService::postPlatformSubscriptionReceipt` ghi `platform_bank_cash` / `platform_subscription_revenue` cho receipt nền tảng.
- **Outbox.**
  - `OutboxService::enqueue` dùng `firstOrCreate` theo `message_key` unique, nên gọi lại không gửi lặp.
  - `OutboxDispatcher::notification` gửi in-app, và gửi mail khi `payload.channels` có `mail`.
  - `DurableNotificationService::recordOutboxNotification` hiện không truyền `channels`.
- **Báo cáo cũ.**
  - `AdminController::report/reportCsv/buildReportDataset` (dòng 346-670) trộn đặt sân, POS, hoàn tiền và phí nền tảng.
  - Lọc ngày bằng `whereBetween` gồm cả hai đầu.
  - Staff gọi được vì route nằm trong nhóm `routes/api.php:339-342`.
- **Dữ liệu nhận tiền của module 04.**
  - `owner_payment_receipts` có `amount`, `event_at`, `status` (`pending|allocated|unmatched|exception|late`), `payment_attempt_id`.
  - Allocation nối receipt với attempt. Attempt nối với đơn qua `purpose_id`.
- **Kết quả vận hành.**
  - `booking_attendances`: hoàn thành ghi `checked_out_at`. Không đến ghi `checked_in_at = now` kèm `metadata.operational_status = no_show`.
  - Module 2 kết thúc đơn đã xác nhận do sự cố bằng `cancelled` với `metadata.release_reason = operator_affected_booking`.
  - `reservations` có `confirmed_at` và `cancelled_at`.
- **Phân quyền có sẵn.** Middleware `RequireOrganizationRole:organization_owner` cho owner, `admin` cho platform admin.
- **Demo.**
  - `OrganizationVenueSeeder` seed plan và gói theo tổ chức.
  - Tài khoản `@demo.auracourt.vn` bị `SimulateDemoMutations` rollback mọi lệnh ghi.
  - Prod có `MAIL_MAILER=smtp`, `QUEUE_CONNECTION=database`. Migration prod chạy tay, còn reseed thay toàn bộ DB từ bản seed local (memory `bt-server-demo-reseed`).
- **Change song song.**
  - `align-module-02-venue-court` giữ "Unified public and new-booking eligibility" (còn ghi "valid plan/trial") và thứ tự lock bắt đầu bằng `platform_subscriptions`. Task tạo cơ sở của họ kiểm hạn mức cơ sở theo gói.
  - `align-module-03-pricing-promotions` đòi "the organization's current business entitlement" khi công bố phiên bản bảng giá và khi tạo/sửa chương trình khuyến mãi. Test của họ có ca "gói hết hạn → không tạo".
  - `align-module-04-booking` giữ dữ liệu nhận tiền và màn ngoại lệ thanh toán.

## Goals / Non-Goals

**Goals:**
- Gói theo cơ sở với bốn kỳ hạn cố định, giá theo lịch sử, yêu cầu thanh toán 10 phút, kích hoạt đúng điều kiện, chấm dứt ngay. Cutover sạch, không giữ bảng hay endpoint cũ.
- Thanh toán phí chạy thật trên prod: SePay vào tài khoản AuraCourt, đúng đường webhook nạp ví đang chạy.
- Kiểm gói luôn bật cho mọi đường nhận đặt mới, không còn cờ.
- Owner có tài chính đúng năm chỉ số của report. Admin có màn phí nền tảng. Cả hai xuất Excel.
- Demo dùng được ngay sau reseed: mọi cơ sở có gói, có một cơ sở sắp hết hạn và có lịch sử yêu cầu.

**Non-Goals:**
- Hoàn tiền, chi phí vận hành, chia tiền (BR-118). POS không vào báo cáo của owner (FR-95 chỉ có năm chỉ số).
- Huỷ yêu cầu thanh toán phí (BR-95). Voucher cho phí gói (BR-93).
- Màn riêng cho giao dịch phí không mang mã nào khớp: được lưu để đối chiếu tay, report không có UC cho nó.
- Ghi nhận không đến hay kết thúc do sự cố: module 05 chỉ đọc kết quả của module 2 và 4.
- Tự gia hạn. Report không có, và `auto_renew` bị bỏ.

## Decisions

### D1. Gói theo cơ sở, cutover sạch
- Chọn: xoá bốn bảng `platform_*` và tạo bốn bảng mới.
  - `package_prices`:
    - Cột: `term_days`, `amount` (VND nguyên, > 0), `effective_at`, `replaced_at` (null), `created_by`, `created_at`.
    - Index `(term_days, effective_at)`.
  - `venue_packages`: mỗi dòng là một lần mua.
    - Cột: `organization_id`, `venue_id`, `term_days`, `starts_at`, `ends_at`, `terminated_at`, `terminated_by`.
    - Gia hạn cập nhật `ends_at` của chính dòng đó. Gói hiện tại của cơ sở là dòng có id lớn nhất.
    - Tình trạng tại `t` suy ra, không lưu cột:
      - không có dòng → `none`;
      - `terminated_at` khác null → `terminated`;
      - `starts_at ≤ t < ends_at` → `active`;
      - còn lại → `expired`.
  - `package_payment_requests`:
    - Mã: `request_code` (unique, `PKR` + 8 ký tự) và `payment_code` (unique, `PKG` + 8 ký tự chữ hoa và số).
    - Phạm vi và nội dung: `organization_id`, `venue_id`, `kind` (`purchase|renewal`), `term_days`, `amount`, `package_price_id`.
    - Trạng thái: `expires_at`, `status` (`pending|paid|expired`), `paid_at`.
    - Kết quả: `venue_package_id`, `period_starts_at`, `period_ends_at`, `created_by`.
    - Unique một phần `(venue_id) WHERE status = 'pending'`.
  - `package_payment_receipts`:
    - Giao dịch: `provider` (`sepay`), `provider_transaction_id` (unique), `payment_code`, `package_payment_request_id` (null), `amount`, `content`.
    - Thời điểm: `transferred_at`, `received_at`.
    - Kết quả: `outcome` (`applied|late|amount_mismatch|unknown_code|already_paid|term_conflict`), `raw`.
- Phương án khác:
  - Thêm `venue_id` vào `platform_subscriptions`. Loại: giữ lại plan, quota, trial và `auto_renew` đã chết, mà mọi đường đọc vẫn phải viết lại.
  - Mỗi lần gia hạn một dòng gói mới. Loại: gói hiện tại phải ghép nhiều dòng, còn lịch sử gia hạn đã có ở `package_payment_requests` (`period_*`).
- Lý do: BR-91, BR-92, D50 archive về trạng thái suy ra. Prod chỉ có dữ liệu demo đã backup và sẽ reseed (D16).

### D2. Kỳ hạn cố định
- Chọn: enum `PackageTerm` int-backed `30|90|180|360`.
  - `ends_at = base + d × 24h`, tính theo UTC bằng `addDays`. UTC không có giờ mùa hè, nên một ngày luôn đúng 24 giờ.
  - Request ngoài bốn giá trị → 422.
- Phương án khác: giữ `addMonthsNoOverflow`. Loại: trái BR-92.
- Lý do: BR-92, FR-82.

### D3. Giá gói theo lịch sử
- Chọn:
  - `POST admin/platform-fees/prices` nhận `{term_days, amount, effective_at?}`.
  - `effective_at` rỗng hoặc ≤ now → bằng now.
  - Trong một transaction:
    - đặt `replaced_at = now` cho các dòng cùng kỳ có `effective_at > now` và `replaced_at` null;
    - chèn dòng mới;
    - sau commit, ghi outbox `package.price.{price_id}.{user_id}` cho mọi owner đang hoạt động, kênh `database` + `mail`. Nội dung gồm kỳ hạn, giá mới và thời điểm áp dụng.
  - Giá áp dụng của kỳ `d` tại `t`: dòng có `replaced_at` null hoặc `> t`, `effective_at ≤ t`, lấy `effective_at` lớn nhất rồi id lớn nhất.
  - Owner và admin cùng thấy giá đang áp dụng và giá đã lên lịch tiếp theo của từng kỳ.
  - Kỳ chưa có giá áp dụng → không tạo được yêu cầu (`PACKAGE_PRICE_UNAVAILABLE`, D4).
- Phương án khác:
  - Giữ mọi giá đã lên lịch. Loại: admin không sửa được giá đặt nhầm vì không có thao tác xoá.
  - Thêm thao tác huỷ giá đã lên lịch. Loại: thêm endpoint, trong khi một quy tắc thay thế là đủ và giống module 3.
- Lý do: BR-93, BR-94, FR-83, FR-84. Yêu cầu chốt `amount` và `package_price_id` lúc tạo, nên giá mới không đổi yêu cầu cũ hay gói đã trả.

### D4. Tạo và mở lại yêu cầu thanh toán
- Chọn: `POST partner/organizations/{org}/venues/{venue}/package-requests` nhận `{term_days}`. Chỉ owner gọi được.
  - Khoá dòng `venues` của cơ sở. Đây là lock chung của mọi thay đổi gói (D6, D8).
  - Cơ sở phải thuộc tổ chức, nếu không → 404. Cơ sở đã đóng vĩnh viễn → 422, vì không bao giờ nhận đặt lại được.
  - Yêu cầu `pending` của cơ sở:
    - đã quá `expires_at` → đặt `expired` rồi đi tiếp;
    - còn hạn, cùng kỳ → trả lại chính yêu cầu đó (200), không đổi tiền hay hạn;
    - còn hạn, khác kỳ → 409 `PACKAGE_REQUEST_PENDING`, kèm yêu cầu đang chờ.
  - Gói tại now:
    - `none`, `expired`, `terminated` → `kind = purchase`, kỳ nào cũng được;
    - `active` → `kind = renewal`, kỳ phải bằng kỳ đang dùng, nếu không → 409 `PACKAGE_TERM_LOCKED`.
  - Lấy giá áp dụng tại now (D3), không có → 422 `PACKAGE_PRICE_UNAVAILABLE`. `expires_at = now + 10 phút`; 10 phút là hằng số trong code.
  - Response 201: mã yêu cầu, mã thanh toán, loại, kỳ, số ngày, số tiền, `expires_at`, tài khoản AuraCourt (ngân hàng, số tài khoản, tên) và ảnh QR từ `VietQrImage::url` với nội dung bằng mã thanh toán.
  - Thiếu `services.sepay.account` → 503, không tạo yêu cầu.
- `GET partner/organizations/{org}/package-requests/{code}` mở lại yêu cầu với cùng dữ liệu và trạng thái hiện tại.
- Phương án khác:
  - Cho nhiều yêu cầu chờ trên một cơ sở. Loại: hai khoản cùng trả sẽ cộng hai lần hoặc xung đột kỳ hạn.
  - Thêm thao tác huỷ. Loại: BR-95 nói không có.
- Lý do: BR-95, BR-99, BR-100, BR-115, FR-85, FR-86, FR-90.

### D5. Thanh toán qua SePay của AuraCourt
- Chọn: thêm nhánh `PKG` ở đầu `ProcessQrPayment::execute`, sau khi đọc id giao dịch và trước mọi nhánh nạp ví.
  - Nội dung khớp `/\bPKG([A-Z0-9]{8})\b/i` → gọi `ApplyPackagePayment` rồi `return`, không tạo `Payment` hay `PaymentIntent`.
  - Chống trùng bằng `package_payment_receipts.provider_transaction_id` unique.
  - Xác thực webhook giữ nguyên ở `SepayWebhookController`.
- Phương án khác:
  - PayOS qua `ProviderEventHandler`. Loại: chưa cấu hình, đang chạy sandbox. Thêm credential mới trước demo là rủi ro, trong khi SePay đã chạy cho nạp ví.
  - Endpoint webhook riêng. Loại: SePay chỉ gọi một URL cho mỗi tài khoản, và tài khoản đó đã trỏ về `/api/webhooks/sepay`.
- Lý do: BR-90, FR-86, FR-87, và requirement "Separate platform subscription receiver" (tài khoản riêng của AuraCourt cùng namespace `PKG`).

### D6. Kích hoạt và gia hạn
- Chọn: `ApplyPackagePayment` chạy trong một transaction.
  1. Chèn receipt. Trùng `provider_transaction_id` thì bỏ qua và trả thành công.
  2. Tìm yêu cầu theo mã thanh toán và khoá dòng `venues`, rồi khoá yêu cầu.
  3. Thời điểm trả = `transactionDate` của SePay, parse theo giờ VN như `OwnerPaymentAttemptService:189`. Thiếu thì dùng now.
  4. Xét `outcome` theo thứ tự:
     - không có yêu cầu → `unknown_code`;
     - yêu cầu đã `paid` → `already_paid`;
     - thời điểm trả ≥ `expires_at` → `late`. Yêu cầu còn `pending` thì chuyển `expired`;
     - số tiền khác `amount` → `amount_mismatch`;
     - gói đang `active` mà khác kỳ → `term_conflict`;
     - còn lại → `applied`.
  5. Khi `applied`:
     - Gói `active` cùng kỳ: `ends_at += d`.
     - Gói `none`, `expired` hoặc `terminated`: tạo dòng mới với `starts_at = thời điểm trả`, `ends_at = thời điểm trả + d`.
     - Ghi `status = paid`, `paid_at`, `venue_package_id`, `period_starts_at`, `period_ends_at` vào yêu cầu.
     - Ghi ledger: `postPackageFeeReceipt` thay `postPlatformSubscriptionReceipt`, cùng tài khoản `platform_bank_cash` / `platform_subscription_revenue`, `event_id = package-fee-receipt:{id}`.
     - Sau commit, báo owner in-app.
  6. Outcome khác: chỉ lưu receipt, không đổi gói hay yêu cầu (trừ việc chuyển `expired` ở bước `late`).
- Gia hạn thì `period_starts_at` bằng `ends_at` cũ, còn mua mới thì bằng thời điểm trả.
- Phương án khác:
  - `abort(422)` khi lệch tiền như code cũ. Loại: SePay thử lại, còn giao dịch không được lưu để đối chiếu.
  - Tính "còn hạn" theo lúc webhook tới. Loại: BR-96 xét thời điểm giao dịch được ghi nhận.
- Lý do: BR-96..BR-100, BR-102, BR-114, FR-87, FR-88, FR-90. Kích hoạt không đổi trạng thái hay đình chỉ của cơ sở.

### D7. Hết hạn yêu cầu
- Chọn:
  - Tình trạng hiển thị suy ra lúc đọc: `pending` mà quá `expires_at` thì hiện "Hết hạn thanh toán".
  - Job mỗi phút `packages:expire-requests` ghi `expired` cho các yêu cầu đó.
  - D4 và D6 cũng tự chuyển yêu cầu quá hạn của cơ sở dưới lock, nên kết quả đúng kể cả khi job trễ.
- Phương án khác: chỉ dùng job. Loại: job trễ thì cơ sở bị kẹt 409 và màn hình sai.
- Lý do: BR-95, BR-115, FR-88.

### D8. Chấm dứt trước hạn
- Chọn: `POST partner/organizations/{org}/venues/{venue}/package/terminate` nhận `{confirm_consequences: true}`. Chỉ owner gọi được.
  - Thiếu xác nhận → 422 `TERMINATION_NOT_CONFIRMED`.
  - Khoá dòng `venues`. Gói không `active` → 409 `PACKAGE_NOT_ACTIVE`.
  - Ghi `terminated_at = now`, `terminated_by`.
  - Không đổi `ends_at`, yêu cầu, receipt, ledger hay đơn.
  - Yêu cầu gia hạn đang chờ giữ nguyên. Nếu được trả, D6 coi gói là `terminated` và tạo gói mới từ thời điểm trả, khớp BR-98.
- Phương án khác: dừng gia hạn ở cuối kỳ như code cũ. Loại: trái BR-101.
- Lý do: BR-101, FR-91. Hộp xác nhận ở frontend ghi rõ mất thời gian còn lại, không hoàn tiền, không chuyển sang gói mới.

### D9. Cổng nhận đặt luôn bật
- Chọn: `VerifyEffectiveBookingEligibility` kiểm gói của chính cơ sở của sân tại now.
  - `getEntitlement(int $orgId)` đổi thành `lockVenuePackage(int $venueId)`: khoá dòng gói hiện tại của cơ sở (nếu có) và trả tình trạng.
  - Bỏ `partner.enforce_subscription_check`, nhánh shadow và mọi test của cờ.
  - Lỗi khi không có gói `active`: giữ mã chặn eligibility hiện có, lý do `package_inactive`.
  - Thứ tự lock toàn cục đổi bước đầu từ `platform_subscriptions` sang `venue_packages`, các bước sau giữ nguyên.
  - `ReservationService::confirm` và luồng thanh toán đơn đang giữ không kiểm gói (BR-104). Thêm test khoá hành vi này.
  - Không giới hạn ngày chơi theo `ends_at` của gói.
  - `VenueFactory` mặc định tạo gói `active` 360 ngày, có state `withoutPackage()`. Test cũ của đường đặt không phải sửa từng file.
- Phương án khác:
  - Giữ cờ và bật trên prod. Loại: vẫn còn hai đường code, và người dùng muốn bật hẳn.
  - Kiểm theo tổ chức. Loại: trái BR-91.
- Lý do: BR-91, BR-103, BR-104, FR-92.

### D10. Gói chỉ chặn nhận đặt mới
- Chọn: không kiểm gói ở quản lý cơ sở, sân, giá, khuyến mãi, nhân sự, vận hành đơn đã xác nhận, tài chính hay mua/gia hạn/chấm dứt.
  - Bỏ `EnforcePlanQuota` và mọi quota.
  - `EvaluateVenueReadiness` và `RestorePartner` bỏ phần đọc gói.
- Phương án khác: giữ cổng gói ở công bố bảng giá và tạo/sửa khuyến mãi như spec module 3. Loại:
  - BR-103 nêu gói là điều kiện nhận đặt mới, và vẫn cho owner thao tác khi gói hết hạn.
  - Module 2 đã bỏ plan/trial khỏi điều kiện mở cơ sở và sân.
  - Owner cần chuẩn bị giá trước khi gia hạn.
- Lý do: BR-103, FR-92. Cần module 3 bỏ câu "business entitlement" (mục Phối hợp).

### D11. Nhắc hết hạn
- Chọn: job mỗi phút `packages:send-expiry-reminders`.
  - Duyệt các gói chưa chấm dứt có `ends_at` trong khoảng `(now − 1 ngày, now + 7 ngày]`.
  - Mốc đã tới: `ends_at − 7 ngày`, `−3`, `−1` và `ends_at`. Chỉ gửi mốc nhỏ nhất đã tới, để gói tạo gần hạn không nhận dồn nhiều thông báo cũ.
  - Ghi outbox key `package.reminder.{package_id}.{ends_at_unix}.{offset}`, kênh `database` + `mail`. Người nhận là các owner đang hoạt động của tổ chức.
  - Nội dung: cơ sở, kỳ hạn, thời điểm hết hạn theo giờ VN, đường dẫn `/partner/subscription`.
  - `recordOutboxNotification` nhận thêm tham số `channels`.
  - Gia hạn đổi `ends_at`, nên key theo hạn cũ không bao giờ được tạo nữa. Gói `terminated` bị loại khỏi truy vấn.
- Phương án khác: bảng `package_reminders`. Loại: outbox đã có `message_key` unique và đã gửi được in-app lẫn mail.
- Lý do: BR-105, FR-93.

### D12. Tài chính của chủ cơ sở
- Chọn: hai endpoint chỉ owner gọi được, phạm vi là các cơ sở của tổ chức.
  - `GET partner/organizations/{org}/finance/transactions?venue_id&from&to&page`:
    - Liệt kê `owner_payment_receipts` theo `event_at` (thiếu thì `received_at`).
    - Mỗi dòng có đơn liên quan (qua allocation, nếu không có thì qua attempt), Pᵢ, Aⱼ, thời điểm và kết quả đối chiếu (trạng thái receipt).
    - Δ = Aⱼ − Pᵢ chỉ khi xác định được đơn; không gán đơn theo số tiền.
  - `GET partner/organizations/{org}/finance/report?venue_id&court_id&from&to`:
    - `from`, `to` là ngày VN, đổi sang khoảng UTC `[from 00:00, to + 1 ngày 00:00)`.
    - Pᵢ = `reservations.total_amount`.
    - Năm chỉ số, mỗi chỉ số có tổng tiền và số mã đơn duy nhất:
      - R: receipt `allocated` có thời điểm ghi nhận trong kỳ, cộng `amount`.
      - H: đơn có attendance `completed` với `checked_out_at` trong kỳ.
      - U: đơn có `confirmed_at` trong kỳ, đang `confirmed` và chưa có attendance `completed` hay no-show (gồm cả đang sử dụng).
      - N: đơn no-show có `checked_in_at` trong kỳ (thời điểm ghi nhận).
      - S: đơn `cancelled` có `confirmed_at` khác null, `release_reason = operator_affected_booking`, `cancelled_at` trong kỳ.
    - Đơn 0đ vẫn được đếm. Không có tổng doanh thu.
    - Response có thêm danh sách chi tiết theo từng chỉ số.
  - Không có POS, hoàn tiền hay phí nền tảng.
- Phương án khác:
  - Sửa `AdminController::report`. Loại: file 670 dòng trộn nhiều nguồn, và định nghĩa của nó trái BR-109..BR-113.
  - U theo `paid_at` của receipt. Loại: đơn 0đ không có receipt. `confirmed_at` là lúc thanh toán hợp lệ cho mọi đơn.
- Lý do: BR-89, BR-106..BR-113, BR-118, FR-94, FR-95.

### D13. Xoá báo cáo cũ
- Chọn:
  - Pha 0: sửa khối kiểm quyền trong `AdminController` (dòng 551-561) để chặn staff ngay.
    - Người không phải platform admin phải có membership owner đang hoạt động, nếu không → 403.
    - Phạm vi chỉ gồm các cơ sở của tổ chức mà người gọi là owner (`getAuthorizedVenueIds($orgId)` cho từng tổ chức đó).
    - Không dùng `RequireOrganizationRole`: nhóm `admin` không có tenant context, nên middleware trả 400 cho owner.
  - Pha 6: xoá ba route và `report/reportCsv/buildReportDataset`, sau khi dashboard admin của platform admin đã đọc tóm tắt phí nền tảng (D15). Owner dùng `/admin/finance` (Pha 5).
- Phương án khác: giữ báo cáo cũ song song. Loại: hai con số khác nhau cho cùng câu hỏi.
- Lý do: BR-89, BR-90, BR-108, BR-113.

### D14. Xuất Excel
- Chọn: A, người dùng duyệt ngày 2026-10-22 (Q1), với điều kiện image prod có `ext-zip`.
  - **A. `openspout/openspout` ở backend** (khuyến nghị).
    - Mỗi export là một endpoint `…/export` dùng lại đúng query của màn hình.
    - Ghi stream, có sheet "Tổng hợp" và "Chi tiết".
    - Đầu file ghi tên báo cáo, phạm vi, khoảng thời gian và thời điểm xuất (giờ VN).
    - Cần `ext-zip`: task 5.1 kiểm `php -m` trên image `frankenphp:latest` trước khi cài.
  - **B. `write-excel-file` ở frontend.** Không phụ thuộc image. Nhưng phải tải hết các trang, và quyền chỉ được bảo đảm bởi API.
  - C. Tự viết xlsx. Không khuyến nghị.
- Lý do: BR-117, FR-96, FR-100. Memory `new-dependency-approval` buộc hỏi trước khi thêm package.

### D15. Quản trị phí nền tảng
- Chọn: các controller sau, mỗi controller tối đa 7 method, nhóm middleware `admin`.
  - `PlatformFeePriceController`: `index`, `store`.
  - `PlatformFeeRequestController`: `index`, `show`, `export`.
    - Lọc theo trạng thái, cơ sở, kỳ, khoảng ngày.
    - Chi tiết có các receipt và outcome.
  - `PlatformFeeVenueController`: `index`, `show`, `export`.
    - Lọc theo tình trạng gói và nhóm sắp hết hạn 7/3/1 ngày theo BR-116.
    - Tổng số cơ sở sắp hết hạn đếm theo id duy nhất.
  - `PlatformFeeReportController`: `show`, `export`.
    - Phí đã thu và số lượt mua/gia hạn từ yêu cầu `paid`, theo `paid_at` trong kỳ.
    - Nhóm theo cơ sở và kỳ, dùng `amount` của yêu cầu.
    - Gói đã chấm dứt vẫn được tính.
- Frontend `/admin/platform-fees` có bốn tab: Giá gói, Yêu cầu thanh toán, Tình trạng gói, Báo cáo thu phí.
- Lý do: BR-114..BR-117, FR-82, FR-83, FR-97..FR-100.

### D16. Demo trên prod
- Chọn:
  - Seeder mới thay phần plan/gói của `OrganizationVenueSeeder`:
    - bốn giá;
    - mọi cơ sở có gói `active`, trong đó một cơ sở còn ≤ 3 ngày;
    - một cơ sở có gói đã chấm dứt;
    - mỗi cơ sở có một yêu cầu `paid` có receipt `applied`;
    - một yêu cầu `expired` có receipt `late`.
  - Thử thanh toán thật bằng tài khoản owner không phải `@demo.auracourt.vn`, vì tài khoản demo bị rollback lệnh ghi. Admin có thể đặt giá demo thấp (ví dụ 10.000đ) trước buổi trình bày.
  - Trước deploy, người dùng xác nhận các giá trị `SEPAY_*` trên prod, URL webhook trên dashboard SePay và scheduler/worker đang chạy (Q2).
  - Reseed theo quy trình có backup. Deploy backend bằng `glab ci run -b main` theo giờ đã hẹn.
- Lý do: yêu cầu "bật hết để trình bày demo" của người dùng, BR-103.

### Phối hợp và thứ tự archive
- Thứ tự archive: `direct-owner-cutover` → `align-module-02-venue-court` → `align-module-03-pricing-promotions` → `align-module-04-booking` → change này.
- Khối MODIFIED "Role-based owner operations" (`organization-access`) dựng trên text của module 3. "Real staff invitation and permission lifecycle" và "Report-aligned end-to-end contracts" dựng trên spec chính. Trước khi archive, dựng lại các khối này từ text đang có ở spec chính (task 8.4).
- Nhờ module 2 (gửi ở task 1.2):
  - "Unified public and new-booking eligibility": "valid plan/trial" → "an active package for the court's venue", ở cả câu chính lẫn câu về mua lại slot.
  - "Venue opening conditions": "the platform plan or trial" → "the venue package".
  - Bỏ hạn mức cơ sở khỏi "Single organization-scoped venue write path" và khỏi task tạo cơ sở.
  - Thứ tự lock: `venue_packages` thay `platform_subscriptions`.
- Nhờ module 3:
  - Bỏ câu "SHALL require the organization's current business entitlement" ở "Price table versions and effective time" và "Venue promotion programs", cùng scenario "Expired business entitlement".
  - Bỏ ca "gói hết hạn → không tạo" ở `PriceTableManagementTest`, `PriceTableVersionTest` và `PromotionProgramManagementTest`.
- Module 4: module 05 chỉ đọc `owner_payment_receipts`, allocation và attempt, không đổi schema hay endpoint của họ.
- Nếu một change kia archive trước khi kịp sửa: task 8.4 thêm khối MODIFIED tương ứng vào change này, dựng từ text ở spec chính, chỉ đổi đúng các câu trên.
- Code: change này xoá `EvaluatePlatformEntitlement` và `getEntitlement`. Branch nào của module 2/3 còn gọi tới thì phải đổi khi merge `origin/main`. Báo trước ở task 1.2.

## Risks / Trade-offs

- **[Cửa sổ deploy]** Push lên main thì frontend tự deploy, còn backend chờ `glab ci run` và schema mới chờ reseed. Màn gói mới sẽ gọi endpoint chưa có.
  → Pha 2–6 nằm trên một nhánh `feat/module05-packages-finance`. Chỉ merge và push main ở Pha 7, theo giờ hẹn với người dùng, ngay trước reseed và `glab ci run`. Màn gói hỏng trong vài phút giữa push và lúc backend deploy xong là chấp nhận được.
- **[Migration xoá bảng trên prod]** Prod không chạy migrate tự động; journal cũ trỏ tới `PlatformProviderReceipt` bằng `source_type`.
  → Không chạy migration xoá trên prod riêng lẻ. Pha 7 thay cả DB bằng reseed sau `pg_dump`. Journal cũ chỉ là dữ liệu demo.
- **[Tiền thật vào sai luồng]** Một khoản `PKG` khớp nhầm ví, hoặc ngược lại.
  → Nhánh `PKG` chạy trước và `return`. Test đối nghịch: nội dung có cả `PKG…` và `NAP…`; mã `PKG` không tồn tại vẫn không tạo `Payment` nạp ví.
- **[Giờ giao dịch do ngân hàng ghi]** `transactionDate` sai múi giờ thì `late` bị xét lệch.
  → Parse giống module 4 (đã chạy thật). Test biên đúng `expires_at`.
- **[Owner chuyển nhầm số tiền]** Giao dịch bị lưu `amount_mismatch` mà không kích hoạt.
  → Đúng BR-96. Admin thấy trong chi tiết yêu cầu. Hoàn hay bù tiền nằm ngoài hệ thống (BR-118).
- **[Scheduler hoặc worker không chạy trên prod]** Nhắc hạn và mail không đi.
  → Hết hạn yêu cầu không phụ thuộc job (D7). Người dùng xác nhận ở Q2; smoke test kiểm một thông báo nhắc của cơ sở sắp hết hạn.
- **[Branch song song còn gọi entitlement cũ]** Merge của module 2/3 hỏng build.
  → Báo trước ở task 1.2. Ghi rõ trong mục Phối hợp.
- **[`ext-zip` không có trong image prod]** Export của phương án A lỗi 500.
  → Task 5.1 kiểm trước. Thiếu thì dùng phương án B.

## Migration Plan

1. **Pha 0:** chỉ sửa kiểm quyền của báo cáo cũ. Deploy độc lập.
2. **Pha 2–6** làm trên nhánh `feat/module05-packages-finance` (schema mới, xoá bảng cũ, endpoint mới, cổng luôn bật, tài chính, phí nền tảng):
   - Local và CI chạy `migrate:fresh`.
   - Mỗi pha qua gate (task 8.1) rồi commit trên nhánh. Nhánh merge `origin/main` khi cần, nhưng chưa vào main.
   - Prod giữ code và schema cũ cho tới Pha 7.
3. **Pha 7:**
   - `pg_dump` prod.
   - Merge `--no-ff` nhánh vào main theo giờ đã hẹn.
   - Seed local bằng `.env.btprod`, rồi restore lên prod theo quy trình reseed.
   - Chạy `glab ci run -b main` và chờ deploy xong.
   - Smoke test: mua gói giá thấp bằng tài khoản owner thật, xem gói kích hoạt, xem báo cáo, xuất Excel.
4. **Rollback:**
   - Restore bản `pg_dump` vừa lấy.
   - Revert commit merge trên main và push (frontend tự deploy lại), rồi `glab ci run -b main` cho backend.
   - Không có đường lùi một phần, vì schema cũ đã bị xoá.

## Open Questions

- **Q1 (đã chốt 2026-10-22):** người dùng chọn A (`openspout/openspout`), kiểm `ext-zip` trên image prod trước khi cài (task 5.1). Nếu image không có `ext-zip` thì báo lại người dùng trước khi đổi phương án.
- **Q2 (giá trị bên ngoài):** người dùng xác nhận trên prod:
  - `SEPAY_ACCOUNT`, `SEPAY_BANK`, `SEPAY_ACCOUNT_NAME` là tài khoản AuraCourt thật. `devops/env/booking-api.prod.env` không có `SEPAY_ACCOUNT_NAME`, nên tên tài khoản đang rơi về giá trị mặc định ở `config/services.php:37` nếu Portainer cũng không đặt;
  - `SEPAY_API_KEY` hoặc `SEPAY_HMAC_SECRET` khớp dashboard SePay, và webhook trỏ tới `https://court-api.ziet.dev/api/webhooks/sepay`;
  - scheduler (`schedule:run` mỗi phút) và queue worker đang chạy trong stack.
- Không còn câu hỏi nghiệp vụ mở. POS trong báo cáo owner đã chốt là không (Non-Goals).
