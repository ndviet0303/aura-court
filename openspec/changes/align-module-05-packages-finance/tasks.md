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
- [ ] 1.3 Người dùng trả lời và ghi vào Open Questions của design:
  - Q1 (thư viện Excel), cần trước Pha 5. Đã chốt 2026-10-22: A (`openspout/openspout`), kiểm `ext-zip` trước.
  - Q2 (giá trị `SEPAY_*`, URL webhook trên dashboard SePay, scheduler và queue worker trên prod), cần trước Pha 7.

## 2. Pha 2 – Schema mới và giá gói (D1, D2, D3)

Pha 2–6 làm trên nhánh `feat/module05-packages-finance` và chỉ vào main ở Pha 7 (design, Migration Plan).

- [ ] 2.1 Migration tạo `package_prices`, `venue_packages`, `package_payment_requests` và `package_payment_receipts` theo D1:
  - Check `term_days IN (30, 90, 180, 360)`, `amount > 0`, `ends_at > starts_at` và tập giá trị của `kind`, `status`, `outcome`.
  - Unique `request_code`, `payment_code`, `provider_transaction_id`. Unique một phần `(venue_id) WHERE status = 'pending'`.
  - FK `restrict` về `venues`, `organizations` và `users`, như `2026_10_11_000002_restrict_deletes_into_financial_tables.php`.
  - Cập nhật `SchemaConformanceTest`.
- [ ] 2.2 Enum `PackageTerm`, model, factory và repository (interface + Eloquent, binding ở `RepositoryServiceProvider`):
  - Repository trả tình trạng gói của một cơ sở tại `t`: `none|active|expired|terminated` (D1).
  - Test: `VenuePackageStatusTest`.
    - Đúng `starts_at` → `active`. Đúng `ends_at` → `expired`.
    - `terminated_at` khác null → `terminated` ở mọi thời điểm.
    - Cơ sở chưa có dòng nào → `none`.
    - Kỳ 30 ngày có `ends_at = starts_at + 720 giờ`.
- [ ] 2.3 Giá gói (D3):
  - `PlatformFeePriceController` (`index`, `store`) trong nhóm `admin`. FormRequest, DTO, action `SchedulePackagePrice`.
  - `effective_at` rỗng hoặc ≤ now → now. Các giá cùng kỳ đã lên lịch nhận `replaced_at = now`.
  - Một service đọc giá áp dụng tại `t` và giá đã lên lịch tiếp theo của từng kỳ. Task 3.1 và 3.6 dùng chung service này.
- [ ] 2.4 `DurableNotificationService::recordOutboxNotification` nhận thêm `array $channels = ['database']` và ghi vào `payload.channels`.
  - Test: thêm vào `DurableNotificationServiceTest`. Có `mail` thì dispatcher gửi cả mail; mặc định chỉ in-app.
- [ ] 2.5 Báo giá mới (D3): sau commit, ghi outbox `package.price.{price_id}.{user_id}` cho mọi owner đang hoạt động, kênh `database` + `mail`. Nội dung có kỳ hạn, giá và thời điểm áp dụng theo giờ VN.
  - Test: `PackagePriceScheduleTest`.
    - Scenario "Price scheduled for a later date", "Scheduled price replaced before it takes effect", "Effective time in the past", "Price set by someone other than a platform administrator".
    - Mỗi owner nhận đúng một outbox cho mỗi giá. Chạy lại dispatcher không gửi lặp.
- [ ] 2.6 Frontend `/admin/platform-fees` cho platform admin, tab đầu "Giá gói":
  - Bảng bốn kỳ: số ngày, giá đang áp dụng, giá đã lên lịch tiếp theo.
  - Form đặt giá: chọn thời điểm áp dụng theo giờ VN, gửi UTC.
  - Feature mới `frontend/src/features/platform-fees/` (api, types, views). Route và menu chỉ cho platform admin.
  - i18n vi/en, Vitest đi kèm.
- [ ] 2.7 `VenueFactory` mặc định tạo gói `active` 360 ngày, có state `withoutPackage()` (D9).

## 3. Pha 3 – Yêu cầu thanh toán, kích hoạt và chấm dứt (D4, D5, D6, D7, D8)

- [ ] 3.1 Tạo và mở lại yêu cầu (D4):
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
- [ ] 3.2 Nhánh `PKG` trong `ProcessQrPayment::execute` (D5):
  - Chạy sau khi đọc id giao dịch, trước mọi nhánh nạp ví.
  - Nội dung khớp `/\bPKG([A-Z0-9]{8})\b/i` → gọi `ApplyPackagePayment` rồi `return`.
  - Test: `PackagePaymentWebhookTest`, gọi `POST /api/webhooks/sepay` có chữ ký hợp lệ.
    - Giao dịch `PKG` không tạo `Payment` hay `PaymentIntent`, kể cả khi mã không tồn tại.
    - Nội dung có cả `PKG…` và `NAP…` → chỉ đi nhánh gói, ví không đổi.
    - Giao dịch nạp ví không có `PKG` chạy như cũ.
- [ ] 3.3 `ApplyPackagePayment` (D6):
  - Chèn receipt; khoá `venues` rồi khoá yêu cầu.
  - Thời điểm trả lấy từ `transactionDate`, parse như `OwnerPaymentAttemptService:189`.
  - Xét outcome đúng thứ tự D6. Khi `applied`: gia hạn hoặc tạo gói, ghi kết quả vào yêu cầu, báo owner in-app sau commit.
  - Test: `PackageActivationAndRenewalTest`.
    - Scenario "First purchase paid on time", "Renewal paid before the end", "Renewal paid after the end", "Late transfer", "Different amount", "Repeated notification", "Transfer with an unknown code", "Paused venue renews".
    - `transactionDate` đúng bằng `expires_at` → `late`; một giây trước đó → `applied`.
    - Gia hạn một cơ sở không đổi gói của cơ sở khác cùng tổ chức.
- [ ] 3.4 Ledger: `FinancialPostingService::postPackageFeeReceipt(PackagePaymentReceipt)`.
  - Debit `platform_bank_cash`, credit `platform_subscription_revenue`, `event_id = package-fee-receipt:{id}`. Chỉ gọi khi outcome là `applied`.
  - Test: sửa `FinancialEntityPostingTest`. Webhook tới hai lần chỉ ghi một bút toán; outcome khác không ghi.
- [ ] 3.5 Chấm dứt (D8): `POST partner/organizations/{org}/venues/{venue}/package/terminate` nhận `{confirm_consequences: true}`, chỉ owner.
  - Test: `PackageTerminationTest`.
    - Scenario "Confirmed termination", "Termination without confirmation", "New term after termination", "Pending renewal paid after termination".
    - Gói không `active` → 409 `PACKAGE_NOT_ACTIVE`. Receipt và ledger không đổi.
- [ ] 3.6 Màn owner (requirement "Owner view of venue packages"): `GET partner/organizations/{org}/packages`, chỉ owner.
  - Mỗi cơ sở có kỳ, bắt đầu, kết thúc, tình trạng tại now, thời điểm chấm dứt, yêu cầu đang chờ và lịch sử yêu cầu.
  - Kèm bốn kỳ với giá đang áp dụng và giá đã lên lịch tiếp theo.
  - Test: `OwnerVenuePackageViewTest`. Scenario "Expired package view", "Pending request shown". Staff → 403.
- [ ] 3.7 Frontend `/partner/subscription`, viết lại trong `features/platform-subscriptions/`:
  - Danh sách cơ sở với tình trạng gói. Nút mua hoặc gia hạn; khi gia hạn thì kỳ bị khoá.
  - Mở lại yêu cầu đang chờ: đếm ngược, tài khoản, số tiền, mã thanh toán và QR.
  - Hộp xác nhận chấm dứt ghi rõ: mất thời gian còn lại, không hoàn tiền, không chuyển sang gói khác.
  - Thông báo cho `PACKAGE_REQUEST_PENDING`, `PACKAGE_TERM_LOCKED`, `PACKAGE_PRICE_UNAVAILABLE`, `PACKAGE_NOT_ACTIVE`, `TERMINATION_NOT_CONFIRMED` trong `ERROR_CODE_MAP`, vi/en.
  - Xoá `PlanCatalogCard.vue`, `SubscriptionStatusCard.vue` và phần plan/trial trong `types`, `api`, `composables`.
  - Viết lại `platformSubscriptionApi.spec.ts` và `PlatformSubscriptions.spec.ts`. Staff không thấy menu gói.

## 4. Pha 4 – Cổng luôn bật, nhắc hạn và xoá gói cũ (D7, D9, D10, D11)

- [ ] 4.1 `VerifyEffectiveBookingEligibility` (D9):
  - `getEntitlement(int $orgId)` → `lockVenuePackage(int $venueId)`. Lý do chặn `package_inactive`, giữ mã chặn eligibility hiện có.
  - Sửa hai nơi gọi: `ReservationService:67` và `OrdinaryCheckoutService:58`.
  - Sau khi merge `feat/module02-venue-court-alignment`, sửa thêm `PeerToPeerResaleService::lock` (task 1.2).
  - Thứ tự lock bắt đầu bằng `venue_packages`.
- [ ] 4.2 Xoá cờ `partner.enforce_subscription_check` (`config/partner.php`, `PartnerRolloutService.php:34,37`) và nhánh shadow.
- [ ] 4.3 Test: `VenuePackageBookingGateTest`.
  - Scenario "Hold after the package ended", "Hold placed before the package ended", "Play after the package end", "Owner works with an expired package".
  - Checkout và hold đều bị chặn với `package_inactive`.
  - `ReservationService::confirm` không kiểm gói (BR-104).
  - Query log khoá `venue_packages` trước các bảng còn lại.
  - Sửa `InflightQuoteEligibilityTest` và `UnifiedBookingEligibilityTest`, bỏ phần cờ.
- [ ] 4.4 Job `packages:expire-requests` mỗi phút trong `routes/console.php`, cùng mẫu `booking:expire-pending` (`name`, `withoutOverlapping`, `onFailure`).
  - Test: `PackageRequestExpiryTest`. Yêu cầu quá hạn thành `expired`; yêu cầu còn hạn giữ nguyên.
- [ ] 4.5 Job `packages:send-expiry-reminders` mỗi phút (D11).
  - Test: `PackageExpiryReminderTest`.
    - Scenario "Seven days before the end", "Renewal moves the reminders", "Terminated package", "Delayed scheduler".
    - Chạy hai lần không ghi thêm outbox.
    - Gói còn 2 ngày lúc job chạy lần đầu chỉ nhận mốc 3 ngày.
- [ ] 4.6 `EvaluateVenueReadiness` và `RestorePartner:47` bỏ phần đọc gói (D10).
  - Frontend: sửa `VenueReadinessModal.vue`, `venuePublicationApi.ts` và `VenuePublicationReviewViews.spec.ts`.
- [ ] 4.7 Migration xoá bảng gói cũ:
  - Bỏ FK và check do `2026_10_11_000002_restrict_deletes_into_financial_tables.php:49-53` và `2026_10_11_000005_add_finance_status_checks.php:20-22` thêm vào.
  - Drop `platform_payment_attempts`, `platform_provider_receipts`, `platform_subscriptions`, `platform_plans`. Không sửa migration cũ.
  - `SchemaConformanceTest` không còn bảng cũ.
- [ ] 4.8 Xoá code gói cũ:
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
- [ ] 4.10 `OrganizationVenueSeeder` (dòng 52-97): bỏ plan và gói theo tổ chức, gọi `VenuePackageSeeder` bản tối thiểu (bốn giá, mỗi cơ sở một gói `active` 360 ngày). Pha 7 thêm dữ liệu demo.

## 5. Pha 5 – Tài chính của chủ cơ sở (D12, D14)

- [ ] 5.1 Thư viện Excel (D14, Q1):
  - Kiểm `ext-zip` của image chạy prod `registry.gitlab.com/bantool/devops/frankenphp:latest` (`devops/build/booking.prod.Dockerfile:3`) bằng `php -m`, chạy trên image đó hoặc trong container backend (chỉ đọc, không chạy artisan).
  - Chưa có Q1 thì dừng, không cài package.
  - A được duyệt và có `ext-zip` → `composer require openspout/openspout`. Không có `ext-zip` → báo người dùng, chuyển B (`write-excel-file`) hoặc thêm extension vào image.
- [ ] 5.2 `GET partner/organizations/{org}/finance/transactions` (D12), chỉ owner.
  - Test: `OwnerFinanceTransactionsTest`.
    - Scenario "Matched payment", "Short transfer", "Transfer without a code".
    - Pᵢ giữ nguyên sau khi đơn đổi sân hoặc đổi giờ. Lọc theo cơ sở. Staff → 403.
- [ ] 5.3 `GET partner/organizations/{org}/finance/report` (D12), chỉ owner.
  - Test: `OwnerFinanceReportTest`.
    - Scenario "Metrics kept separate", "Unfinished booking completes later", "No-show and incident", "Free booking", "Court filter", "Midnight boundary", "Calendar month".
    - S không gồm đơn huỷ trước khi thanh toán và đơn đã dời sân hoặc giờ.
    - Số mã đơn đếm duy nhất. Response không có tổng doanh thu.
- [ ] 5.4 Xuất Excel báo cáo owner theo Q1:
  - Dùng lại đúng query của task 5.3.
  - Đầu file ghi tên báo cáo, cơ sở và sân, kỳ, thời điểm xuất (giờ VN). Sheet "Tổng hợp" và "Chi tiết".
  - Test: `OwnerFinanceExportTest`. Scenario "Export matches the screen", "Staff access", "Another organization".
- [ ] 5.5 Frontend `/admin/finance` cho owner:
  - Hai tab "Giao dịch" và "Báo cáo".
  - Lọc theo cơ sở, sân, và kỳ theo ngày, tháng, quý hoặc năm lịch VN.
  - Năm thẻ chỉ số, không có tổng. Danh sách chi tiết của từng chỉ số. Nút xuất.
  - Staff không thấy menu. i18n vi/en, Vitest đi kèm.

## 6. Pha 6 – Quản trị phí nền tảng (D13, D15)

- [ ] 6.1 `PlatformFeeRequestController` (`index`, `show`, `export`) trong nhóm `admin`.
- [ ] 6.2 `PlatformFeeVenueController` (`index`, `show`, `export`).
- [ ] 6.3 `PlatformFeeReportController` (`show`, `export`).
- [ ] 6.4 Test: `PlatformFeeAdminTest`.
  - Scenario "Collected fees by term", "Expiring venues", "Request detail with an anomalous transfer", "Export matches the screen", "Non-administrator access".
  - Nhóm 7/3/1 ngày theo `0 < ends_at − now ≤ n ngày`; tổng đếm cơ sở duy nhất.
  - Giá đổi sau khi trả không đổi báo cáo. Gói đã chấm dứt vẫn tính phí.
- [ ] 6.5 Frontend `/admin/platform-fees` thêm ba tab "Yêu cầu thanh toán", "Tình trạng gói", "Báo cáo thu phí", mỗi tab có nút xuất. Vitest đi kèm.
- [ ] 6.6 Dashboard `/admin` của platform admin: thay số liệu từ `getReports` (`useAdminDashboard.ts:140`) bằng tóm tắt phí nền tảng (phí đã thu trong tháng, số cơ sở sắp hết hạn trong 7 ngày).
- [ ] 6.7 Xoá báo cáo cũ (D13):
  - Ba route `admin/reports*`; `AdminController::report`, `reportCsv`, `buildReportDataset`; `getReports` trong `adminApi.ts`.
  - Test `Admin/CanonicalReportingAndExportTest`, `Admin/ReportParityTest` và `AdminReportOwnerScopeTest` của Pha 0.
  - Allowlist chỉ giảm.

## 7. Pha 7 – Bật demo trên prod (D16)

- [ ] 7.1 `VenuePackageSeeder` bản demo:
  - Bốn giá.
  - Mọi cơ sở có gói `active`, trong đó một cơ sở còn ≤ 3 ngày. Một cơ sở có gói đã chấm dứt.
  - Mỗi cơ sở có một yêu cầu `paid` với receipt `applied` và bút toán ledger.
  - Một yêu cầu `expired` có receipt `late`.
  - Test: `VenuePackageSeederTest` chạy seeder trên DB test và kiểm các điều trên.
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
