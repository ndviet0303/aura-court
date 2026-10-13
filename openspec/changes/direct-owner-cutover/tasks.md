## 0. Điều kiện trước khi bắt đầu

- [x] 0.1 Tạo nhánh `feat/direct-owner-cutover` từ `main` (tạo từ `688e440f` ngày 2026-10-13).
  - Mọi lệnh artisan và test chỉ chạy trên DB `*_test` hoặc `booking_demo_btprod`.
  - Không dùng `backend/.env` vì file này trỏ vào prod.
- [ ] 0.2 Người dùng tự điền bốn biến vào `backend/.env.btprod` (file này gitignored): `SEED_DEMO_RECEIVER_BANK`, `SEED_DEMO_RECEIVER_ACCOUNT`, `SEED_DEMO_RECEIVER_NAME`, `SEED_DEMO_RECEIVER_WEBHOOK_KEY`.
  - Giá trị không được xuất hiện trong chat, commit hay log.
  - Chỉ việc seed (mục 10, 15) cần các biến này. Code và test không phụ thuộc chúng.

## 1. Cờ tính năng và stack (design 1)

- [x] 1.1 Sửa `.gitlab-ci.yml` (dòng 148–153): đặt `FEATURE_DIRECT_OWNER_PAYMENTS=true` và `"PILOT_ORGANIZATION_IDS=*"`.
  - Giữ ngoặc kép để shell không glob dấu `*`.
  - Các cờ khác giữ nguyên. Dockerfile vẫn hardcode `FEATURE_MARKETPLACE_PAYOUTS=false`.
- [x] 1.2 Xóa các dòng `FEATURE_*` và `PILOT_ORGANIZATION_IDS` (dòng 39–45) khỏi anchor `x-booking-runtime-environment` trong `devops/portainer/booking_app.stack.yml`.
- [x] 1.3 Test `FeatureFlagService` với `pilot_organizations = ['*']`:
  - một tổ chức vừa tạo có `direct_owner_payments` bật;
  - `marketplace_payouts` vẫn tắt khi cờ cấu hình là false.
  - Test: `tests/Feature/Infrastructure/FeatureFlagWildcardPilotTest.php`. `FeatureFlagService` đã hỗ trợ `*` nên không sửa code.

## 2. Webhook SePay xác thực bằng Api_Key (design 2)

- [x] 2.1 `SePayReceivingConnectionService::verifyInboundWebhook`: xác thực header `Authorization: Apikey <key>`.
  - So khớp với credential đã giải mã của kết nối bằng `hash_equals`.
  - Thiếu header hoặc sai key thì trả 401 và không ghi evidence.
  - Xóa cửa sổ timestamp và code HMAC.
  - Đổi nhãn evidence `webhook_hmac` thành `webhook_api_key`.
  - Các bước khác giữ nguyên: khớp tài khoản nhận, validate payload, chống trùng theo event id của từng kết nối.
  - Header được tách bằng `^Apikey\s+(\S+)$` (không phân biệt hoa thường); credential lỗi giải mã hoặc `credential_version = 0` cũng trả 401. Nhãn `receiver_selected` cũng đổi sang `webhook_api_key`.
- [x] 2.2 `ReceivingConnectionWebhookController`:
  - Bỏ đọc `X-SePay-Timestamp` và `X-SePay-Signature`, chuyển header `Authorization` vào service.
  - Không log header `Authorization` ở bất kỳ đâu, kể cả trong middleware ghi request (nếu có).
  - Không có middleware nào ghi header request; controller chỉ chuyển header vào service.
- [x] 2.3 Hướng dẫn cài đặt trên API và UI phải ghi đúng những gì chủ sân dán vào SePay:
  - URL `…/api/v1/webhooks/sepay/connections/{routingId}`;
  - kiểu xác thực Api_Key;
  - sự kiện In_only;
  - mẫu mã thanh toán: tiền tố `AC`, hậu tố 18–18 ký tự, NumberAndLetter.
  - Trường của `POST …/credential` đổi từ `hmac_secret` thành `webhook_api_key` (min 32, max 512, chỉ `[A-Za-z0-9_-]`). Không giữ tên cũ (no shim).
  - `onboarding` trả `authentication: Api_Key`, `authorization_header`, `event: In_only`, `payment_code {prefix AC, 18–18, NumberAndLetter}` và hướng dẫn vi/en tương ứng. `data-testid` trên UI đổi thành `webhook-api-key-input` / `save-webhook-api-key`.

  File cần sửa: `ReceivingConnectionResource`, `ReceivingConnectionController`, `RotateReceivingConnectionCredentialRequest`, `OwnerReceivingConnectionPanel.vue`, i18n vi/en.
- [x] 2.4 Test backend:
  - Đổi helper gửi webhook sang header `Authorization: Apikey` trong mọi test gọi `webhooks/sepay/connections/`. Hiện có 16 file, liệt kê bằng `grep -rl 'webhooks/sepay/connections' backend/tests`.
  - Test của webhook nền tảng (`webhooks/sepay`, ví dụ `TenantWalletDepositTest`) giữ nguyên.
  - Thêm các case:
    - thiếu header: 401, không có evidence;
    - sai key: 401;
    - đúng key nhưng sai `accountNumber`: không khớp nghĩa vụ nào;
    - cùng event id gửi hai lần: chỉ tạo một receipt.
  - Case mới nằm trong `OwnerReceivingConnectionTest` (thiếu header / sai key / sai scheme: 401, không evidence, không receipt, không log; đúng key + sai `accountNumber`: receipt `foreign_receiver`, attempt vẫn pending) và một assertion một-receipt cho event id gửi hai lần. Case "body bị sửa" trong `OwnerPaymentAttemptServiceTest` không còn ý nghĩa với Api_Key nên đổi thành case sai key.
- [x] 2.5 Playwright:
  - `frontend/e2e/runner/test-data.ts` (khoảng dòng 654–727) gửi `Authorization: Apikey` thay cho HMAC.
  - Cập nhật `p1-receiving-connections`, `p1-recurring-series` và `p1-split-payments`. `p1-resale-marketplace` viết lại ở 7.9.
  - Key dùng trong test chỉ gồm ký tự `[A-Za-z0-9_-]` và dài ít nhất 10 ký tự, để `sanitize-traces.ts` và `sanitize_zip.py` che được.
  - Helper và các spec cũng sửa URL về `/api/v1/webhooks/sepay[...]` (đang trỏ nhầm sang đường dẫn không version, sẽ 404). Bỏ tùy chọn `timestamp` của helper (gỡ hai dòng dùng nó trong `p0-cancellation-refund-reschedule`, spec này sẽ bị xóa ở 3.x).
  - `p1-legacy-wallet-direct-cohort` gọi thẳng `verifyInboundWebhook` nên cũng đổi sang `'Apikey ' . $secret`.
  - Chưa chạy Playwright.

## 3. Bỏ hủy sân, hoàn tiền và dời lịch (design 7)

- [ ] 3.1 Xóa route trong `routes/api.php`:
  - v1: `bookings/{reservation}/refund-quote` và `refund-requests` (dòng 267–269), `reschedule-preview` và `reschedule-requests` (dòng 270–271).
  - v2: `reservations/{reservation}/refund-quote`, `refund-requests`, hộp thư `refund-requests` cùng approve, reject, report-execution, acknowledge, và `reschedule-preview`, `reschedule-requests` (dòng 435–444).
  - `admin/refunds` (dòng 367).
  - Xóa các `use` controller không còn dùng.
- [ ] 3.2 Xóa `OwnerRefundService`, `OwnerRefundController`, `AdminController::refunds` và code chỉ chúng dùng.
  - Model và bảng hoàn tiền giữ lại làm lịch sử.
- [ ] 3.3 Xóa dời lịch:
  - `RescheduleService` và `RescheduleController`.
  - Purpose `booking_change`:
    - mục trong `OwnerPaymentAttemptService::PURPOSES`;
    - nhánh `booking_change` trong `processReceipt` (khoảng dòng 329);
    - mục trong danh sách purpose của `ExpirePendingBookings` (dòng 106).
  - Không giữ handler cho attempt `booking_change` cũ. Prod không có attempt nào (kiểm tra ngày 2026-10-13), và seed cũng không tạo.
  - Tham số `excludeReservationId` và `excludeBookingSlotIds` của `SlotSelectionPolicy::assertAllowed`, cùng các nhánh lọc theo chúng. Sửa docblock cho hết nhắc tới reschedule.
- [ ] 3.4 `RecurringReleaseService`:
  - Xóa `repairLegacyCancel`, `recordPaidValue`, `refundAttempt`, action `RepairLegacyCancelledContracts` và lệnh `contracts:repair-legacy-cancel`.
  - `cancelSeries` (kết thúc hợp đồng) chỉ nhả các buổi chưa thanh toán. Buổi đã thanh toán vẫn giữ.
- [ ] 3.5 `SplitPaymentService`:
  - Xóa `refundAllocations` (đang là code chết).
  - Nhóm chia tiền hết hạn ghi một `FinanceException` cho mỗi phần đã trả, không tạo nghĩa vụ hoàn tiền.
- [ ] 3.6 `ArbitrateDispute`: bỏ quyết định `resolve_and_refund` ở request validation, action và resource.
- [ ] 3.7 Chính sách cơ sở:
  - `StoreVenueBookingPolicyRequest` bỏ `cancellation`, `change`, `ancillary_credit_policy` và `transfer.seller_reimbursement_required`. Bỏ luôn vòng kiểm phí `['cancellation', 'change']` trong `withValidator`.
  - Readiness chỉ kiểm tra có chính sách hay không, nên không phải sửa.
  - Phiên bản chính sách và snapshot booking đã lưu giữ nguyên các khóa này làm lịch sử.
- [ ] 3.8 Frontend:
  - Booking:
    - Xóa `BookingCancelModal.vue`, `BookingRescheduleModal.vue` và `BookingRescheduleModal.spec.ts`.
    - Bỏ nút hủy, nút dời lịch và luồng hoàn tiền trong `UserBookingCard.vue`, `BookingView.vue`, `V2ReservationView.vue` và `V2ReservationDetailModal.vue`. Booking đủ điều kiện hiện nút bán lại slot thay vào đó.
    - Bỏ API hoàn tiền và dời lịch trong `bookingApi.ts`. Xóa `reservationChanges.ts` nếu không còn kiểu nào được dùng.
    - Bỏ dòng ưu đãi `schedule.flexReschedulePerk` trong `ScheduleBookingSummary.vue`.
    - `useNotificationList.ts` bỏ nhánh sự kiện `reschedule`. Thông báo cũ vẫn hiện theo dạng mặc định.
  - Admin:
    - Xóa `refundReviewApi.ts`, `AdminRefundsPanel.vue` và tab của nó trong `AdminManageView.vue`.
    - Bỏ `resolve_and_refund` trong `ArbitrationDrawer.vue` và `types/dispute.ts`.
    - Bỏ các trường hủy, hoàn tiền, dời lịch, `ancillary_credit_policy` và cờ hoàn tiền cho người bán trong `VenueBookingPolicyForm.vue`.
    - Bỏ quyền hoàn tiền trong `StaffRoleMatrix.vue`.
  - Nội dung:
    - Bỏ mục 5 trong `TermsView.vue` và liên kết chính sách hoàn tiền trong `SiteFooter.vue` (nếu có). Bỏ mọi câu hứa dời lịch.
    - Sửa câu đồng ý trong `ConsentAndReviewStep.vue`, giữ nguyên khóa `settlement_refund_terms`.
    - Cập nhật i18n vi/en.
  - Test frontend:
    - Sửa `BookingView.spec.ts`, `V2ReservationDeepLink.spec.ts` và `bookingApiReservations.spec.ts`.
    - Bỏ các mục đã xóa khỏi `src/__tests__/architecture-allowlist.json`.
- [ ] 3.9 Test backend:
  - Các route đã xóa (hoàn tiền và dời lịch, v1 và v2) trả 404 và không làm đổi dữ liệu.
  - Kết thúc hợp đồng vẫn giữ các buổi đã trả.
  - Nhóm chia tiền hết hạn tạo một exception cho mỗi phần đã trả.
  - Xóa `RescheduleAtomicSwapTest`, `OwnerRefundInboxTest` và `OwnerRefundObligationTest`.
  - Sửa các test còn gọi đường hoàn tiền hoặc dời lịch. Liệt kê bằng `grep -rlE "refund-requests|refund-quote|admin/refunds|resolve_and_refund|repairLegacyCancel|refundAllocations|OwnerRefund|reschedule|booking_change|excludeReservationId" backend/tests`.
    - Hiện danh sách gồm `BlockedCommandsInvariantsTest`, `ReservationSlotOverlapExclusionTest`, `SuspendedPartnerObligationsAndAuthorizationTest`, `BookingFlowsTest`, `OrdinaryWalletCheckoutTest`, `ComprehensiveConcurrencyAndRetryInvariantsTest` và `CommunityActivityAndReputationTest`.
    - Giữ các kiểm tra rằng lịch sử hoàn tiền cũ vẫn đọc được.
  - Bỏ các mục đã xóa khỏi `tests/Unit/Architecture/architecture-allowlist.json`. Ceiling chỉ được giảm.
- [ ] 3.10 Playwright:
  - Xóa `p0-cancellation-refund-reschedule.spec.ts` và sửa mọi nơi tham chiếu tên file này.
  - `p0-booking-payment-journey` thêm kiểm tra: booking đã thanh toán không có nút hủy, hoàn tiền hay dời lịch, nhưng có nút bán lại.
  - Bỏ `resolve_and_refund` trong `p1-dispute-lifecycle`.
  - Sửa phần hoàn tiền và hủy sân trong `p0-booking-payment-journey`, `p1-split-payments`, `p1-venue-lifecycle`, `p1-partner-registration` và `p1-legacy-wallet-direct-cohort`.

## 4. Slot 1 giờ ở dữ liệu và chữ hiển thị (design 10)

Người dùng chốt ngày 2026-10-13: không thêm kiểm tra slot 1 giờ trong code, chỉ đổi chữ hiển thị và bỏ gia hạn. Phần ép slot 1 giờ đã bỏ khỏi change này: một kiểm tra chung cho mọi đường đặt sân, bỏ cài đặt `slot_duration`, chính sách bội số 60 phút, slot giá `HH:00`, bỏ lựa chọn 1,5 giờ ở trang sân.

- [x] 4.1 Bỏ gia hạn tại sân:
  - Xóa route `attendance/extend-session`, `AttendanceController::extendSession` và `AttendanceService::extendSession`.
  - `reassignCourt` giữ nguyên.
  - Khách muốn chơi thêm thì nhân viên đặt slot kế tiếp qua luồng walk-in sẵn có trong `OrdinaryCheckoutService`. Luồng này đã gọi `assertAllowed` và thu tiền riêng.
  - Hạ trần allowlist kiến trúc: `AttendanceService` còn 565 dòng, `AttendanceController` còn 5 lần `$request->validate(`.
- [x] 4.2 `AttendanceOperationsAndReadinessTest`: gọi `extend-session` nhận 405, vì chỉ còn `GET attendance/{code}` khớp đường dẫn. Giờ kết thúc của booking không đổi.
- [x] 4.3 Chữ hiển thị không dùng "slot". Khi đếm thì dùng "giờ", khi nói về một đơn vị thì dùng "1h". Các nhãn "khung giờ" đang có giữ nguyên.
  - Thông báo backend: checkout V1, báo giá, danh sách chờ (cả thông báo đẩy), đặt định kỳ, điểm thưởng và `SlotSelectionPolicy`.
  - Frontend: i18n tiếng Việt, chợ pass sân, bộ mô phỏng giá, và Điều khoản (định nghĩa "Khung giờ" là 1h).
  - Bản tiếng Anh giữ chữ "slot".

## 5. Ví và tài chính (design 8)

- [ ] 5.1 Ở tổ chức thuộc cohort direct, server từ chối nạp ví (`WalletDepositController`) và mọi tender ví trước khi thay đổi dữ liệu. Áp dụng cho booking, POS, tab dịch vụ và giải đấu.
- [ ] 5.2 `/me` thêm `capabilities.wallet_enabled`.
  - Giá trị true khi còn ít nhất một tổ chức active dùng mô hình ví cũ, hoặc khi người dùng có số dư ví khác 0.
  - Các điểm vào ví sau đều theo capability này:
    - menu "Ví Aura" trong `NavbarDesktopNav.vue` và `NavbarDrawerNavList.vue`;
    - thẻ ví trong `NavbarUserProfile.vue` và `ProfileShortcutsTab.vue`;
    - `WalletPayButton.vue`;
    - route `/wallet`.
- [ ] 5.3 `AdminLayout.vue`:
  - Nhóm Tài chính (Rút tiền, Đối soát, Ngoại lệ, gồm cả "Phí & Hoa hồng") chỉ hiện khi `marketplace_payouts` bật.
  - Mở link trực tiếp thì hiện "tính năng không khả dụng", không hiện trang rỗng.
  - Thêm `direct_owner_payments` vào kiểu capability ở TS nếu còn thiếu.
- [ ] 5.4 Test:
  - Backend: nạp ví và tender ví bị từ chối và số dư không đổi. `/me` trả đúng `wallet_enabled` trong cả hai trường hợp.
  - Frontend unit: menu ví và nhóm Tài chính ẩn hoặc hiện theo capability.
  - Playwright: sửa các spec đang giả định ví hoặc menu Tài chính hiện ra: `p1-legacy-wallet-direct-cohort`, `auth-journey-and-access-matrix`, `booking-access-loyalty-uat`, `p1-staff-management`, `seven-areas-acceptance`, `p1-revenue-reconciliation-reporting`.

## 6. QR ở POS về tài khoản chủ sân (design 4, 12)

- [ ] 6.1 Thêm service mới `PosQrPaymentService`, không nhồi thêm vào `PosService`. Service tạo attempt `pos_order` trên kết nối đã xác minh của cơ sở:
  - `purpose_id` là id đơn;
  - số tiền là phần trả bằng QR;
  - hạn của attempt là hạn của đơn;
  - mã `AC` do server sinh và lưu vào `payment_reference`. Mã tham chiếu client gửi lên bị bỏ qua.

  Service trả về `ReservationPaymentService::instructions($attempt)`.
- [ ] 6.2 Cơ sở chưa có kết nối đã xác minh:
  - Server từ chối tender QR kèm hướng dẫn cài đặt.
  - Config POS báo UI ẩn QR. Điều kiện này thay cho `isQrProviderAvailable`, vốn dựa vào tài khoản nền tảng.
- [ ] 6.3 `OwnerPaymentAttemptService::processReceipt` thêm nhánh `pos_order` và gọi sang `PosQrPaymentService`. Nhánh này khóa đơn rồi xử lý:
  - Đơn còn `pending_payment` và attempt còn hạn: chạy routine hoàn tất sẵn có (dòng thanh toán QR, xuất kho, loyalty). Không gọi `postPosSale` cho phần QR.
  - Đơn đã hủy hoặc hết hạn: ghi exception `pos_order_unavailable`.
- [ ] 6.4 Xóa:
  - route `pos/orders/{order}/confirm-qr`;
  - `PosService::confirmQrPayment`;
  - nhánh `POS-` của `ProcessQrPayment`;
  - khối QR dùng tài khoản nền tảng trong `PosService::completeOrder` (khoảng dòng 526–536).

  Route `cancel-qr` giữ lại và expire luôn attempt.
- [ ] 6.5 `ServiceTabService::settleTab` ở tổ chức direct chỉ nhận tiền mặt. `qr` và ví bị từ chối trước khi thay đổi dữ liệu.
- [ ] 6.6 Lên lịch `PosService::releaseExpiredPendingOrders` chạy mỗi phút cho các tổ chức active trong `routes/console.php`, có `withoutOverlapping` như các lịch hiện có. Hàm này expire luôn attempt tương ứng.
- [ ] 6.7 Frontend POS:
  - Hiện VietQR từ `instructions` và poll đơn tới khi hoàn tất.
  - Bỏ nút xác nhận QR thủ công.
  - Ẩn tender ví khi `direct_owner_payments` bật.
  - Ẩn QR khi cơ sở chưa có kết nối.
  - Tab dịch vụ ở cohort direct chỉ còn tiền mặt.
- [ ] 6.8 Test:
  - Viết lại `PosVerifiedPendingQrCollectionTest` theo đường owner attempt:
    - QR ghi đúng tài khoản chủ sân;
    - receipt gửi hai lần chỉ tạo một lần hoàn tất và một sự kiện kho/sổ;
    - doanh thu chỉ ghi một lần;
    - đơn hết hạn nhả kho đúng một lần, receipt đến muộn thành exception.
  - Sửa `PosShiftControlsAndOrderProvenanceTest` vì `confirm-qr` đã bị xóa.
  - Playwright `p0-pos-shift-service` dùng webhook kết nối với header Api_Key.

## 7. Bán lại slot giữa người mua và người bán (design 5, 12)

- [ ] 7.1 Migration thêm `marketplace_listings.seller_receiver_ciphertext` (text, nullable). `down()` xóa cột.
- [ ] 7.2 Đăng bán:
  - Mỗi listing là một entitlement, tức một slot đã đặt (1 giờ trong dữ liệu seed). Booking nhiều giờ thì đăng bán từng giờ, như hiện nay.
  - Listing có giá bắt buộc khai mã ngân hàng, số tài khoản và tên chủ tài khoản. Validate rồi lưu mã hóa.
  - Listing mới có `fee_bps = 0`.
  - Resource danh sách không bao giờ trả thông tin nhận tiền.
- [ ] 7.3 Route `checkout` chuyển thành khóa listing:
  - Chạy dưới row lock và chỉ từ trạng thái `listed`. Listing đang `checkout` mà khóa đã hết hạn thì được nhả trước.
  - Đặt status `checkout` và ghi metadata:
    - `locked_by`;
    - `locked_until` = min(now + 15 phút, cutoff);
    - `reference` = `RS` + 10 ký tự chữ hoa hoặc số.
  - Chỉ người khóa và người bán nhận VietQR của người bán với đúng giá bán. Dùng helper URL ảnh tách ra từ `ReservationPaymentService::instructions`.
  - Người thứ hai khóa cùng lúc nhận 409 và không thấy tài khoản người bán.
  - Listing miễn phí chuyển quyền ngay khi người mua chấp nhận, trong cùng giao dịch khóa.
- [ ] 7.4 Thêm ba endpoint, theo luồng Controller → FormRequest → Action/Service của dự án:
  - `payment-sent` (người mua): báo đã chuyển tiền.
  - `release`: người mua nhả khóa trước khi báo đã trả, hoặc người bán báo chưa nhận được tiền.
  - `confirm-receipt` (người bán):
    - Kiểm tra lại điều kiện: không có khiếu nại, người mua không bị hạn chế, chưa quá cutoff.
    - Chuyển quyền dùng sân trong một giao dịch nguyên tử.
    - Ghi bản ghi transfer `completed` với `fee=0`, `seller_refund=0`, `settlement_mode=peer_to_peer`, không có bút toán.

  Mỗi bước gửi thông báo cho bên còn lại.
- [ ] 7.5 Xóa:
  - route `marketplace/listings/{listing}/purchase` và `MarketplaceService::purchase`;
  - `verifySellerReimbursementEvidence`;
  - nhánh `transfer_consideration` trong `processReceipt` và purpose này trong `PURPOSES`;
  - `hasConfirmableListing` và `confirmPaidAttempt`, nếu chúng chỉ phục vụ đường cũ.

  Bản ghi `compensated` và hoa hồng cũ giữ nguyên.
- [ ] 7.6 Sweep chạy mỗi phút:
  - nhả các khóa đã hết hạn;
  - listing qua cutoff mà chưa bán thì chuyển sang `withdrawn`.

  Mọi lệnh đọc khóa coi khóa đã hết hạn là đã nhả, không chờ sweep.
- [ ] 7.7 Khiếu nại về bán lại tạo support case có `reference_type` trỏ tới listing.
- [ ] 7.8 Frontend:
  - Sửa `useResaleMarketplace`, `marketplaceApi` và các kiểu dữ liệu liên quan.
  - Form đăng bán thêm phần tài khoản nhận tiền.
  - Thêm các nút khóa, đã chuyển tiền, nhả và đã nhận tiền. Poll trạng thái.
  - Trong `useResaleMarketplace.ts`:
    - Xóa `getFallbackListings`. Hàm này hiện bốn tin pass giả (id 901–904, có tin dài 2 giờ) khi API trả rỗng hoặc lỗi.
    - Bỏ giá dự phòng 200000 (dòng 363, 421).
  - UI ghi rõ tiền đi thẳng giữa người mua và người bán.
- [ ] 7.9 Test:
  - Phủ các scenario của spec `advanced-booking-marketplace`:
    - mỗi lúc chỉ một người khóa được, người thứ hai nhận 409;
    - nhả khóa;
    - xác nhận chuyển quyền nguyên tử;
    - quá cutoff;
    - listing miễn phí;
    - danh sách không lộ tài khoản người bán;
    - bán một giờ trong booking hai giờ thì giờ còn lại vẫn thuộc người bán.
  - Sửa `MarketplaceTransferVerificationTest`, `ComprehensiveConcurrencyAndRetryInvariantsTest` và `AdvancedBookingMarketplaceTest`.
  - Viết lại Playwright `p1-resale-marketplace`.

## 8. Phí giải đấu qua QR chủ sân (design 6)

- [ ] 8.1 Migration thêm `tournaments.venue_id`: nullable, FK tới `venues` với restrict on delete, có index. `down()` xóa cột.
- [ ] 8.2 Tạo và sửa giải:
  - `venue_id` bắt buộc khi phí lớn hơn 0. Cơ sở phải thuộc tổ chức của giải và có kết nối nhận tiền đã xác minh.
  - `settings['venue']` vẫn giữ tên hiển thị.
  - Sửa `TournamentController` (khoảng dòng 83 và 99).
  - `TournamentCreateModal.vue` đổi ô nhập tự do thành select cơ sở của tổ chức.
- [ ] 8.3 Thêm service mới `TournamentRegistrationPaymentService`:
  - Dưới khóa dòng giải, đếm người đã xác nhận cộng số attempt `tournament_registration` đang chờ và còn hạn, rồi so với sức chứa.
  - Tạo hoặc trả lại attempt của người gọi, idempotent theo cặp (giải, người dùng).
  - Hạn 15 phút, không vượt quá hạn đăng ký.
  - Trả về `instructions()`.

  Thêm route `GET tournaments/{tournament}/registration` để trang thanh toán poll.
- [ ] 8.4 `processReceipt` thêm nhánh `tournament_registration`:
  - tạo participant giống đường miễn phí và ghi kèm receipt;
  - giải đã đóng hoặc attempt hết hạn thì ghi exception `tournament_registration_unavailable`.
- [ ] 8.5 `TournamentService`:
  - Bỏ mọi lệnh trừ ví trong `register` và `addParticipant`, bỏ phụ thuộc `WalletPaymentService`.
  - Ban tổ chức thêm người vào giải có phí thì bắt buộc có `waiver_reason`. Lý do được ghi vào participant (cột đã có sẵn) và audit log.
- [ ] 8.6 Frontend:
  - Trang thanh toán giải hiện QR và poll trạng thái.
  - Modal thêm người có ô nhập lý do miễn phí.
- [ ] 8.7 Test:
  - Sức chứa tính cả attempt đang chờ.
  - Đăng ký idempotent.
  - Receipt gửi hai lần chỉ tạo một participant.
  - Receipt đến muộn thành exception.
  - Thêm người mà không có lý do thì bị từ chối.
  - Không còn bút toán ví.
  - Sửa `TournamentCompleteLifecycleTest` và `TournamentRegistrationAndLifecycleTest`.
  - Playwright `p1-tournaments-lifecycle` thêm case giải có phí, thanh toán qua webhook tổng hợp.

## 9. Chỉ công khai sân có giá (design 9)

- [ ] 9.1 Tách query nạp rule giá trong `PriceQuoteService::priceSnapshots` thành một method dùng chung, và thêm predicate "sân có giá".
  - Một sân có giá khi có ít nhất một `PriceRule` active theo org, venue, zone hoặc court, hoặc một `CourtPriceSlot` active.
- [ ] 9.2 Áp predicate:
  - `VerifyEffectiveBookingEligibility`: phủ trang chi tiết, lịch trống và booking mới.
  - `CourtController::index`: lọc bằng một query gộp, không N+1.
- [ ] 9.3 Frontend bỏ giá dự phòng ở `useCourtDetail.ts` (dòng 70, 72), `CourtItemCard.vue` (dòng 87), `VenueCourtsGrid.vue` (dòng 12, 14) và `HomeView.vue` (dòng 601).
- [ ] 9.4 Test:
  - Sân không có rule bị ẩn khỏi danh sách và không đặt được.
  - Sân chỉ có rule cấp tổ chức vẫn hiện.
  - Số query của index không tăng theo số sân.

## 10. Seed khớp luồng thật (design 3, 11)

- [ ] 10.1 `UserSeeder::receivingConnection` đọc bốn biến `SEED_DEMO_RECEIVER_*`.
  - Ngoài `local` và `testing`, thiếu bất kỳ biến nào thì seed dừng, theo mẫu `hashedPassword`.
  - Mọi kết nối cơ sở và tài khoản người bán lại đều dùng tài khoản này.
  - Chỉ `aura-court` nhận key từ env. Các kết nối khác dùng key ngẫu nhiên.
  - Seeder in ra URL webhook của Cầu Giấy (routing id uuid5 `auracourt-demo/aura-court`) và không bao giờ in key.
  - Thêm bốn khóa, để trống giá trị, vào `.env.example`.
- [ ] 10.2 Bỏ nạp ví (`topUp`) và dữ liệu trả bằng ví ở `UserSeeder`, `BookingPaymentSeeder`, `RecurringSeriesSeeder`, `PosSalesSeeder`, `TournamentSeeder` và `MarketplaceSeeder`.
- [ ] 10.3 Bỏ payout, settlement, beneficiary, hoàn tiền, hủy sân và khiếu nại xử lý bằng hoàn tiền ở `UserSeeder`, `DisputeFinanceSeeder`, `BookingPaymentSeeder`, `RecurringSeriesSeeder` và `MatchmakingSeeder`.
- [ ] 10.4 Lịch sử đã thanh toán được ghi như code thật ghi: attempt `paid`, receipt `allocated`, bút toán `owner.direct_receipt`.
  - Không ghi bản ghi `payments` của nền tảng.
  - Đơn QR trong `PosSalesSeeder` (khoảng dòng 307–320) trỏ về kết nối của cơ sở, không trỏ về tài khoản nền tảng.
- [ ] 10.5 Chính sách seed (`OrganizationVenueSeeder::bookingPolicy` và `E2ESeedFixturesCommand`, khoảng dòng 500–536):
  - Bỏ `cancellation`, `change`, `ancillary_credit_policy` và `transfer.seller_reimbursement_required`.
  - Thời lượng tối thiểu và tối đa giữ 60 và 240 phút.
- [ ] 10.6 `MarketplaceSeeder`: listing P2P có tài khoản người bán, cùng vài transfer đã hoàn tất với `fee=0`.
- [ ] 10.7 `TournamentSeeder`:
  - mọi giải đều có `venue_id`;
  - thêm một giải sắp diễn ra ở Cầu Giấy, phí 2.000–10.000 VND, hạn đăng ký ít nhất 7 ngày sau lúc seed.
- [ ] 10.8 `PosProductSeeder`: thêm một sản phẩm ở Cầu Giấy giá không quá 10.000 VND.
- [ ] 10.9 Test seed:
  - không ví nào có số dư;
  - không có payout, settlement hay hoàn tiền;
  - không có attempt `booking_change`;
  - mọi kết nối dùng đúng tài khoản đã cấu hình;
  - mọi sân công khai đều có giá;
  - mọi khu sân có `slot_duration` 60; mọi slot đặt, entitlement và slot giá bắt đầu đúng giờ và dài 60 phút; mọi chính sách tính theo giờ chẵn;
  - thiếu biến env ngoài `local`/`testing` thì seed dừng.

## 11. Tài liệu

- [ ] 11.1 `docs/demo-accounts.md`:
  - Sửa đoạn về cờ (dòng 37–39) theo `PILOT_ORGANIZATION_IDS=*`.
  - Bỏ "ví" trong dòng của `user@example.com` (dòng 54) và ghi chú "Chi trả" (dòng 75).
  - Thêm kịch bản demo:
    - Mọi thứ cần tự xác nhận (booking, POS, giải đấu) đều làm ở Cầu Giấy.
    - Bán lại slot do người bán xác nhận.
    - Khách không đến được thì bán lại slot. Không có hủy hay dời lịch.
- [ ] 11.2 Viết tài liệu vận hành SePay, gồm:
  - mẫu mã thanh toán;
  - webhook Api_Key cho từng kết nối;
  - xoay key bằng endpoint rotate;
  - tắt webhook nền tảng cũ;
  - cách tra tiền không khớp trong SePay hoặc app ngân hàng.
- [ ] 11.3 `docs/testing/playwright-coverage.md`:
  - Bỏ các dòng FR-BKG-11 đến FR-BKG-14 và FR-PAY-09 đến FR-PAY-11 (dòng 99–102, 118–120). Các dòng này đang trỏ tới spec Playwright đã xóa.
  - Bỏ mục P0-08 (dòng 204).
  - Ghi đúng mã FR theo bản báo cáo đã sửa.
- [ ] 11.4 Khi có bản `dacn_report.docx` đã sửa, ghi bảng truy vết vào `report-traceability.md` của change này.
  - Bảng phủ các FR đã bỏ (hủy sân, hoàn tiền, dời lịch), luồng bán lại P2P và định nghĩa slot 1 giờ.
  - Không sửa file trong `openspec/changes/archive/`.

## 12. Phối hợp với change khác (design 14)

- [x] 12.1 Sửa delta `openspec/changes/retire-v1-booking/specs/reservations-and-pricing/spec.md`:
  - Dòng 6: bỏ "booking changes" khỏi danh sách việc client phải dùng V2.
  - Dòng 12: bỏ câu "The former cancellation/rescheduling retirement SHALL be superseded by policy-governed requests."
  - Dòng 27: scenario client cũ gọi các lệnh hủy, hoàn tiền và dời lịch đã bị bỏ.
  - Chạy `openspec validate retire-v1-booking --strict`.
  - Đã làm cùng Phase C của `retire-v1-booking` (2026-10-13). Requirement đó cũng được đổi tên thành "Unversioned reservation API", nên change nào archive sau phải rebase cả tên requirement (design C7 của change đó).
- [ ] 12.2 Thêm ghi chú vào `openspec/changes/retire-v1-booking/tasks.md`:
  - 3.3: `BookingCancelModal` và `BookingRescheduleModal` đã bị xóa;
  - 3.7: case "huỷ" và "dời lịch đắt hơn và rẻ hơn" không còn;
  - 6.2: route hoàn tiền và dời lịch đã bị change này xóa;
  - 6.4: `OwnerRefundService` và `RescheduleService` đã bị xóa;
  - 6.8: webhook nền tảng không còn ghi `payments` cho POS.
- [ ] 12.3 Khi archive:
  - Change archive sau rebase khối MODIFIED lên spec chính.
  - Sửa tay phần Purpose của `direct-owner-payments`, `reservations-and-pricing` và `wallet-payment`.

## 13. Cổng kiểm tra local

- [ ] 13.1 Chạy toàn bộ PHPUnit trên DB `*_test` bằng một file phpunit config tạm.
  - Chạy `migrate:fresh`, rồi rollback từng bước qua hai migration mới.
- [ ] 13.2 Chạy Pint, và PHPStan level 5 với `.env` tạm, kết quả phải 0 lỗi. Không sinh lại baseline.
  - Test kiến trúc backend phải pass. Allowlist chỉ được giảm.
- [ ] 13.3 Frontend: type-check, ESLint, oxlint, Prettier, `vitest --maxWorkers=1` (chạy riêng, không song song với việc khác) và `architecture.spec.ts`. Không tăng allowlist.
- [ ] 13.4 Playwright p0 và p1 pass, với webhook tổng hợp dùng header Api_Key.

## 14. Việc người dùng tự làm (ngoài chat)

- [ ] 14.1 SePay: thêm mẫu mã thanh toán tiền tố `AC`, hậu tố 18–18 ký tự, kiểu NumberAndLetter.
- [ ] 14.2 SePay: thêm webhook cho tài khoản TPBank.
  - URL: URL của Cầu Giấy do seeder in ra.
  - Xác thực Api_Key, giá trị bằng `SEED_DEMO_RECEIVER_WEBHOOK_KEY`. Nên sinh key bằng `openssl rand -hex 32`.
  - Sự kiện: In_only.
- [ ] 14.3 Portainer stack 77: xóa biến `PILOT_ORGANIZATION_IDS=73,74` và xác nhận stack không còn biến `FEATURE_*` nào.
- [ ] 14.4 Chốt thời điểm reseed và deploy. Push `main` không còn tự deploy; prod chỉ đổi khi chạy pipeline trên `main` (change `manual-ci-pipeline`).
- [ ] 14.5 Bỏ các FR dời lịch khỏi báo cáo, như đã làm với hủy sân và hoàn tiền.
  - Trong bản cũ, đó là FR-BKG-11 "Yêu cầu thay đổi booking" và FR-BKG-12 "Xử lý thay đổi booking".
  - Ghi rõ slot dài 1 giờ.

## 15. Triển khai (design 13)

- [ ] 15.1 Seed `booking_demo_btprod` bằng `.env.btprod` với code của nhánh, rồi chạy lại các kiểm tra ở 10.9 trên DB đó.
- [ ] 15.2 Backup prod bằng `pg_dump` vào `backend/storage/app/backups/`.
- [ ] 15.3 Sau khi người dùng chốt giờ, reseed prod bằng dump/restore. Bước này phải xong trước khi deploy.
- [ ] 15.4 Merge `--no-ff` vào `main`, xóa nhánh và push `origin`.
  - Chạy pipeline trên `main` (`glab ci run -b main`) và chờ nó chạy xong.
  - Kiểm tra revision trong `build-info.json`.
- [ ] 15.5 Người dùng tắt webhook nền tảng cũ trong SePay.
- [ ] 15.6 Diễn tập trên prod bằng các khoản chuyển thật nhỏ. Ghi lại kết quả nhưng không ghi bí mật.
  - Booking ở Cầu Giấy tự xác nhận.
  - Đơn POS QR không quá 10.000 VND tự hoàn tất.
  - Đóng phí giải đấu thì người chơi được ghi danh.
  - Bán lại P2P đi trọn luồng: khóa, chuyển tiền, người bán xác nhận.
  - Booking 2 giờ hiện thành hai slot 1 giờ, mỗi slot đăng bán riêng được.
  - Không còn nút hủy, hoàn tiền hay dời lịch ở bất kỳ đâu.
  - Không còn ví, menu Tài chính hay "Phí & Hoa hồng".
  - Không còn sân chưa có giá, không còn sân "ABC" hay "ABCD".
  - Chuyển từ ít nhất hai ngân hàng khác nhau để chắc SePay nhận ra `code`.
- [ ] 15.7 Đóng băng code.
  - 1–2 ngày trước buổi bảo vệ, sau 07:00, reseed lại theo 15.1–15.3. Không cần merge.
  - Chạy lại smoke.
- [ ] 15.8 Sau buổi bảo vệ, xoay key webhook bằng endpoint rotate và cập nhật lại trong SePay.
