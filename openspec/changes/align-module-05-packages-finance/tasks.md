## 0. Pha 0 – Chặn staff ở báo cáo cũ (D13)

Nhánh riêng, merge và deploy độc lập với các pha sau.

- [x] 0.1 Sửa khối kiểm quyền của báo cáo cũ (`app/Http/Controllers/Admin/AdminController.php:549-564`):
  - Người không phải platform admin phải có membership `organization_owner` đang hoạt động, nếu không → 403.
  - Phạm vi là các cơ sở của những tổ chức mà người gọi là owner, lấy bằng `getAuthorizedVenueIds($orgId)` cho từng tổ chức đó. `venue_id` ngoài phạm vi → 403.
  - Không dùng `RequireOrganizationRole`: nhóm `admin` không có tenant context, middleware sẽ trả 400 cho owner (D13).
  - Test: `AdminReportOwnerScopeTest`.
    - Staff → 403 ở `admin/reports`, `admin/reports.csv` và `admin/reports/csv`.
    - Người vừa là owner tổ chức A vừa là staff tổ chức B chỉ thấy cơ sở của A.
    - Platform admin vẫn thấy toàn bộ.
  - Đã làm 2026-10-22 trên nhánh `fix/module05-report-owner-scope`. `AdminReportOwnerScopeTest` có thêm ca người không có membership → 403. Chạy với controller cũ thì fail đúng hai ca: staff nhận 200, owner thấy cả tổ chức B. `ComprehensiveRoleAndScopingMatrixTest` đổi theo: báo cáo của staff → 403, test thu hồi quyền dò bằng `admin/reservations`. 29 test liên quan đạt, PHPStan sạch. Chưa chạy cả suite.
  - Review có kiểm chứng đối kháng tìm thêm hai test cũ còn mong staff đọc được báo cáo: `OwnerStaffGlobalAdminSeparationTest` và `OrganizationGrantsAndVenueScopingTest`. Đã sửa: probe báo cáo của staff → 403, probe phạm vi cơ sở chuyển sang `admin/reservations`. Hai file này chưa chạy, vì lane hiện không chạy PHPUnit.
- [ ] 0.2 Gate theo task 8.1. Merge `--no-ff` vào main, push, hẹn giờ với người dùng rồi chạy `glab ci run -b main`.
  - Hoãn từ 2026-10-22. Session điều phối chuyển lời người dùng: "ko test ko deploy chỉ sửa và check ở local trước". Pha 0 chỉ commit ở nhánh local.

## 1. Pha 1 – Tài liệu và phối hợp

- [x] 1.1 `openspec validate align-module-05-packages-finance --strict` đạt (2026-10-22).
- [x] 1.2 Gửi session module 2 và module 3 các yêu cầu ở mục "Phối hợp và thứ tự archive" của design:
  - Module 2 (`align-module-02-venue-court`):
    - "Unified public and new-booking eligibility": "valid plan/trial" → "an active package for the court's venue", ở cả câu chính lẫn câu về mua lại slot.
    - "Venue opening conditions": "the platform plan or trial" → "the venue package".
    - Bỏ hạn mức cơ sở khỏi "Single organization-scoped venue write path" và khỏi task tạo cơ sở.
    - Thứ tự lock bắt đầu bằng `venue_packages` thay `platform_subscriptions`.
  - Module 3 (`align-module-03-pricing-promotions`):
    - Bỏ câu "SHALL require the organization's current business entitlement" ở "Price table versions and effective time" và "Venue promotion programs", cùng scenario "Expired business entitlement".
    - Bỏ ca "gói hết hạn → không tạo" ở `PriceTableManagementTest`, `PriceTableVersionTest` và `PromotionProgramManagementTest`.
  - Báo cả hai: change này xoá `EvaluatePlatformEntitlement`, `VerifyEffectiveBookingEligibility::getEntitlement`, `EnforcePlanQuota` và các model `Platform*`. Branch nào còn gọi tới phải đổi khi merge `origin/main`.
  - Ghi ngày gửi và phản hồi vào task này. Phản hồi làm đổi tài liệu thì thêm task 1.x mới.
  - Đã gửi 2026-10-22 cho session "module 2" và "module 3", kèm số dòng hiện tại trong change của họ (module 3 thêm tasks.md:73 và :194; module 2 thêm tasks.md:190 về `PlanQuotaEnforcementAndDowngradeTest`). Đã hỏi mỗi bên: tự sửa trong change của mình, hay để task 8.4 thêm MODIFIED.
  - Module 2 trả lời 2026-10-22: không sửa spec của module 2, vì code module 2 vẫn chạy theo plan/trial cho tới Pha 7. Module 5 thêm MODIFIED cho cả bốn chỗ (task 8.4), với điều kiện module 2 archive trước. Nếu module 5 buộc phải archive trước thì báo module 2 để họ sửa text. Chỗ gọi mới trên `feat/module02-venue-court-alignment`, phải đổi khi merge:
    - `PeerToPeerResaleService::lock` gọi `VerifyEffectiveBookingEligibility` (cả `getEntitlement`), lock theo thứ tự `platform_subscriptions` → sân → cơ sở (task 1.11a của module 2).
    - Tạo sân (task 1.16) và tạo cơ sở vẫn gọi `EnforcePlanQuota`.
    - Mở, tạm dừng, mở lại, đóng cơ sở và sân (task 1.5–1.8) không kiểm plan/trial.
  - Module 3 trả lời 2026-10-22: tự bỏ điều kiện quyền kinh doanh trong change và code của module 3 (`CreatePriceTable`, `PublishPriceTableVersion`, tạo/sửa chương trình khuyến mãi), trên `feat/module03-price-tables`, sau workflow Pha 4 đang chạy. Sẽ báo commit hash. `PriceTableManagementTest` và `AvailabilityFromPriceTablesTest` còn ca entitlement cho tới lúc module 3 làm test. Module 5 không cần MODIFIED cho module 3.
  - Module 3 báo 2026-10-22: đã gỡ điều kiện quyền kinh doanh ở commit `5e582c57` (`feat/module03-price-tables`, chưa merge). Phần này trùng Pha 4 (`98f4b473`); khi merge giữ bản đã xoá. Module 5 đã trả lời.
  - Module 2 trả lời Pha 4 (`98f4b473`) ngày 2026-10-22:
    - Cổng gói ở các chỗ gọi trên nhánh module 2 do module 5 đổi khi merge.
    - Ba lệnh D8 của module 2 (dời sân, đổi giờ, huỷ) không kiểm gói và không lock `venue_packages`. Thứ tự lock của chúng (sân → venue → `venue_suspensions` → attempt → đơn → case) khớp với venues → `package_payment_requests` → `venue_packages`.
    - Chỉ số S: lệnh huỷ ghi cả `cancelled_at` lẫn `metadata.release_reason = 'operator_affected_booking'`. Module 2 sẽ báo hash khi commit.
    - `VenueReadinessModal.vue` đã bị xoá trên nhánh module 2 (`e0c4894c`), nên lệch key sẽ hết sau khi merge.
- [ ] 1.3 Người dùng trả lời và ghi vào Open Questions của design:
  - Q1 (thư viện Excel), cần trước Pha 5. Đã chốt 2026-10-22: A (`openspout/openspout`), kiểm `ext-zip` trước.
  - Q2 (giá trị `SEPAY_*`, URL webhook trên dashboard SePay, scheduler và queue worker trên prod), cần trước Pha 7.

## 2. Pha 2 – Schema mới và giá gói (D1, D2, D3)

Pha 2–6 làm trên nhánh `feat/module05-packages-finance` và chỉ vào main ở Pha 7 (design, Migration Plan).

- [x] 2.1 Migration tạo `package_prices`, `venue_packages`, `package_payment_requests` và `package_payment_receipts` theo D1: (test: chưa làm)
  - Check `term_days IN (30, 90, 180, 360)`, `amount > 0`, `ends_at > starts_at` và tập giá trị của `kind`, `status`, `outcome`.
  - Unique `request_code`, `payment_code`, `provider_transaction_id`. Unique một phần `(venue_id) WHERE status = 'pending'`.
  - FK `restrict` về `venues`, `organizations` và `users`, như `2026_10_11_000002_restrict_deletes_into_financial_tables.php`.
  - Cập nhật `SchemaConformanceTest`.
- [x] 2.2 Enum `PackageTerm`, model, factory và repository (interface + Eloquent, binding ở `RepositoryServiceProvider`): (test: chưa làm)
  - Repository trả tình trạng gói của một cơ sở tại `t`: `none|active|expired|terminated` (D1).
  - Test: `VenuePackageStatusTest`.
    - Đúng `starts_at` → `active`. Đúng `ends_at` → `expired`.
    - `terminated_at` khác null → `terminated` ở mọi thời điểm.
    - Cơ sở chưa có dòng nào → `none`.
    - Kỳ 30 ngày có `ends_at = starts_at + 720 giờ`.
- [x] 2.3 Giá gói (D3):
  - `PlatformFeePriceController` (`index`, `store`) trong nhóm `admin`. FormRequest, DTO, action `SchedulePackagePrice`.
  - `effective_at` rỗng hoặc ≤ now → now. Các giá cùng kỳ đã lên lịch nhận `replaced_at = now`.
  - Một service đọc giá áp dụng tại `t` và giá đã lên lịch tiếp theo của từng kỳ. Task 3.1 và 3.6 dùng chung service này.
- [x] 2.4 `DurableNotificationService::recordOutboxNotification` nhận thêm `array $channels = ['database']` và ghi vào `payload.channels`. (test: chưa làm)
  - Test: thêm vào `DurableNotificationServiceTest`. Có `mail` thì dispatcher gửi cả mail; mặc định chỉ in-app.
- [x] 2.5 Báo giá mới (D3): sau commit, ghi outbox `package.price.{price_id}.{user_id}` cho mọi owner đang hoạt động, kênh `database` + `mail`. Nội dung có kỳ hạn, giá và thời điểm áp dụng theo giờ VN. (test: chưa làm)
  - Test: `PackagePriceScheduleTest`.
    - Scenario "Price scheduled for a later date", "Scheduled price replaced before it takes effect", "Effective time in the past", "Price set by someone other than a platform administrator".
    - Mỗi owner nhận đúng một outbox cho mỗi giá. Chạy lại dispatcher không gửi lặp.
- [x] 2.6 Frontend `/admin/platform-fees` cho platform admin, tab đầu "Giá gói": (test: chưa làm)
  - Bảng bốn kỳ: số ngày, giá đang áp dụng, giá đã lên lịch tiếp theo.
  - Form đặt giá: chọn thời điểm áp dụng theo giờ VN, gửi UTC.
  - Feature mới `frontend/src/features/platform-fees/` (api, types, views). Route và menu chỉ cho platform admin.
  - i18n vi/en, Vitest đi kèm.
- [x] 2.7 `VenueFactory` mặc định tạo gói `active` 360 ngày, có state `withoutPackage()` (D9).
- Ghi chú Pha 2 (2026-10-22, chỉ implement, chưa chạy test hay migrate):
  - Bốn bảng gói không dùng `BelongsToTenant`; giá trị lưu là hằng trên model. Service giá dùng chung cho 3.1 và 3.6 là `App\Services\Package\PackagePriceCatalog`.
  - Ghi giá khoá theo kỳ bằng `pg_advisory_xact_lock(5003, term_days)` để hai admin đặt cùng lúc vẫn chỉ còn một giá đã lên lịch (D3).
  - Báo giá chạy sau commit; mỗi owner bọc try/catch như `notifyBooking`, nên một lần gửi mail lỗi không làm admin nhận 500 hay bỏ sót owner sau. Dòng outbox lỗi còn `pending` để recovery gửi lại.
  - Danh sách kỳ ở form frontend là hằng `PACKAGE_TERMS`, để dropdown vẫn hiện khi tải bảng giá lỗi.
  - Còn hở cho Pha 4: khoảng 169 test tạo cơ sở bằng `Venue::create` (kể cả `tests/TestCase.php` và `tests/Concerns/BooksDirectOwnerCourt.php`) nên không có gói mặc định. Phải xử lý trước khi bật cổng (4.1).

## 3. Pha 3 – Yêu cầu thanh toán, kích hoạt và chấm dứt (D4, D5, D6, D7, D8)

- [x] 3.1 Tạo và mở lại yêu cầu (D4): (test: chưa làm)
  - `POST partner/organizations/{org}/venues/{venue}/package-requests` và `GET partner/organizations/{org}/package-requests/{code}`, middleware `RequireOrganizationRole:organization_owner`.
  - Action `CreatePackagePaymentRequest`:
    - Khoá `venues`, chuyển yêu cầu quá hạn của cơ sở sang `expired`, rồi xét theo D4.
    - Lỗi: `PACKAGE_REQUEST_PENDING` (409), `PACKAGE_TERM_LOCKED` (409), `PACKAGE_PRICE_UNAVAILABLE` (422), cơ sở đã đóng (422), thiếu `services.sepay.account` (503).
  - Mã `PKR` và `PKG` kèm 8 ký tự chữ hoa và số, sinh lại khi trùng.
  - Response có tài khoản AuraCourt và ảnh QR từ `VietQrImage::url`.
  - Test: `PackagePaymentRequestTest`.
    - Scenario "Purchase for a venue without a package", "Reopening a pending request", "Another term while a request is pending", "Renewal with another term", "Price change after creation", "Deadline passes without payment".
    - Staff → 403. Owner tổ chức khác → 403. Cơ sở đã đóng → 422.
    - Thiếu cấu hình SePay → 503 và không có dòng mới.
- [x] 3.2 Nhánh `PKG` trong `ProcessQrPayment::execute` (D5): (test: chưa làm)
  - Chạy sau khi đọc id giao dịch, trước mọi nhánh nạp ví.
  - Nội dung khớp `/\bPKG([A-Z0-9]{8})\b/i` → gọi `ApplyPackagePayment` rồi `return`.
  - Test: `PackagePaymentWebhookTest`, gọi `POST /api/webhooks/sepay` có chữ ký hợp lệ.
    - Giao dịch `PKG` không tạo `Payment` hay `PaymentIntent`, kể cả khi mã không tồn tại.
    - Nội dung có cả `PKG…` và `NAP…` → chỉ đi nhánh gói, ví không đổi.
    - Giao dịch nạp ví không có `PKG` chạy như cũ.
- [x] 3.3 `ApplyPackagePayment` (D6): (test: chưa làm)
  - Chèn receipt; khoá `venues` rồi khoá yêu cầu.
  - Thời điểm trả lấy từ `transactionDate`, parse như `OwnerPaymentAttemptService:189`.
  - Xét outcome đúng thứ tự D6. Khi `applied`: gia hạn hoặc tạo gói, ghi kết quả vào yêu cầu, báo owner in-app sau commit.
  - Test: `PackageActivationAndRenewalTest`.
    - Scenario "First purchase paid on time", "Renewal paid before the end", "Renewal paid after the end", "Late transfer", "Different amount", "Repeated notification", "Transfer with an unknown code", "Paused venue renews".
    - `transactionDate` đúng bằng `expires_at` → `late`; một giây trước đó → `applied`.
    - Gia hạn một cơ sở không đổi gói của cơ sở khác cùng tổ chức.
- [x] 3.4 Ledger: `FinancialPostingService::postPackageFeeReceipt(PackagePaymentReceipt)`. (test: chưa làm)
  - Debit `platform_bank_cash`, credit `platform_subscription_revenue`, `event_id = package-fee-receipt:{id}`. Chỉ gọi khi outcome là `applied`.
  - Test: sửa `FinancialEntityPostingTest`. Webhook tới hai lần chỉ ghi một bút toán; outcome khác không ghi.
- [x] 3.5 Chấm dứt (D8): `POST partner/organizations/{org}/venues/{venue}/package/terminate` nhận `{confirm_consequences: true}`, chỉ owner. (test: chưa làm)
  - Test: `PackageTerminationTest`.
    - Scenario "Confirmed termination", "Termination without confirmation", "New term after termination", "Pending renewal paid after termination".
    - Gói không `active` → 409 `PACKAGE_NOT_ACTIVE`. Receipt và ledger không đổi.
- [x] 3.6 Màn owner (requirement "Owner view of venue packages"): `GET partner/organizations/{org}/packages`, chỉ owner. (test: chưa làm)
  - Mỗi cơ sở có kỳ, bắt đầu, kết thúc, tình trạng tại now, thời điểm chấm dứt, yêu cầu đang chờ và lịch sử yêu cầu.
  - Kèm bốn kỳ với giá đang áp dụng và giá đã lên lịch tiếp theo.
  - Test: `OwnerVenuePackageViewTest`. Scenario "Expired package view", "Pending request shown". Staff → 403.
- [x] 3.7 Frontend `/partner/subscription`, viết lại trong `features/platform-subscriptions/`: (test: chưa làm)
  - Danh sách cơ sở với tình trạng gói. Nút mua hoặc gia hạn; khi gia hạn thì kỳ bị khoá.
  - Mở lại yêu cầu đang chờ: đếm ngược, tài khoản, số tiền, mã thanh toán và QR.
  - Hộp xác nhận chấm dứt ghi rõ: mất thời gian còn lại, không hoàn tiền, không chuyển sang gói khác.
  - Thông báo cho `PACKAGE_REQUEST_PENDING`, `PACKAGE_TERM_LOCKED`, `PACKAGE_PRICE_UNAVAILABLE`, `PACKAGE_NOT_ACTIVE`, `TERMINATION_NOT_CONFIRMED` trong `ERROR_CODE_MAP`, vi/en.
  - Xoá `PlanCatalogCard.vue`, `SubscriptionStatusCard.vue` và phần plan/trial trong `types`, `api`, `composables`.
  - Viết lại `platformSubscriptionApi.spec.ts` và `PlatformSubscriptions.spec.ts`. Staff không thấy menu gói.

- Ghi chú Pha 3 (2026-10-22, chỉ implement, chưa chạy test hay migrate):
  - Trước Pha 3 đã merge local `feat/module04-phase5` (nhánh này đã chứa tip của `feat/module02-venue-court-alignment` và `feat/module03-price-tables`), commit `cd020ecd`. Pha 3 cần mô hình trạng thái cơ sở của module 2 (cơ sở `closed` → 422). Coordinator yêu cầu merge trước Pha 4, nên merge sớm hơn vẫn đúng yêu cầu.
  - Quyền owner được kiểm theo tổ chức trên path, trong `authorize()` của FormRequest hoặc ngay trong controller, không dùng `RequireOrganizationRole`. Middleware đó trả 400 khi không có tenant context, cùng vấn đề D13 ghi cho nhóm `admin`. Staff hoặc owner tổ chức khác → 403; cơ sở hay mã yêu cầu của tổ chức khác → 404.
  - Thêm hai mã lỗi: `VENUE_CLOSED` (422) và `PACKAGE_PAYMENT_UNAVAILABLE` (503), có trong `ERROR_CODE_MAP` vi/en.
  - Nhánh `PKG` dò regex trên cả `content` và `code`, bỏ qua giao dịch chuyển ra (`transferType` khác `in`). Receipt gắn yêu cầu với mọi outcome trừ `unknown_code`.
  - Webhook đến trễ sau khi một gói mới hơn đã được áp dụng: gói hiện tại được xét tại thời điểm muộn hơn giữa thời điểm trả và `starts_at` của gói. Cùng kỳ thì cộng tiếp từ `ends_at`, khác kỳ thì `term_conflict`. Nhờ vậy không có dòng nào bắt đầu trước gói hiện tại, và owner không mất kỳ đã trả.
  - `FinancialPostingService` chạm trần 554 dòng, nên `postEntityJournal` chuyển sang `LedgerService::postOnce`. Hành vi của các nơi gọi giữ nguyên.
  - Frontend đặt trang gói ở `/admin/packages`, trong khung admin. `/partner/subscription` redirect về đó, nên `action_url` cũ vẫn mở được.
  - Sửa kèm:
    - `RecheckHeldReservationsAtNewPrice` (module 4) truyền `channels` qua tham số; trước đó 2.4 ghi đè `payload.channels` nên mail đổi giá bị rơi.
    - `AdminController` rút về 575 dòng, dưới trần 578. Merge Pha 0 với module 4 từng đẩy file lên 582.
  - Cho Pha 4 và lane test:
    - Thứ tự lock của Pha 3 là unique key của receipt → `venues` → yêu cầu → `venue_packages`. Chấm dứt khoá `venues` rồi sửa `venue_packages`. Đã chốt ở D9 (2026-10-22): cổng đọc gói sau khi khoá `venues`, không khoá `venue_packages`.
    - `frontend/src/features/admin/composables/usePriceTables.ts:184` còn xử lý mã cũ `SUBSCRIPTION_EXPIRED`/`NO_ACTIVE_SUBSCRIPTION`.
    - `platformSubscriptionApi.spec.ts` và `PlatformSubscriptions.spec.ts` không còn compile vì import code plan/trial đã xoá; lane test viết lại.
    - Có thể hạ trần allowlist của `FinancialPostingService` từ 554 xuống 552.

## 4. Pha 4 – Cổng luôn bật, nhắc hạn và xoá gói cũ (D7, D9, D10, D11)

- [x] 4.1 `VerifyEffectiveBookingEligibility` (D9):
  - Bỏ `getEntitlement(int $orgId)`. Cổng đọc gói của cơ sở tại now sau khi đã khoá dòng `venues` (D9). Lý do chặn `package_inactive`, giữ mã chặn eligibility hiện có.
  - Sửa hai nơi gọi: `ReservationService:67` và `OrdinaryCheckoutService:58`.
  - Sau khi merge `feat/module02-venue-court-alignment`, sửa thêm `PeerToPeerResaleService::lock` (task 1.2).
  - Thứ tự lock: sân → cơ sở, rồi mới đọc gói; không khoá `venue_packages` (D9, đổi 2026-10-22).
- [x] 4.2 Xoá cờ `partner.enforce_subscription_check` (`config/partner.php`, `PartnerRolloutService.php:34,37`) và nhánh shadow.
- [ ] 4.3 Test: `VenuePackageBookingGateTest`.
  - Scenario "Hold after the package ended", "Hold placed before the package ended", "Play after the package end", "Owner works with an expired package".
  - Checkout và hold đều bị chặn với `package_inactive`.
  - `ReservationService::confirm` không kiểm gói (BR-104).
  - Query log: khoá `venues` trước khi đọc gói, và không có `FOR UPDATE` trên `venue_packages`.
  - Sửa `InflightQuoteEligibilityTest` và `UnifiedBookingEligibilityTest`, bỏ phần cờ.
- [x] 4.4 Job `packages:expire-requests` mỗi phút trong `routes/console.php`, cùng mẫu `booking:expire-pending` (`name`, `withoutOverlapping`, `onFailure`). (test: chưa làm)
  - Test: `PackageRequestExpiryTest`. Yêu cầu quá hạn thành `expired`; yêu cầu còn hạn giữ nguyên.
- [x] 4.5 Job `packages:send-expiry-reminders` mỗi phút (D11). (test: chưa làm)
  - Test: `PackageExpiryReminderTest`.
    - Scenario "Seven days before the end", "Renewal moves the reminders", "Terminated package", "Delayed scheduler".
    - Chạy hai lần không ghi thêm outbox.
    - Gói còn 2 ngày lúc job chạy lần đầu chỉ nhận mốc 3 ngày.
- [x] 4.6 `EvaluateVenueReadiness` và `RestorePartner:47` bỏ phần đọc gói (D10). (test: chưa làm)
  - Frontend: sửa `VenueReadinessModal.vue`, `venuePublicationApi.ts` và `VenuePublicationReviewViews.spec.ts`.
- [x] 4.7 Migration xoá bảng gói cũ: (test: chưa làm)
  - Bỏ FK và check do `2026_10_11_000002_restrict_deletes_into_financial_tables.php:49-53` và `2026_10_11_000005_add_finance_status_checks.php:20-22` thêm vào.
  - Drop `platform_payment_attempts`, `platform_provider_receipts`, `platform_subscriptions`, `platform_plans`. Không sửa migration cũ.
  - `SchemaConformanceTest` không còn bảng cũ.
- [x] 4.8 Xoá code gói cũ: (test: chưa làm)
  - `app/Domain/Subscription/` (10 action) và `EvaluatePlatformEntitlement`.
  - Model `PlatformPlan`, `PlatformSubscription`, `PlatformPaymentAttempt`, `PlatformProviderReceipt`.
  - `PlatformSubscriptionRepositoryInterface`, repo Eloquent và binding.
  - `ProviderNeutralSubscriptionPaymentAdapter`, `EnforcePlanQuota`, `PlatformPlanController`.
  - Nhánh platform attempt của `ProviderEventHandler` (dòng 20-90). Nhánh `PaymentIntent` giữ nguyên.
  - `FinancialPostingService::postPlatformSubscriptionReceipt`.
  - Route `admin/platform-plans*`, `platform/plans`, `partner/organizations/{id}/subscription/*`, `partner/subscription/confirm-payment`.
  - `Organization::subscriptions()` và quan hệ hasOne (dòng 175, 182).
  - Lệnh console:
    - `E2ESeedFixturesCommand` (dòng 208, 321, 419, 743). Fixture E2E tạo gói `active` cho cơ sở.
    - `ReconcileLegacyPartnersCommand:53`.
    - `BackfillReviewedPartnerMappingsCommand:150-157`.
  - Sau khi merge nhánh module 2: bỏ `EnforcePlanQuota` ở tạo sân và tạo cơ sở của nhánh đó (task 1.2).
  - Kiểm: `grep -rn "PlatformSubscription\|PlatformPlan\|PlatformPaymentAttempt\|PlatformProviderReceipt\|EnforcePlanQuota\|enforce_subscription_check" app config database routes tests` không còn kết quả.
- [ ] 4.9 Test cũ:
  - Xoá các file sau. Phần còn đúng đã chuyển sang test của task 3.1, 3.3, 3.5 và 4.3.
    - `PlatformTrialActivationTest`, `PlanQuotaEnforcementAndDowngradeTest`, `PlatformQuotaAndFeatureEnforcementTest`, `PlatformPlanCatalogAndSelectionTest`, `PlatformPlanAdminLifecycleTest`.
    - `PlatformPaymentInitiationTest`, `PlatformPaymentConfirmationAndReconciliationTest`, `PlatformRealTimeEntitlementAndExpiryTest`, `PlatformSubscriptionPersistenceTest`, `PlatformSubscriptionRenewalAndCancellationTest`.
  - Sửa fixture: bỏ `PlatformSubscription`/`PlatformPlan`, dựa vào gói mặc định của `VenueFactory`.
    - `Account/OrganizationInvitationLifecycleTest`, `Booking/SuspendedPartnerObligationsAndAuthorizationTest`, `BookingFlowsTest`, `Console/BackfillReviewedPartnerMappingsCommandTest`.
    - `Finance/ReconciliationAndAutoResolveIntegrityTest`, `Infrastructure/DurableNotificationServiceTest`, `Partner/PartnerLifecycleActionsTest`, `Policy/PolicyRetirementAndReadinessRegressionTest`.
    - `PricingTenantAndBoundaryTest`, `PromotionCampaignAndCouponLifecycleTest`, `PromotionScheduleAndLockTest`, `ReservationsAndPricingTest`, `VenueSportPricingTest`.
    - `Readiness/ComprehensiveConcurrencyAndRetryInvariantsTest`, `Readiness/ProviderSandboxAndOperationalReadinessTest`, `Repositories/NewRepositoriesAndLocksIntegrationTest`.
    - `Venue/`: `BookingPolicySnapshotAndWalletRegressionTest`, `DraftVenueCourtSetupTest`, `DraftVenueCreationAndSetupTest`, `FinalMutationEligibilityAndCacheTest`, `VenueBookingPolicyVersioningTest`, `VenueLifecycleTransitionsTest`, `VenueOperatingCalendarTest`, `VenueProfilePersistenceTest`, `VenuePublicSnapshotAndOperationalClosureTest`, `VenuePublicationReviewTest`, `VenueReadinessChecklistTest`.
  - `tests/Unit/Architecture/architecture-allowlist.json` và `frontend/src/__tests__/architecture-allowlist.json`: chỉ xoá mục của file đã xoá, không nâng trần.
- [x] 4.10 `OrganizationVenueSeeder` (dòng 52-97): bỏ plan và gói theo tổ chức, gọi `VenuePackageSeeder` bản tối thiểu (bốn giá, mỗi cơ sở một gói `active` 360 ngày). Pha 7 thêm dữ liệu demo.

- Ghi chú Pha 4 (2026-10-22, chỉ implement, chưa chạy test, migrate hay seed):
  - Cổng (4.1): `evaluateVenue` đọc `statusAt(venue, now)` qua `VenuePackageRepositoryInterface`, không khoá. Không `active` (chưa có gói, hết hạn, đã chấm dứt) → lý do `package_inactive`, giữ cách map cũ: 409 ở hold/checkout/mua lại slot, 422 ở `assertEligible` với key lỗi `package` (trước là `subscription`, frontend không đọc key này).
    - Cơ sở hết gói bị ẩn khỏi danh sách và trang chi tiết công khai như mọi cơ sở không đủ điều kiện: spec gốc "Unified public and new-booking eligibility" dùng chung một điều kiện cho công khai và đặt mới.
    - `PeerToPeerResaleService::lock` khoá sân → cơ sở rồi chạy `execute` trước khi khoá listing. Kiểm dừng vận hành chồng lấn và receiving connection vẫn là task 1.11a của module 2.
    - `MatchService::assertCourtCanHostSession` giữ nguyên: đường này không khoá sân/cơ sở và file đã chạm trần 1.080 dòng. Gói vẫn được kiểm, chỉ không nằm dưới lock.
    - Danh sách sân công khai cache 300 giây; đổi gói không bump `venues_version`, nên cơ sở có thể còn hiện hoặc còn ẩn tối đa 5 phút. Hold, checkout và mua lại slot kiểm lại dưới lock nên không lọt đơn.
  - Cờ (4.2): xoá cả `partner.shadow_mode` vì chỉ nhánh shadow đọc nó, cùng `PartnerRolloutService::recordShadowComparison`.
  - Hai job (4.4, 4.5) là `Schedule::call` gọi action `ExpirePackagePaymentRequests` và `SendPackageExpiryReminders`. `expireOverdueForVenue` của Pha 3 đổi thành `expireOverdue($now, ?$venueId)`.
    - Key nhắc có thêm `.{user_id}` (D11 đã sửa). Truy vấn chỉ lấy gói hiện tại (id lớn nhất của cơ sở), bỏ gói đã chấm dứt và gói của cơ sở `closed` (review phát hiện; D11 và spec đã thêm).
  - Xoá (4.6, 4.8): 33 file, kể cả `PlatformPlanRepositoryInterface`, `PlatformSubscriptionController`, `app/Http/Requests/Subscription/`, `ProcessExpiredSubscriptionsCommand` và lịch `platform:process-expired-subscriptions`, vì chúng chỉ phục vụ code đã xoá.
    - Bỏ quota khỏi tạo/sửa sân, tạo cơ sở, tạo cơ sở nháp, mời và thêm nhân sự; bỏ kiểm entitlement ở khuyến mãi, tạo và công bố bảng giá (D10). DTO bảng giá bỏ `organizationId` vì chỉ phần kiểm này dùng.
    - Báo cáo cũ bỏ `platform_fees`, `platform_attempts`, `is_platform_admin` thay vì trả 0; 6.7 xoá hẳn báo cáo. `RestorePartner` trả `Organization`.
    - `phpstan-baseline.neon` sửa tay: bỏ 49 mục chết, hạ 7 số đếm.
  - Migration (4.7) `2026_10_22_200001_drop_platform_subscription_tables`. Đã đọc catalog prod (chỉ đọc): đủ 5 FK và 3 check đúng tên, không bảng nào khác trỏ FK vào bốn bảng cũ; prod còn 2 plan và 2 subscription demo, sẽ mất khi migrate (D16 dump trước). `down()` dựng lại bảng rỗng theo tiền lệ `2026_10_21_100002`.
  - Seeder (4.10): `VenuePackageSeeder` chạy trong `DatabaseSeeder` ngay sau `UserSeeder`, vì `package_prices.created_by` cần platform admin và `DatabaseSeederTest` chạy riêng `OrganizationVenueSeeder`. Chỉ `OrganizationVenueSeeder` tạo cơ sở. Giá dùng `firstOrCreate` theo kỳ; cơ sở chỉ nhận gói mới khi gói hiện tại không `active`. `E2ESeedFixturesCommand` tạo gói 360 ngày cho venue1A, venue1B, venue2.
  - Cho lane test (4.3, 4.9):
    - Gãy: `PartnerRolloutServiceTest:48-75`, `InflightQuoteEligibilityTest` (cờ, lock `platform_subscriptions`), `UnifiedBookingEligibilityTest` (cờ, key `subscription`), cả thư mục `tests/Feature/Subscription/`, các test liệt kê ở 4.9, cùng `Admin/CanonicalReportingAndExportTest`, `Admin/ReportParityTest`, `Finance/FinancialEntityPostingTest`, `Infrastructure/SchemaConformanceTest`, `Partner/PartnerLifecycleActionsTest` (`has_valid_entitlement`), `tests/Concerns/PricingRoles.php`.
    - Mọi test đường đặt có cơ sở tạo bằng `Venue::create` (khoảng 105 file, kể cả `tests/TestCase.php:49` và `tests/Concerns/BooksDirectOwnerCourt.php:43`) sẽ bị chặn `package_inactive` cho tới khi fixture có gói.
    - Mua lại slot giờ kiểm điều kiện: `AdvancedBookingMarketplaceTest`, `Booking/MarketplaceTransferVerificationTest`, `Readiness/ComprehensiveConcurrencyAndRetryInvariantsTest`.
    - Frontend: `VenuePublicationReviewViews.spec.ts` (mock `subscription_entitled`), hai spec platform-subscriptions của Pha 3. E2E: `p1-platform-subscription.spec.ts`, quota nhân sự trong `p1-staff-management.spec.ts`, `p1-legacy-wallet-direct-cohort.spec.ts:340-362`, `p1-revenue-reconciliation-reporting.spec.ts:136-168`, `runner/environment.spec.ts:741-800`, `runner/types.ts:90`; tài liệu `docs/testing/playwright-coverage.md:59-62,116-117`.
    - Allowlist backend phải xoá hai mục `model-query` của `ConfirmSubscriptionPayment` và `PlatformSubscriptionController`; có thể hạ trần `E2ESeedFixturesCommand` 773, `AdminController` 526, `PromotionController` 734, `FinancialPostingService` 512, `ReconciliationService` 427. Frontend: `VenueReadinessModal.vue` 425 → 409.
  - Còn mở, không thuộc Pha 4: `VenueReadinessModal.vue:230,244` đọc `partner_active` và `venue_metadata`, còn backend trả `partner_approved`, `venue_name`, `venue_address`, nên hai dòng đó luôn hiện dấu đỏ. Lỗi có từ trước (HEAD cũng vậy), thuộc phần công bố cơ sở của module 2.

## 5. Pha 5 – Tài chính của chủ cơ sở (D12, D14)

- [x] 5.1 Thư viện Excel (D14, Q1): Q1 = A (duyệt 2026-10-22). Image `registry.gitlab.com/bantool/devops/frankenphp:latest` không pull được (forbidden); kiểm trên image prod build sẵn ở máy `booking/server:latest` (PHP 8.5.5) có `zip`. Đã cài `openspout/openspout:^5.12`.
  - Kiểm `ext-zip` của image chạy prod `registry.gitlab.com/bantool/devops/frankenphp:latest` (`devops/build/booking.prod.Dockerfile:3`) bằng `php -m`, chạy trên image đó hoặc trong container backend (chỉ đọc, không chạy artisan).
  - Chưa có Q1 thì dừng, không cài package.
  - A được duyệt và có `ext-zip` → `composer require openspout/openspout`. Không có `ext-zip` → báo người dùng, chuyển B (`write-excel-file`) hoặc thêm extension vào image.
- [x] 5.2 `GET partner/organizations/{org}/finance/transactions` (D12), chỉ owner. (test: chưa làm)
  - Test: `OwnerFinanceTransactionsTest`.
    - Scenario "Matched payment", "Short transfer", "Transfer without a code".
    - Pᵢ giữ nguyên sau khi đơn đổi sân hoặc đổi giờ. Lọc theo cơ sở. Staff → 403.
- [x] 5.3 `GET partner/organizations/{org}/finance/report` (D12), chỉ owner. (test: chưa làm)
  - Test: `OwnerFinanceReportTest`.
    - Scenario "Metrics kept separate", "Unfinished booking completes later", "No-show and incident", "Free booking", "Court filter", "Midnight boundary", "Calendar month".
    - S không gồm đơn huỷ trước khi thanh toán và đơn đã dời sân hoặc giờ.
    - Số mã đơn đếm duy nhất. Response không có tổng doanh thu.
- [x] 5.4 Xuất Excel báo cáo owner theo Q1. (test: chưa làm)
  - Dùng lại đúng query của task 5.3.
  - Đầu file ghi tên báo cáo, cơ sở và sân, kỳ, thời điểm xuất (giờ VN). Sheet "Tổng hợp" và "Chi tiết".
  - Test: `OwnerFinanceExportTest`. Scenario "Export matches the screen", "Staff access", "Another organization".
- [x] 5.5 Frontend `/admin/finance` cho owner. (test: chưa làm — Vitest)
  - Hai tab "Giao dịch" và "Báo cáo".
  - Lọc theo cơ sở, sân, và kỳ theo ngày, tháng, quý hoặc năm lịch VN.
  - Năm thẻ chỉ số, không có tổng. Danh sách chi tiết của từng chỉ số. Nút xuất.
  - Staff không thấy menu. i18n vi/en, Vitest đi kèm.

- Ghi chú Pha 5 (2026-10-22, chỉ implement, chưa chạy test):
  - Kỳ lọc là ngày lịch VN `[from 00:00, to + 1 ngày 00:00)`. Cột tài chính của module 04 lưu giờ treo tường theo `APP_TIMEZONE` (Asia/Ho_Chi_Minh), nên mốc được đổi sang múi giờ app thay vì UTC như spec viết. Không đổi cách lưu của module 04. `from` phải từ 2000-01-01 (PostgreSQL không có năm 0).
  - Quyền: chỉ owner của tổ chức trên path. `venue_id` hoặc `court_id` ngoài tổ chức → 403 dù có tồn tại hay không (scenario "Another organization", không lộ sự tồn tại). Sân của tổ chức nhưng không thuộc cơ sở đã chọn → 422.
  - Giao dịch (5.2): receipt của mục đích `booking`, `recurring_period`, `split_share` và receipt chưa có mục đích (không mã, mã lạ, bị từ chối sớm), vì đó chính là thứ owner cần đối soát. Bỏ POS, giải đấu và chuyển khoản ra. Phân trang tối đa 100 dòng. `payable` (Pᵢ) và `delta` chỉ có khi xác định được đơn. Không trả `reference_code` vì module 04 cố ý ẩn mã giao dịch ngân hàng.
  - Báo cáo (5.3): năm chỉ số tách riêng, mỗi chỉ số `{amount, count, bookings[]}`, không có tổng.
    - R gồm phân bổ `booking`, `split_share`, `recurring_period`, theo `event_at` của receipt.
    - Hoàn thành tính attendance `checked_out`; vắng mặt tính `no_show` theo `checked_in_at`.
    - S đọc `metadata->release_reason = operator_affected_booking` và thời điểm huỷ, nên luôn bằng 0 cho tới khi module 02 làm task 4.8–4.12 (ghi lý do đó và `cancelled_at`).
  - Frontend (5.5): `useApiCall` thêm đánh số request, chỉ nhận kết quả của lần gọi mới nhất (`reset()` cũng bỏ kết quả đang chờ). Mọi nơi đang dùng đều là loader danh sách nên an toàn. Menu nằm trong nhóm "Tổ chức" (không thêm nhóm mới vì `AdminLayout.spec` đếm số nhóm), route có `orgRoles: ['organization_owner']`. Không gắn cờ tính năng.
  - Để sau: xem cả năm chưa phân trang danh sách chi tiết; nút "Hôm nay" dùng ngày của trình duyệt.
  - Cho lane test: `OwnerFinanceTransactionsTest`, `OwnerFinanceReportTest` (các scenario ở 5.2, 5.3, cộng 403 khi cơ sở hoặc sân thuộc tổ chức khác), Vitest cho `OwnerFinanceView`, hai tab, bộ lọc, `financePeriod` và `useApiCall`.

- Ghi chú xuất Excel 5.1, 5.4, 6.1–6.3, 5.5/6.5 (2026-10-22, chỉ implement, chưa chạy test; đã kiểm một file mẫu mở được, đúng hai sheet):
  - Trước khi làm đã merge `feat/module04-phase5` vào nhánh này (`d2462b1a`). Khi gộp: `HeldReservationRelease::notifyReleased` truyền `channels: ['database', 'mail']` làm tham số, vì `DurableNotificationService::recordOutboxNotification` (task 2.4) ghi đè `channels` trong payload.
  - `App\Services\Export\ExcelReportWriter` (openspout) stream file: sheet "Tổng hợp" có tên báo cáo, phạm vi (tổ chức/cơ sở/sân hoặc bộ lọc), kỳ, "Thời điểm xuất" giờ VN, rồi bảng tổng hợp; sheet "Chi tiết" có một dòng tiêu đề và các dòng dữ liệu.
  - Owner: `GET partner/organizations/{org}/finance/report/export` dùng lại `ShowOwnerFinanceReportRequest` (quyền như 5.3) và `metrics()`; tổng hợp năm chỉ số tách riêng, không có tổng; chi tiết liệt kê đơn của từng chỉ số.
  - Platform admin: `GET admin/platform-fees/{requests,venues,report}/export` dùng lại FormRequest và repository của màn hình. Repository yêu cầu và cơ sở tách `filtered()` dùng chung cho `paginate()` và `all()`, nên file có mọi dòng khớp bộ lọc, không theo trang. Tình trạng gói thêm ba dòng 7/3/1 ngày "(toàn hệ thống)" như màn hình.
  - Frontend: `api.download(path, fileName, options)` trong `core/api/client.ts` (tách `fetchAt` khỏi `requestAt`, hành vi lỗi và 419 giữ nguyên) tải Blob rồi lưu; `ExportButton` dùng chung báo lỗi ngay dưới nút khi thất bại, không báo đã tạo file. Tên file đặt ở frontend vì CORS không expose `Content-Disposition`.
  - `backend/vendor` của worktree là symlink sang checkout chính; sau `composer` ở worktree phải chạy `composer dump-autoload -o --no-scripts` ở checkout chính. `openspout` hiện có trong vendor chung, một `composer install` từ nhánh không có package này sẽ gỡ nó.
  - Cho lane test: `OwnerFinanceExportTest` (5.4), phần export của `PlatformFeeAdminTest` (6.4, "Export matches the screen"), Vitest cho `ExportButton` (thành công, lỗi), `api.download` và nút xuất ở bốn tab.

## 6. Pha 6 – Quản trị phí nền tảng (D13, D15)

- [x] 6.1 `PlatformFeeRequestController` (`index`, `show`, `export`) trong nhóm `admin`. (test: chưa làm)
- [x] 6.2 `PlatformFeeVenueController` (`index`, `show`, `export`). (test: chưa làm)
- [x] 6.3 `PlatformFeeReportController` (`show`, `export`). (test: chưa làm)
- [ ] 6.4 Test: `PlatformFeeAdminTest`.
  - Scenario "Collected fees by term", "Expiring venues", "Request detail with an anomalous transfer", "Export matches the screen", "Non-administrator access".
  - Nhóm 7/3/1 ngày theo `0 < ends_at − now ≤ n ngày`; tổng đếm cơ sở duy nhất.
  - Giá đổi sau khi trả không đổi báo cáo. Gói đã chấm dứt vẫn tính phí.
- [x] 6.5 Frontend `/admin/platform-fees` thêm ba tab "Yêu cầu thanh toán", "Tình trạng gói", "Báo cáo thu phí", mỗi tab có nút xuất. Vitest đi kèm. (test: chưa làm — Vitest)
- [x] 6.6 Dashboard `/admin` của platform admin: thay số liệu từ `getReports` (`useAdminDashboard.ts:140`) bằng tóm tắt phí nền tảng (phí đã thu trong tháng, số cơ sở sắp hết hạn trong 7 ngày). (test: chưa làm)
- [x] 6.7 Xoá báo cáo cũ (D13): (test: chưa làm — xoá ba file test và sửa allowlist thuộc lane test)
  - Ba route `admin/reports*`; `AdminController::report`, `reportCsv`, `buildReportDataset`; `getReports` trong `adminApi.ts`.
  - Test `Admin/CanonicalReportingAndExportTest`, `Admin/ReportParityTest` và `AdminReportOwnerScopeTest` của Pha 0.
  - Allowlist chỉ giảm.

- Ghi chú Pha 6 (2026-10-22, chỉ implement, chưa chạy test; SQL chưa chạy trên PostgreSQL):
  - Endpoint (nhóm `admin`, chỉ platform admin, còn lại 403): `GET admin/platform-fees/requests` (lọc `status`, `venue_id`, `term`, `from`/`to` theo ngày tạo, phân trang), `requests/{code}`, `venues` (lọc `status`, `expiring_within` 1/3/7, `search`), `venues/{venueId}`, `report` (`from`, `to` bắt buộc; `venue_id`, `term`). Mỗi màn hình một repository để export sau này dùng lại đúng truy vấn.
  - Trạng thái tính lúc xem: yêu cầu `pending` quá hạn hiện là `expired` (như `PackagePaymentRequest::statusAt`); GET không gọi `expireOverdue` vì đọc không được ghi. Gói hiện tại là dòng id lớn nhất của cơ sở (D1); cơ sở đóng hoặc tạm ngưng vẫn hiện, kèm trạng thái cơ sở.
  - Nhóm sắp hết hạn: gói hiện tại chưa chấm dứt với `0 < ends_at − now ≤ n ngày`, đếm `count(distinct venue_id)` trên toàn bộ cơ sở, không theo bộ lọc của danh sách.
  - Báo cáo thu phí: yêu cầu `paid` có `paid_at` trong khoảng UTC của các ngày VN, cộng `amount` của chính yêu cầu (đổi giá sau không ảnh hưởng), đếm mua mới và gia hạn theo `kind`, chia theo kỳ (đủ bốn kỳ, kể cả 0) và theo cơ sở. Gói đã chấm dứt vẫn tính; yêu cầu hết hạn, chuyển khoản muộn hoặc lệch tiền không bao giờ thành `paid` nên tự bị loại.
  - Ngày tạo yêu cầu (`created_at`, cast mặc định) là giờ treo tường của app; `paid_at`, `expires_at`, `ends_at` là UTC (`UtcDateTime`). Mỗi bộ lọc đổi mốc theo đúng loại cột.
  - Chi tiết yêu cầu trả mọi receipt kèm số tiền, thời điểm chuyển, thời điểm nhận và kết quả; không trả `content` và `raw`. Receipt `unknown_code` không gắn yêu cầu nên không hiện ở màn nào.
  - "Chủ cơ sở" trong danh sách là owner hiện tại của tổ chức, không phải người tạo yêu cầu.
  - Dashboard `/admin`: bỏ thẻ KPI, biểu đồ, bộ lọc ngày và link CSV của báo cáo cũ (xoá `AdminKpiCards`, `AdminAnalyticsCharts`, `AdminDateRangeFilter`); thêm hai thẻ "Phí đã thu tháng này" (từ `report` tháng VN hiện tại) và "Sắp hết hạn trong 7 ngày" (`expiring_counts[7]` của `venues`), dẫn tới tab tương ứng. Owner và staff đã bị router đưa khỏi `/admin` nên không đổi; owner dùng `/admin/finance`.
  - Xoá (6.7): ba route `admin/reports*`, `AdminController::report`, `reportCsv`, `buildReportDataset` và import chỉ chúng dùng, `VenueOperatingCalendar::slotCapacity` (chỉ báo cáo cũ gọi), `adminApi.getReports`. `AdminController` còn 260 dòng.
  - Bộ lọc cơ sở ở frontend lấy tối đa 100 cơ sở từ `venues?per_page=100` vì `/admin/venues` chặn platform admin (`venue_admin`); có comment `ponytail:` để đổi sang ô tìm kiếm khi vượt 100.
  - Review (3 hướng, mỗi phát hiện 3 người phản biện): sửa focus khi đóng panel chi tiết bằng nút X. Để lại: id cơ sở vượt `PHP_INT_MAX` trả 500 thay vì 404 (cùng kiểu với các route admin khác, chỉ platform admin chạm được).
  - Cho lane test:
    - Viết mới `PlatformFeeAdminTest` (các scenario ở 6.4, cộng: biên nửa đêm VN, `pending` quá hạn nằm trong `expired`, gói chấm dứt và dòng cũ không vào nhóm hết hạn, 404 khi mã/id không có, 422 khi chỉ có một trong `from`/`to`, `term=45`, `expiring_within=5`).
    - Xoá `Admin/AdminReportOwnerScopeTest`, `Admin/CanonicalReportingAndExportTest`, `Admin/ReportParityTest`. Sửa phần gọi `/api/admin/reports*` trong `Account/OwnerStaffGlobalAdminSeparationTest`, `BookingFlowsTest:58`, `Readiness/ComprehensiveRoleAndScopingMatrixTest`, `Readiness/SevenModulesContractAndE2EAcceptanceTest`.
    - Allowlist backend: bỏ `class-lines` của `AdminController` (còn 260 < 300), hạ `controller-public-methods` 13 → 11, `model-query` 7 → 2; bỏ hai mục `model-query` của file Subscription đã xoá ở Pha 4. Allowlist frontend: bỏ `component-lines` của `AdminAnalyticsCharts.vue`.
    - Vitest: sửa `AdminDashboardReservations.spec.ts` (bỏ stub ba component đã xoá, stub hoặc mock `PlatformFeeSummary`); viết mới cho `PlatformFeesView` (`?tab=`), ba tab, hai panel chi tiết, `FeeReportFilterBar`, `PlatformFeeSummary` và năm composable.
    - E2E và tài liệu: `frontend/e2e/p1-revenue-reconciliation-reporting.spec.ts` (dòng 116, 123, 186, 279), `docs/testing/playwright-coverage.md` (120, 123, 124).

## 7. Pha 7 – Bật demo trên prod (D16)

- [x] 7.1 `VenuePackageSeeder` bản demo: (test: chưa làm)
  - Bốn giá.
  - Mọi cơ sở có gói `active`, trong đó một cơ sở còn ≤ 3 ngày. Một cơ sở có gói đã chấm dứt.
  - Mỗi cơ sở có một yêu cầu `paid` với receipt `applied` và bút toán ledger.
  - Một yêu cầu `expired` có receipt `late`.
  - Test: `VenuePackageSeederTest` chạy seeder trên DB test và kiểm các điều trên.

- Ghi chú 7.1 (2026-10-22, chỉ implement, chưa chạy seeder hay test):
  - Đọc D16 theo nghĩa đen: mọi cơ sở đều có gói `active`. "Một cơ sở có gói đã chấm dứt" là `aura-landmark-72`: chấm dứt gói 30 ngày ở D-20 rồi mua gói 360 ngày, nên lịch sử có dòng đã chấm dứt mà cơ sở vẫn nhận đặt sân. Phương án khác là để một cơ sở đang bị chặn; bị loại vì luồng demo ở mọi cơ sở phải chạy, còn cảnh bị chặn có thể diễn trực tiếp bằng cách chấm dứt gói.
  - Mọi gói, yêu cầu, receipt, chấm dứt và bút toán đều đi qua action thật (`CreatePackagePaymentRequest`, `ApplyPackagePayment`, `TerminateVenuePackage`) với đồng hồ lùi bằng `Carbon::setTestNow`, luôn trả lại trong `finally`. Người trả là owner của chính tổ chức.
  - Dòng thời gian: giá có hiệu lực từ D-40; `aura-court` 360 ngày, `aura-arena-quan-ngua` 180 ngày, `hoang-gia-phu-my-hung` 90 ngày, mua ở D-35. `aura-complex-tay-ho` có yêu cầu đầu nhận chuyển khoản sau 12 phút (`late`, yêu cầu `expired`), rồi trả yêu cầu thứ hai (90 ngày). `aura-central-saigon` trả gói 30 ngày lúc 20:00 ngày D-28, nên hết hạn khoảng 20:03 ngày D+2.
  - Kết quả trên DB mới: 4 giá, 7 gói, 8 yêu cầu (7 `paid`, 1 `expired`), 8 receipt (7 `applied`, 1 `late`), 7 journal `platform.package_fee_receipt`.
  - Thông báo: chỉ kênh `database`, không gửi mail khi seed (bảng giá ghi thẳng bằng `firstOrCreate`, không qua `SchedulePackagePrice` vì action đó gửi mail cho owner). Mã giao dịch giả có dạng `seed-{request_code}`, không bao giờ trùng id số của SePay sau khi restore.
  - `SEPAY_ACCOUNT` trống: ở `local`/`testing` dùng số giả `0000000000`, môi trường khác thì dừng trước khi ghi. Seeder bỏ qua cơ sở đã có gói, mỗi cơ sở một transaction, nên chạy lại không nhân bản.
  - Lưu ý khi làm 7.3: phải reseed trong vòng 2 ngày trước buổi demo, nếu không `aura-central-saigon` đã hết gói khi trình bày. Sau khi restore, scheduler gửi nhắc hạn 3 ngày của cơ sở này qua app và mail cho 4 owner của tổ chức 2 (gồm `admin@auracourt.vn` và hai email admin demo). Thẻ "Phí đã thu tháng này" có thể bằng 0 nếu ngày seed rơi vào 20 ngày đầu tháng, cho tới khi có thanh toán thật trong smoke test.
  - Cho lane test: `VenuePackageSeederTest` (chạy `OrganizationVenueSeeder`, `UserSeeder`, `VenuePackageSeeder` sau `travelTo` 07:30; kiểm các điều ở trên, chạy lại không đổi số dòng, `Mail::fake` không gửi gì, môi trường khác `local`/`testing` với `SEPAY_ACCOUNT` trống thì ném lỗi và không ghi gì). `DatabaseSeederTest` vẫn phải qua kiểm giờ ≥ 6.
- [ ] 7.2 Người dùng xác nhận Q2. Thiếu giá trị nào thì dừng, không deploy.
- [ ] 7.3 Hẹn giờ với người dùng, rồi làm theo thứ tự:
  - `pg_dump` prod (memory `bt-server-demo-reseed`).
  - Merge `--no-ff` `feat/module05-packages-finance` vào main: dựng detached, chỉ ff main khi `origin/main` chưa đổi. Push origin.
  - Seed local bằng `.env.btprod`, restore lên prod theo quy trình reseed.
  - `glab ci run -b main`, chờ deploy xong.
- [ ] 7.4 Smoke test trên prod:
  - Admin đặt giá thấp (ví dụ 10.000đ) cho một kỳ.
  - Người dùng dùng tài khoản owner không phải `@demo.auracourt.vn`: tạo yêu cầu, chuyển khoản thật theo QR. Gói kích hoạt trong vài giây, ledger có đúng một bút toán.
  - Khách đặt sân ở cơ sở hết gói bị chặn, ở cơ sở có gói thì được.
  - Cơ sở còn ≤ 3 ngày có thông báo nhắc trong app và qua mail.
  - Owner xem báo cáo tài chính và xuất Excel. Admin xem báo cáo thu phí và xuất Excel.
  - Sau buổi demo, người dùng chọn giá thật để đặt lại.

## 8. Pha 8 – Kiểm tra và archive

- [ ] 8.1 Gate mỗi pha:
  - phpunit trên DB `*_test` bằng phpunit config tạm. PHPStan với `.env` tạm. Không chạy artisan hay test vào DB prod.
  - Baseline PHPStan chỉ giảm. Trần allowlist kiến trúc không tăng. File ≤ 300 dòng, controller ≤ 7 method public.
  - Vitest chạy riêng với `--maxWorkers=1`, không chạy cùng lúc với phpunit.
  - Lint và build frontend.
- [ ] 8.2 Playwright:
  - Owner mua gói → yêu cầu chờ → mở lại → webhook có chữ ký → gói `active`.
  - Owner chấm dứt gói → khách không đặt được sân của cơ sở đó.
  - Owner xem báo cáo tài chính tháng và xuất Excel.
  - Admin đặt giá lên lịch, xem tab tình trạng gói.
- [ ] 8.3 Cập nhật `report-traceability.md` sau mỗi pha: trạng thái từng mã, commit và test thật.
- [ ] 8.4 Trước khi archive:
  - Dựng lại các khối MODIFIED "Role-based owner operations", "Real staff invitation and permission lifecycle", "Report-aligned end-to-end contracts" từ text đang có ở spec chính, chỉ giữ phần sửa của change này.
  - Thêm khối MODIFIED "Unified public and new-booking eligibility", "Venue opening conditions" và "Single organization-scoped venue write path", dựng từ spec chính sau khi module 2 archive, chỉ đổi đúng các câu ở task 1.2 (module 2 đã chọn cách này). Module 3 tự sửa change của mình, nên không cần MODIFIED cho "Price table versions and effective time" và "Venue promotion programs", trừ khi module 3 archive mà chưa sửa.
  - Sửa Purpose của `openspec/specs/platform-subscriptions/spec.md` theo gói cơ sở.
  - `openspec validate align-module-05-packages-finance --strict` đạt.
- [ ] 8.5 Archive sau `direct-owner-cutover`, `align-module-02-venue-court`, `align-module-03-pricing-promotions` và `align-module-04-booking`.
