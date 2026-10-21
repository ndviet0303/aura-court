## 0. Điều kiện trước khi bắt đầu

- [x] 0.1 Tạo nhánh `feat/direct-owner-cutover` từ `main` (tạo từ `688e440f` ngày 2026-10-13).
  - Mọi lệnh artisan và test chỉ chạy trên DB `*_test` hoặc `booking_demo_btprod`.
  - Không dùng `backend/.env` vì file này trỏ vào prod.
- [x] 0.2 Người dùng tự điền bốn biến vào `backend/.env.btprod` (file này gitignored): `SEED_DEMO_RECEIVER_BANK`, `SEED_DEMO_RECEIVER_ACCOUNT`, `SEED_DEMO_RECEIVER_NAME`, `SEED_DEMO_RECEIVER_WEBHOOK_KEY`.
  - Giá trị không được xuất hiện trong chat, commit hay log.
  - Chỉ việc seed (mục 10, 15) cần các biến này. Code và test không phụ thuộc chúng.
  - 2026-10-14, theo yêu cầu người dùng: agent tạo file mới (worktree cũ chứa file đã bị xóa). Ngân hàng, số tài khoản và tên chủ tài khoản lấy từ `SEPAY_*` trong `backend/.env`. Webhook key và ba mật khẩu seed sinh ngẫu nhiên. `APP_KEY` giữ giống prod. DB trỏ tới `booking_demo_btprod` local. Không in giá trị nào.

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

- [x] 3.1 Xóa route trong `routes/api.php`:
  - v1: `bookings/{reservation}/refund-quote` và `refund-requests` (dòng 267–269), `reschedule-preview` và `reschedule-requests` (dòng 270–271).
  - v2: `reservations/{reservation}/refund-quote`, `refund-requests`, hộp thư `refund-requests` cùng approve, reject, report-execution, acknowledge, và `reschedule-preview`, `reschedule-requests` (dòng 435–444).
  - `admin/refunds` (dòng 367).
  - Xóa các `use` controller không còn dùng.
- [x] 3.2 Xóa `OwnerRefundService`, `OwnerRefundController`, `AdminController::refunds` và code chỉ chúng dùng.
  - Model và bảng hoàn tiền giữ lại làm lịch sử.
- [x] 3.3 Xóa dời lịch:
  - `RescheduleService` và `RescheduleController`.
  - Purpose `booking_change`:
    - mục trong `OwnerPaymentAttemptService::PURPOSES`;
    - nhánh `booking_change` trong `processReceipt` (khoảng dòng 329);
    - mục trong danh sách purpose của `ExpirePendingBookings` (dòng 106).
  - Không giữ handler cho attempt `booking_change` cũ. Prod không có attempt nào (kiểm tra ngày 2026-10-13), và seed cũng không tạo.
  - Tham số `excludeReservationId` và `excludeBookingSlotIds` của `SlotSelectionPolicy::assertAllowed`, cùng các nhánh lọc theo chúng. Sửa docblock cho hết nhắc tới reschedule.
  - Ghi chú: `excludeBookingSlotIds` không còn tồn tại trong code. Tham số `excludeReservationId` chết tương ứng trong `CourtCommitmentConflictService` cũng bị xóa (chỉ dời lịch dùng).
- [x] 3.4 `RecurringReleaseService`:
  - Xóa `repairLegacyCancel`, `recordPaidValue`, `refundAttempt`, action `RepairLegacyCancelledContracts` và lệnh `contracts:repair-legacy-cancel`.
  - `cancelSeries` (kết thúc hợp đồng) chỉ nhả các buổi chưa thanh toán. Buổi đã thanh toán vẫn giữ.
  - Ghi chú: "đã thanh toán" = kỳ thuộc hóa đơn `paid`. Hóa đơn `pending`/`guaranteed` của kỳ chưa bắt đầu bị hủy; không tạo FinanceException hay hoàn tiền.
- [x] 3.5 `SplitPaymentService`:
  - Xóa `refundAllocations` (đang là code chết).
  - Nhóm chia tiền hết hạn ghi một `FinanceException` cho mỗi phần đã trả, không tạo nghĩa vụ hoàn tiền.
  - Ghi chú: logic nằm ở service mới `SplitExpiredShareExceptions` (type `split_expired_paid_share`, reference `split-share:{id}`).
- [x] 3.6 `ArbitrateDispute`: bỏ quyết định `resolve_and_refund` ở request validation, action và resource.
  - Ghi chú: `refund_amount`/`refund_method` vẫn được nhận và lưu như phát hiện của phán quyết (không tạo nghĩa vụ). Resource bỏ các trường theo dõi hoàn tiền (`payment_completed`, `refund_obligation_status`, `has_pending_execution`), giữ `refund_obligation_id` làm lịch sử. `AdminDisputeController` bỏ chặn đóng case khi còn nghĩa vụ hoàn tiền, nếu không case cũ không bao giờ đóng được.
- [x] 3.7 Chính sách cơ sở:
  - `StoreVenueBookingPolicyRequest` bỏ `cancellation`, `change`, `ancillary_credit_policy` và `transfer.seller_reimbursement_required`. Bỏ luôn vòng kiểm phí `['cancellation', 'change']` trong `withValidator`.
  - Readiness chỉ kiểm tra có chính sách hay không, nên không phải sửa.
  - Phiên bản chính sách và snapshot booking đã lưu giữ nguyên các khóa này làm lịch sử.
- [x] 3.8 Frontend:
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
  - Ghi chú: nút bán lại là link tới route có sẵn `resale-marketplace` (`/resale`), chỉ hiện với booking đã xác nhận. ArbitrationDrawer bỏ luôn ô phương thức/số tiền hoàn. Bỏ thêm tag "Bảo chứng hoàn tiền" ở `AuthBrandPanel.vue` (hứa hoàn tiền).
- [x] 3.9 Test backend:
  - Các route đã xóa (hoàn tiền và dời lịch, v1 và v2) trả 404 và không làm đổi dữ liệu.
  - Kết thúc hợp đồng vẫn giữ các buổi đã trả.
  - Nhóm chia tiền hết hạn tạo một exception cho mỗi phần đã trả.
  - Xóa `RescheduleAtomicSwapTest`, `OwnerRefundInboxTest` và `OwnerRefundObligationTest`.
  - Sửa các test còn gọi đường hoàn tiền hoặc dời lịch. Liệt kê bằng `grep -rlE "refund-requests|refund-quote|admin/refunds|resolve_and_refund|repairLegacyCancel|refundAllocations|OwnerRefund|reschedule|booking_change|excludeReservationId" backend/tests`.
    - Hiện danh sách gồm `BlockedCommandsInvariantsTest`, `ReservationSlotOverlapExclusionTest`, `SuspendedPartnerObligationsAndAuthorizationTest`, `BookingFlowsTest`, `OrdinaryWalletCheckoutTest`, `ComprehensiveConcurrencyAndRetryInvariantsTest` và `CommunityActivityAndReputationTest`.
    - Giữ các kiểm tra rằng lịch sử hoàn tiền cũ vẫn đọc được.
  - Bỏ các mục đã xóa khỏi `tests/Unit/Architecture/architecture-allowlist.json`. Ceiling chỉ được giảm.
- [x] 3.10 Playwright:
  - Xóa `p0-cancellation-refund-reschedule.spec.ts` và sửa mọi nơi tham chiếu tên file này.
  - `p0-booking-payment-journey` thêm kiểm tra: booking đã thanh toán không có nút hủy, hoàn tiền hay dời lịch, nhưng có nút bán lại.
  - Bỏ `resolve_and_refund` trong `p1-dispute-lifecycle`.
  - Sửa phần hoàn tiền và hủy sân trong `p0-booking-payment-journey`, `p1-split-payments`, `p1-venue-lifecycle`, `p1-partner-registration` và `p1-legacy-wallet-direct-cohort`.
  - Ghi chú: chưa chạy Playwright. `p1-legacy-wallet-direct-cohort` 8.6.3 bị xóa vì gọi `OwnerRefundService`. `p1-partner-registration` không cần sửa. Tham chiếu trong `openspec/changes/archive/...` giữ nguyên vì là lịch sử đã lưu trữ.

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

- [x] 5.1 Ở tổ chức thuộc cohort direct, server từ chối nạp ví (`WalletDepositController`) và mọi tender ví trước khi thay đổi dữ liệu. Áp dụng cho booking, POS, tab dịch vụ và giải đấu.
  - Ghi chú: một helper dùng chung `WalletAvailabilityService::assertAvailable` (409 `DIRECT_OWNER_PAYMENTS_ENABLED`, giữ mã cũ vì frontend/Playwright đang dùng; message đổi sang tiếng Việt). Mỗi file chỉ một lời gọi: `PosService::completeOrder` (tender `wallet` hoặc tender hỗn hợp có ví), `ServiceTabService::settleTab`, `TournamentService::register`/`addParticipant`, `ReservationService::confirm`. Thêm chặn ở `POST wallet/spend` và `wallet/credit` của POS (cũng là nạp/tiêu ví). Phần 6.5/6.7/8.5 sẽ sửa tiếp các file này. `TournamentService` gọn lại còn 718 dòng, hạ trần allowlist.
- [x] 5.2 `/me` thêm `capabilities.wallet_enabled`.
  - Giá trị true khi còn ít nhất một tổ chức active dùng mô hình ví cũ, hoặc khi người dùng có số dư ví khác 0.
  - Các điểm vào ví sau đều theo capability này:
    - menu "Ví Aura" trong `NavbarDesktopNav.vue` và `NavbarDrawerNavList.vue`;
    - thẻ ví trong `NavbarUserProfile.vue` và `ProfileShortcutsTab.vue`;
    - `WalletPayButton.vue`;
    - route `/wallet`.
  - Ghi chú: `WalletPayButton.vue` không còn (xóa ở 7d17ac5f khi bỏ V1). Gate thêm các điểm vào ví khác: pill số dư ở `Navbar.vue` (và chỉ gọi `fetchWalletBalance` khi bật), nút ví ở `NavbarDrawerUserCard.vue`, CTA ở `ProfileMembershipTab.vue`. Route `/wallet` chuyển về `home` khi tắt. Ẩn là mặc định: response login chưa có `capabilities`, nên ví ẩn đến khi `/me` nạp lại.
- [x] 5.3 `AdminLayout.vue`:
  - Nhóm Tài chính (Rút tiền, Đối soát, Ngoại lệ, gồm cả "Phí & Hoa hồng") chỉ hiện khi `marketplace_payouts` bật.
  - Mở link trực tiếp thì hiện "tính năng không khả dụng", không hiện trang rỗng.
  - Thêm `direct_owner_payments` vào kiểu capability ở TS nếu còn thiếu.
  - Ghi chú: ba route tài chính có `meta.capability: 'marketplace_payouts'`; component mới `CapabilityRouteView.vue` thay `<RouterView />` trong layout, nạp capability và hiện "Tính năng không khả dụng" khi tắt. "Phí & Hoa hồng" nằm trong trang Rút tiền nên đi theo. Link nhanh Rút tiền ở `AdminQuickNav.vue` cũng ẩn theo cờ.
- [x] 5.4 Test:
  - Backend: nạp ví và tender ví bị từ chối và số dư không đổi. `/me` trả đúng `wallet_enabled` trong cả hai trường hợp.
  - Frontend unit: menu ví và nhóm Tài chính ẩn hoặc hiện theo capability.
  - Playwright: sửa các spec đang giả định ví hoặc menu Tài chính hiện ra: `p1-legacy-wallet-direct-cohort`, `auth-journey-and-access-matrix`, `booking-access-loyalty-uat`, `p1-staff-management`, `seven-areas-acceptance`, `p1-revenue-reconciliation-reporting`.
  - Ghi chú: chưa chạy Playwright (theo yêu cầu). Các spec dùng `/wallet` làm route bảo vệ mẫu chuyển sang `/profile`; trang tài chính trên stack e2e (`marketplace_payouts` tắt) được kiểm là "không khả dụng". `p1-legacy-wallet-direct-cohort` kiểm UI theo `wallet_enabled` từ `/api/auth/me`. `p1-staff-management` không giả định ví hay menu Tài chính nên không sửa.

## 6. QR ở POS về tài khoản chủ sân (design 4, 12)

- [x] 6.1 Thêm service mới `PosQrPaymentService`, không nhồi thêm vào `PosService`. Service tạo attempt `pos_order` trên kết nối đã xác minh của cơ sở:
  - `purpose_id` là id đơn;
  - số tiền là phần trả bằng QR;
  - hạn của attempt là hạn của đơn;
  - mã `AC` do server sinh và lưu vào `payment_reference`. Mã tham chiếu client gửi lên bị bỏ qua.

  Service trả về `ReservationPaymentService::instructions($attempt)`.
  - Ghi chú: `PosService::completeOrder` chỉ gọi `PosQrPaymentService::open` ở cuối transaction; `instructions` trả về ở field `payment_attempt` của response `POST pos/orders` và `GET pos/orders/{id}` (receipt không còn `qr_data`). Cơ sở của đơn: `venue_id` gửi lên, rồi venue của ca, rồi venue của reservation, cuối cùng là venue duy nhất cashier được gán (UI mở ca không kèm venue). Không xác định được một venue thì coi như chưa có kết nối.
- [x] 6.2 Cơ sở chưa có kết nối đã xác minh:
  - Server từ chối tender QR kèm hướng dẫn cài đặt.
  - Config POS báo UI ẩn QR. Điều kiện này thay cho `isQrProviderAvailable`, vốn dựa vào tài khoản nền tảng.
  - Ghi chú: Tín hiệu cho UI là `shift.qr_available` trên `GET pos/shifts/active` (POS không có endpoint config riêng). Từ chối là 422 với hướng dẫn tiếng Việt, trước mọi thay đổi dữ liệu.
- [x] 6.3 `OwnerPaymentAttemptService::processReceipt` thêm nhánh `pos_order` và gọi sang `PosQrPaymentService`. Nhánh này khóa đơn rồi xử lý:
  - Đơn còn `pending_payment` và attempt còn hạn: chạy routine hoàn tất sẵn có (dòng thanh toán QR, xuất kho, loyalty). Không gọi `postPosSale` cho phần QR.
  - Đơn đã hủy hoặc hết hạn: ghi exception `pos_order_unavailable`.
  - Ghi chú: `processReceipt` chuyển sang bảng `CONFIRMERS` (purpose → service, hàm kiểm tra, mã exception) để giữ trần 555 dòng (nay 541); hành vi các nhánh cũ không đổi. Đơn mixed: phần không phải QR ghi `postPosSale` lúc hoàn tất, phần QR chỉ có journal `owner.direct_receipt`. Hủy/hết hạn expire attempt trước nên receipt muộn thường thành `attempt_not_pending`/`transfer_after_expiry`; `pos_order_unavailable` cho đơn bị hủy khi attempt còn sống.
- [x] 6.4 Xóa:
  - route `pos/orders/{order}/confirm-qr`;
  - `PosService::confirmQrPayment`;
  - nhánh `POS-` của `ProcessQrPayment`;
  - khối QR dùng tài khoản nền tảng trong `PosService::completeOrder` (khoảng dòng 526–536).

  Route `cancel-qr` giữ lại và expire luôn attempt.
  - Ghi chú: Xóa luôn `isQrProviderAvailable` và khối kiểm tra provider trong tender hỗn hợp. Thứ tự khóa khi hủy: attempt trước, đơn sau (giống receipt processing).
- [x] 6.5 `ServiceTabService::settleTab` ở tổ chức direct chỉ nhận tiền mặt. `qr` và ví bị từ chối trước khi thay đổi dữ liệu.
  - Ghi chú: Ví đã bị chặn bởi `WalletAvailabilityService` (mục 5); QR bị từ chối 409 khi `direct_owner_payments` bật, hướng dẫn tạo đơn POS. `ServiceTabService` giữ đúng 439 dòng.
- [x] 6.6 Lên lịch `PosService::releaseExpiredPendingOrders` chạy mỗi phút cho các tổ chức active trong `routes/console.php`, có `withoutOverlapping` như các lịch hiện có. Hàm này expire luôn attempt tương ứng.
  - Ghi chú: Lịch `pos:release-expired-orders` (`withoutOverlapping(10)`, `onFailure` như các lịch khác); lỗi từng tổ chức được log, không chặn tổ chức khác. Hết hạn đi qua `cancelPendingQr` nên expire attempt.
- [x] 6.7 Frontend POS:
  - Hiện VietQR từ `instructions` và poll đơn tới khi hoàn tất.
  - Bỏ nút xác nhận QR thủ công.
  - Ẩn tender ví khi `direct_owner_payments` bật.
  - Ẩn QR khi cơ sở chưa có kết nối.
  - Tab dịch vụ ở cohort direct chỉ còn tiền mặt.
  - Ghi chú: Poll `GET pos/orders/{id}` mỗi 3 giây; hoàn tất thì hiện receipt của server, hủy/hết hạn thì báo `qrExpiredNotice`. Nút tender dựng từ danh sách để ẩn QR/ví (PosCartPanel 1151 → 1135 dòng). Tab dịch vụ ở cohort direct luôn gửi `cash`. Mở ca xong đọc lại ca active để có `qr_available`. Trần PosView hạ còn 772/550.
- [x] 6.8 Test:
  - Viết lại `PosVerifiedPendingQrCollectionTest` theo đường owner attempt:
    - QR ghi đúng tài khoản chủ sân;
    - receipt gửi hai lần chỉ tạo một lần hoàn tất và một sự kiện kho/sổ;
    - doanh thu chỉ ghi một lần;
    - đơn hết hạn nhả kho đúng một lần, receipt đến muộn thành exception.
  - Sửa `PosShiftControlsAndOrderProvenanceTest` vì `confirm-qr` đã bị xóa.
  - Playwright `p0-pos-shift-service` dùng webhook kết nối với header Api_Key.
  - Ghi chú: Thêm trait `tests/Concerns/PaysOwnerByQr`. Sửa thêm hai test readiness (kết nối venue trước khi tạo đơn QR) và xóa `RetiredV1BookingRoutesTest::test_sepay_webhook_settles_a_pos_qr_order_once` (nhánh `POS-` đã xóa). Playwright `p0-pos-shift-service` mới sửa, chưa chạy: `confirm-qr` giờ 404, gửi lại cùng event webhook không trừ kho lần hai. PHPStan: thêm generic cho `Venue::currentReceivingConnection`, `PosOrder::items`/`shift`, `PosOrderItem::product`; baseline chỉ bớt mục cũ (−120 dòng).

## 7. Bán lại slot giữa người mua và người bán (design 5, 12)

- [x] 7.1 Migration thêm `marketplace_listings.seller_receiver_ciphertext` (text, nullable). `down()` xóa cột.
  - Ghi chú: `up()` đã chạy qua bộ PHPUnit trên DB test riêng; `down()` chưa chạy riêng (không chạy artisan cạnh `.env` production), cần kiểm ở gate migrate/rollback.
- [x] 7.2 Đăng bán:
  - Mỗi listing là một entitlement, tức một slot đã đặt (1 giờ trong dữ liệu seed). Booking nhiều giờ thì đăng bán từng giờ, như hiện nay.
  - Listing có giá bắt buộc khai mã ngân hàng, số tài khoản và tên chủ tài khoản. Validate rồi lưu mã hóa.
  - Listing mới có `fee_bps = 0`.
  - Resource danh sách không bao giờ trả thông tin nhận tiền.
  - Ghi chú: service không bắt buộc tài khoản nhận tiền khi đăng bán (để `MarketplaceSeeder` vẫn chạy, seed bổ sung ở 10.6); việc bắt buộc nằm ở `StoreResaleListingRequest`, và khóa listing có giá mà thiếu tài khoản trả 409. Giá 0 chỉ được khi chính sách sân bật `transfer.free_transfer_allowed`. Endpoint đăng bán chuyển sang `ResaleListingController` mới.
- [x] 7.3 Route `checkout` chuyển thành khóa listing:
  - Chạy dưới row lock và chỉ từ trạng thái `listed`. Listing đang `checkout` mà khóa đã hết hạn thì được nhả trước.
  - Đặt status `checkout` và ghi metadata:
    - `locked_by`;
    - `locked_until` = min(now + 15 phút, cutoff);
    - `reference` = `RS` + 10 ký tự chữ hoa hoặc số.
  - Chỉ người khóa và người bán nhận VietQR của người bán với đúng giá bán. Dùng helper URL ảnh tách ra từ `ReservationPaymentService::instructions`.
  - Người thứ hai khóa cùng lúc nhận 409 và không thấy tài khoản người bán.
  - Listing miễn phí chuyển quyền ngay khi người mua chấp nhận, trong cùng giao dịch khóa.
  - Ghi chú: helper ảnh VietQR là `App\Services\Payment\VietQrImage`. Listing miễn phí cần `accept_free_transfer=true` (thiếu thì 422). Logic nằm trong `PeerToPeerResaleService` mới; phần đọc trạng thái (`GET marketplace/listings/{listing}`, chỉ cho người bán, người đang khóa và người mua từng khóa) nằm trong `ResaleListingState`.
- [x] 7.4 Thêm ba endpoint, theo luồng Controller → FormRequest → Action/Service của dự án:
  - `payment-sent` (người mua): báo đã chuyển tiền.
  - `release`: người mua nhả khóa trước khi báo đã trả, hoặc người bán báo chưa nhận được tiền.
  - `confirm-receipt` (người bán):
    - Kiểm tra lại điều kiện: không có khiếu nại, người mua không bị hạn chế, chưa quá cutoff.
    - Chuyển quyền dùng sân trong một giao dịch nguyên tử.
    - Ghi bản ghi transfer `completed` với `fee=0`, `seller_refund=0`, `settlement_mode=peer_to_peer`, không có bút toán.

  Mỗi bước gửi thông báo cho bên còn lại.
  - Ghi chú: `settlement_mode=peer_to_peer` lưu trong `entitlement_transfers.metadata` (bảng không có cột riêng). Người bán xác nhận khi người mua chưa báo đã trả vẫn được. Sau khi người mua báo đã trả, khóa giữ tới cutoff và người mua không tự nhả được nữa. `reservation.user_id` chỉ đổi sang người mua khi người mua giữ mọi slot còn hiệu lực.
- [x] 7.5 Xóa:
  - route `marketplace/listings/{listing}/purchase` và `MarketplaceService::purchase`;
  - `verifySellerReimbursementEvidence`;
  - nhánh `transfer_consideration` trong `processReceipt` và purpose này trong `PURPOSES`;
  - `hasConfirmableListing` và `confirmPaidAttempt`, nếu chúng chỉ phục vụ đường cũ.

  Bản ghi `compensated` và hoa hồng cũ giữ nguyên.
  - Ghi chú: xóa thêm `MarketplaceService::checkout` và `FinancialPostingService::postMarketplaceSale` (không còn ai gọi). Attempt mang purpose không còn hỗ trợ giờ thành ngoại lệ `unsupported_purpose` thay vì được ghi nhận.
- [x] 7.6 Sweep chạy mỗi phút:
  - nhả các khóa đã hết hạn;
  - listing qua cutoff mà chưa bán thì chuyển sang `withdrawn`.

  Mọi lệnh đọc khóa coi khóa đã hết hạn là đã nhả, không chờ sweep.
  - Ghi chú: lịch `marketplace:sweep-resale-locks` (mục 1d trong `routes/console.php`). Đọc lười qua `MarketplaceListing::lockActive()`, accessor `effective_status` và scope `availableTo`.
- [x] 7.7 Khiếu nại về bán lại tạo support case có `reference_type` trỏ tới listing.
  - Ghi chú: `reference_type=marketplace_listing`, `reference_id` là id listing; chỉ người bán hoặc người mua từng khóa được mở. Tổ chức, sân và bên bị khiếu nại lấy từ sân của listing (vẫn là chủ sân, đúng luồng support case hiện có).
- [x] 7.8 Frontend:
  - Sửa `useResaleMarketplace`, `marketplaceApi` và các kiểu dữ liệu liên quan.
  - Form đăng bán thêm phần tài khoản nhận tiền.
  - Thêm các nút khóa, đã chuyển tiền, nhả và đã nhận tiền. Poll trạng thái.
  - Trong `useResaleMarketplace.ts`:
    - Xóa `getFallbackListings`. Hàm này hiện bốn tin pass giả (id 901–904, có tin dài 2 giờ) khi API trả rỗng hoặc lỗi.
    - Bỏ giá dự phòng 200000 (dòng 363, 421).
  - UI ghi rõ tiền đi thẳng giữa người mua và người bán.
  - Ghi chú: thêm `useResaleLock` (poll 5 giây khi đang giữ chỗ hoặc đã báo trả), `ResalePaymentDetails` và `ResaleReceiverFields`. Bỏ `seller_refund_accepted` và lựa chọn ví. Người bán nhập lý do "chưa nhận được tiền" qua `window.prompt`, theo kiểu `confirm()` sẵn có.
- [x] 7.9 Test:
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
  - Ghi chú: Playwright `p1-resale-marketplace` đã viết lại nhưng CHƯA chạy. Kịch bản listing miễn phí chỉ có trong PHPUnit. Thêm `GatedMarketplaceAndSplitRegressionTest`, `RestrictedAccountLifecycleTest`, `ZeroCommissionRetirementRegressionTest` và vitest `useResaleLock.spec.ts`. JSON listing bỏ `locked_by`, `reference`, `buyer_ids`, `last_release` khỏi `metadata`.

## 8. Phí giải đấu qua QR chủ sân (design 6)

- [x] 8.1 Migration thêm `tournaments.venue_id`: nullable, FK tới `venues` với restrict on delete, có index. `down()` xóa cột.
  - Ghi chú: `down()` (drop FK, index, cột) chưa chạy riêng; schema conformance/FK-index test xanh.
- [x] 8.2 Tạo và sửa giải:
  - `venue_id` bắt buộc khi phí lớn hơn 0. Cơ sở phải thuộc tổ chức của giải và có kết nối nhận tiền đã xác minh.
  - `settings['venue']` vẫn giữ tên hiển thị.
  - Sửa `TournamentController` (khoảng dòng 83 và 99).
  - `TournamentCreateModal.vue` đổi ô nhập tự do thành select cơ sở của tổ chức.
  - Ghi chú: hiện không có endpoint sửa giải, nên chỉ áp dụng ở `create` qua `TournamentRegistrationPaymentService::assertVenueCanCollect` (dùng `PosQrPaymentService::connectionFor` làm kiểm tra kết nối đã xác minh).
- [x] 8.3 Thêm service mới `TournamentRegistrationPaymentService`:
  - Dưới khóa dòng giải, đếm người đã xác nhận cộng số attempt `tournament_registration` đang chờ và còn hạn, rồi so với sức chứa.
  - Tạo hoặc trả lại attempt của người gọi, idempotent theo cặp (giải, người dùng).
  - Hạn 15 phút, không vượt quá hạn đăng ký.
  - Trả về `instructions()`.

  Thêm route `GET tournaments/{tournament}/registration` để trang thanh toán poll.
  - Ghi chú: controller gọi service thanh toán, service này phụ thuộc `TournamentService` (không vòng lặp). `register` trả 201 (miễn phí), 202 (chờ QR), 200 (đã ghi danh).
- [x] 8.4 `processReceipt` thêm nhánh `tournament_registration`:
  - tạo participant giống đường miễn phí và ghi kèm receipt;
  - giải đã đóng hoặc attempt hết hạn thì ghi exception `tournament_registration_unavailable`.
  - Ghi chú: chuyển khoản sau hạn đã được `applyAttemptChecks` chung xử lý trước nhánh purpose (exception `transfer_after_expiry`, attempt `late`); `tournament_registration_unavailable` phủ giải đã đóng/đầy/đã bắt đầu hoặc người đã ghi danh. Receipt được ghi qua allocation/receipt/attempt và `payment_reference` trong biên nhận đăng ký, không thêm cột.
- [x] 8.5 `TournamentService`:
  - Bỏ mọi lệnh trừ ví trong `register` và `addParticipant`, bỏ phụ thuộc `WalletPaymentService`.
  - Ban tổ chức thêm người vào giải có phí thì bắt buộc có `waiver_reason`. Lý do được ghi vào participant (cột đã có sẵn) và audit log.
  - Ghi chú: bỏ luôn các guard `WalletAvailabilityService` và phụ thuộc `FinancialPostingService` khỏi `TournamentService`; `FinancialPostingService::postTournamentRegistrationPayment` giữ lại vì test khác còn dùng. Audit action `tournament.entry_fee_waived`.
- [x] 8.6 Frontend:
  - Trang thanh toán giải hiện QR và poll trạng thái.
  - Modal thêm người có ô nhập lý do miễn phí.
  - Ghi chú: "trang thanh toán" là `TournamentPaymentPanel` trong modal đăng ký (dùng lại `OwnerPaymentInstructionsPanel`), không phải route riêng. Trước đây chưa có UI thêm người, nên thêm `TournamentAddParticipantModal` (tìm khách qua `/customers?q=`) mở từ tab Tổng quan.
- [x] 8.7 Test:
  - Sức chứa tính cả attempt đang chờ.
  - Đăng ký idempotent.
  - Receipt gửi hai lần chỉ tạo một participant.
  - Receipt đến muộn thành exception.
  - Thêm người mà không có lý do thì bị từ chối.
  - Không còn bút toán ví.
  - Sửa `TournamentCompleteLifecycleTest` và `TournamentRegistrationAndLifecycleTest`.
  - Playwright `p1-tournaments-lifecycle` thêm case giải có phí, thanh toán qua webhook tổng hợp.
  - Ghi chú: case Playwright 9.4.6 chỉ sửa, chưa chạy. Sửa thêm `ComprehensiveConcurrencyAndRetryInvariantsTest` scenario 9 sang luồng QR chủ sân.

## 9. Chỉ công khai sân có giá (design 9)

- [x] 9.1 Tách query nạp rule giá trong `PriceQuoteService::priceSnapshots` thành một method dùng chung, và thêm predicate "sân có giá".
  - Một sân có giá khi có ít nhất một `PriceRule` active theo org, venue, zone hoặc court, hoặc một `CourtPriceSlot` active.
- [x] 9.2 Áp predicate:
  - `VerifyEffectiveBookingEligibility`: phủ trang chi tiết, lịch trống và booking mới.
  - `CourtController::index`: lọc bằng một query gộp, không N+1.
  - Ghi chú: `CourtPriceRuleService` (2 query cho mọi số sân) dùng chung cho `priceSnapshots`, eligibility (lý do `COURT_UNPRICED`, trả 404) và index. Lưu/xóa `PriceRule`/`CourtPriceSlot` tăng `courts_version` để cache danh sách công khai làm mới. Danh sách venue công khai và sân lồng trong venue chưa lọc theo giá (ngoài phạm vi 9.2).
- [x] 9.3 Frontend bỏ giá dự phòng ở `useCourtDetail.ts` (dòng 70, 72), `CourtItemCard.vue` (dòng 87), `VenueCourtsGrid.vue` (dòng 12, 14) và `HomeView.vue` (dòng 601).
  - Ghi chú: sân không có `price_slots` thì ẩn giá thay vì bịa giá. Giá 200000 trong `useResaleMarketplace.ts` để lại cho 7.8.
- [x] 9.4 Test:
  - Sân không có rule bị ẩn khỏi danh sách và không đặt được.
  - Sân chỉ có rule cấp tổ chức vẫn hiện.
  - Số query của index không tăng theo số sân.

## 10. Seed khớp luồng thật (design 3, 11)

- [x] 10.1 `UserSeeder::receivingConnection` đọc bốn biến `SEED_DEMO_RECEIVER_*`.
  - Ngoài `local` và `testing`, thiếu bất kỳ biến nào thì seed dừng, theo mẫu `hashedPassword`.
  - Mọi kết nối cơ sở và tài khoản người bán lại đều dùng tài khoản này.
  - Chỉ `aura-court` nhận key từ env. Các kết nối khác dùng key ngẫu nhiên.
  - Seeder in ra URL webhook của Cầu Giấy (routing id uuid5 `auracourt-demo/aura-court`) và không bao giờ in key.
  - Thêm bốn khóa, để trống giá trị, vào `.env.example`.
  - Đã làm. Biến được kiểm tra định dạng và đọc ở đầu `run()`, nên thiếu biến thì seed dừng trước khi tạo tài khoản nào. Ở local, để trống thì dùng giá trị giả (`TPBank`, `0000000000`, key ngẫu nhiên). Seed lại với key khác thì kết nối nhận key mới và tăng `credential_version`.
- [x] 10.2 Bỏ nạp ví (`topUp`) và dữ liệu trả bằng ví ở `UserSeeder`, `BookingPaymentSeeder`, `RecurringSeriesSeeder`, `PosSalesSeeder`, `TournamentSeeder` và `MarketplaceSeeder`.
  - Đã làm. `UserSeeder` không còn `topUp`; bán POS bằng ví bị bỏ.
- [x] 10.3 Bỏ payout, settlement, beneficiary, hoàn tiền, hủy sân và khiếu nại xử lý bằng hoàn tiền ở `UserSeeder`, `DisputeFinanceSeeder`, `BookingPaymentSeeder`, `RecurringSeriesSeeder` và `MatchmakingSeeder`.
  - Đã làm. `DisputeFinanceSeeder` bỏ toàn bộ phần tài chính (provider event, beneficiary, settlement, payout, đối soát, nghĩa vụ hoàn tiền); khiếu nại DSP-5MB8VJ3RDP đổi sang `resolve`, chủ sân hỗ trợ trực tiếp. Lịch booking `refunded`/`cancelled`/`refund_pending`/`pending` đổi sang đã chơi, hết hạn hoặc sắp tới (vẫn 72 booking). Hợp đồng định kỳ bị hủy giữ các buổi đã trả, như `cancelSeries`. `MatchmakingSeeder` không cần sửa: trận bị hủy không có hoàn tiền hay ví.
- [x] 10.4 Lịch sử đã thanh toán được ghi như code thật ghi: attempt `paid`, receipt `allocated`, bút toán `owner.direct_receipt`.
  - Không ghi bản ghi `payments` của nền tảng.
  - Đơn QR trong `PosSalesSeeder` (khoảng dòng 307–320) trỏ về kết nối của cơ sở, không trỏ về tài khoản nền tảng.
  - Đã làm qua trait `Database\Seeders\Concerns\PaysVenueOwner`: tạo attempt bằng `createAttempt`, gửi sự kiện SePay có header `Apikey` qua `verifyInboundWebhook` và `receiveVerifiedEvent`, rồi ghi allocation và gọi `postDirectOwnerReceipt`. Không gọi `processReceipt` vì các confirmer sẽ chạy lại side effect (xác nhận hold, hoàn tất đơn) trên dữ liệu seed đã ở trạng thái cuối. Áp dụng cho booking, kỳ định kỳ, đơn POS QR (mã `AC…`) và lệ phí giải.
- [x] 10.5 Chính sách seed (`OrganizationVenueSeeder::bookingPolicy` và `E2ESeedFixturesCommand`, khoảng dòng 500–536):
  - Bỏ `cancellation`, `change`, `ancillary_credit_policy` và `transfer.seller_reimbursement_required`.
  - Thời lượng tối thiểu và tối đa giữ 60 và 240 phút.
  - Đã làm; bỏ luôn `cancellation_grace_hours` trong settings của cơ sở. `E2ESeedFixturesCommand` giảm từ 882 xuống 869 dòng, allowlist kiến trúc giảm theo.
- [x] 10.6 `MarketplaceSeeder`: listing P2P có tài khoản người bán, cùng vài transfer đã hoàn tất với `fee=0`.
  - Đã làm. Listing đi qua `MarketplaceService::list` với tài khoản nhận đã cấu hình. Ba giao dịch đã bán (RES-P2P-101..103, người mua của 102 là `user@example.com`) ghi theo dạng `PeerToPeerResaleService::transfer`: mã `RS…`, `fee=0`, `settlement_mode=peer_to_peer`.
- [x] 10.7 `TournamentSeeder`:
  - mọi giải đều có `venue_id`;
  - thêm một giải sắp diễn ra ở Cầu Giấy, phí 2.000–10.000 VND, hạn đăng ký ít nhất 7 ngày sau lúc seed.
  - Đã làm. Giải mới "Giải Bóng Bàn Giao Lưu Aura Cầu Giấy": bắt đầu sau 12 ngày, hạn đăng ký sau 10 ngày, lệ phí 5.000 ₫ / VĐV; `user@example.com` chưa đăng ký để trình diễn.
- [x] 10.8 `PosProductSeeder`: thêm một sản phẩm ở Cầu Giấy giá không quá 10.000 VND.
  - Đã làm. "Trà đá (ly)" `POS-TRA-DA` 5.000 ₫ (5.400 ₫ gồm VAT). Danh mục POS tính theo tổ chức nên sản phẩm có ở mọi cơ sở của Aura, trong đó có Cầu Giấy.
- [x] 10.9 Test seed:
  - không ví nào có số dư;
  - không có payout, settlement hay hoàn tiền;
  - không có attempt `booking_change`;
  - mọi kết nối dùng đúng tài khoản đã cấu hình;
  - mọi sân công khai đều có giá;
  - mọi khu sân có `slot_duration` 60; mọi slot đặt, entitlement và slot giá bắt đầu đúng giờ và dài 60 phút; mọi chính sách tính theo giờ chẵn;
  - thiếu biến env ngoài `local`/`testing` thì seed dừng.
  - Đã làm trong `DatabaseSeederTest`, dùng giá trị env giả. Thêm kiểm tra: mọi attempt `paid`, số receipt `allocated`, allocation và bút toán `owner.direct_receipt` bằng nhau, chỉ `aura-court` giữ key env, không có khiếu nại `resolve_and_refund`. Ngày tạo của cơ sở và khu sân được ghim theo tổ chức, sửa lỗi thỉnh thoảng có mốc lúc nửa đêm.

## 11. Tài liệu

- [x] 11.1 `docs/demo-accounts.md`:
  - Sửa đoạn về cờ (dòng 37–39) theo `PILOT_ORGANIZATION_IDS=*`.
  - Bỏ "ví" trong dòng của `user@example.com` (dòng 54) và ghi chú "Chi trả" (dòng 75).
  - Thêm kịch bản demo:
    - Mọi thứ cần tự xác nhận (booking, POS, giải đấu) đều làm ở Cầu Giấy.
    - Bán lại slot do người bán xác nhận.
    - Khách không đến được thì bán lại slot. Không có hủy hay dời lịch.
  - Đã làm. Thêm mục "Tài khoản nhận tiền" (bốn biến `SEED_DEMO_RECEIVER_*`, không ghi giá trị) và "Kịch bản trình diễn". Bỏ câu về lượt giữ chỗ BK-066/067/069 và đối soát 01:00 vì seed không còn tạo chúng.
- [x] 11.2 Viết tài liệu vận hành SePay, gồm:
  - mẫu mã thanh toán;
  - webhook Api_Key cho từng kết nối;
  - xoay key bằng endpoint rotate;
  - tắt webhook nền tảng cũ;
  - cách tra tiền không khớp trong SePay hoặc app ngân hàng.
  - Đã làm: `docs/operations/sepay-owner-connections.md`, liên kết từ `docs/demo-accounts.md` (docs không có trang mục lục).
- [x] 11.3 `docs/testing/playwright-coverage.md`:
  - Bỏ các dòng FR-BKG-11 đến FR-BKG-14 và FR-PAY-09 đến FR-PAY-11 (dòng 99–102, 118–120). Các dòng này đang trỏ tới spec Playwright đã xóa.
  - Bỏ mục P0-08 (dòng 204).
  - Ghi đúng mã FR theo bản báo cáo đã sửa.
  - Đã làm. Commit trước đã đổi các dòng này sang `Retired`; nay bỏ hẳn, ma trận còn 119 FR, danh sách P0 còn 9 hành trình (giữ nguyên mã P0-09, P0-10). Các mã FR còn lại giữ nguyên vì chưa có bản báo cáo đã sửa (11.4). Sửa luôn endpoint webhook của FR-PAY-02.
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
- [x] 12.2 Thêm ghi chú vào `openspec/changes/retire-v1-booking/tasks.md`:
  - 3.3: `BookingCancelModal` và `BookingRescheduleModal` đã bị xóa;
  - 3.7: case "huỷ" và "dời lịch đắt hơn và rẻ hơn" không còn;
  - 6.2: route hoàn tiền và dời lịch đã bị change này xóa;
  - 6.4: `OwnerRefundService` và `RescheduleService` đã bị xóa;
  - 6.8: webhook nền tảng không còn ghi `payments` cho POS.
  - Đã làm. Ghi chú thêm vào `openspec/changes/archive/2026-10-13-retire-v1-booking/tasks.md` theo yêu cầu, dù change đó đã archive.
- [ ] 12.3 Khi archive:
  - Change archive sau rebase khối MODIFIED lên spec chính.
  - Sửa tay phần Purpose của `direct-owner-payments`, `reservations-and-pricing` và `wallet-payment`.

## 13. Cổng kiểm tra local

- [x] 13.1 Chạy toàn bộ PHPUnit trên DB `*_test` bằng một file phpunit config tạm.
  - Chạy `migrate:fresh`, rồi rollback từng bước qua hai migration mới.
  - Kết quả: 860 test, chỉ fail `DatabaseSafetyGuardTest::test_safety_guard_allows_booking_test` (lỗi đã biết, do tên DB tạm). Rollback `--step=2` rồi migrate lại, 2 vòng, đều pass.
- [x] 13.2 Chạy Pint, và PHPStan level 5 với `.env` tạm, kết quả phải 0 lỗi. Không sinh lại baseline.
  - Test kiến trúc backend phải pass. Allowlist chỉ được giảm.
  - Level 0 và level 5 đều 0 lỗi (7f09de10). Baseline chỉ bỏ entry, không thêm: từ 651 còn 504.
- [x] 13.3 Frontend: type-check, ESLint, oxlint, Prettier, `vitest --maxWorkers=1` (chạy riêng, không song song với việc khác) và `architecture.spec.ts`. Không tăng allowlist.
  - vitest: 88 file, 441 test đều pass.
- [x] 13.4 Playwright p0 và p1 pass, với webhook tổng hợp dùng header Api_Key.
  - Chạy local `E2E_TARGET=local`, 126 test. Lần đầu fail 3 test, đã sửa:
    - POS 5.2: lần gửi lại webhook phải giữ nguyên body (cùng `transactionDate`). Webhook thật được SePay gửi lại với body y hệt, nên server trả 409 khi body đổi là đúng.
    - Resale 7.9.3: cửa sổ check-in tính từ slot đầu của đơn đặt, không phải từ giờ được chuyển nhượng.
    - Ví 8.6.2 là bug thật: response đăng nhập không có `capabilities`, nên ví bị ẩn tới khi F5. Đã sửa: store gọi lại `/auth/me` sau khi đăng nhập.
  - Lần chạy lại: 125 pass. 4.3 (tranh chấp slot) timeout khi 4 lane chạy song song; chạy riêng 3/3 pass, và đã pass ở lần chạy đầu.

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
  - Ghi chú phối hợp (2026-10-21): change `align-module-02-venue-court` thay một phần delta "Final bookings and released availability" của change này, tức quyết định đơn đã xác nhận là cuối cùng.
    - Theo design D8 của change đó, owner và staff được chuyển sân, đổi giờ hoặc huỷ một đơn khi đơn còn case "đơn bị ảnh hưởng" mở. Cả ba lệnh cần khách đồng ý và không tạo dòng tiền.
    - Khi bỏ FR dời lịch khỏi báo cáo, giữ UC-2.16..UC-2.18 của Module 02, gồm UC-2.17 đổi giờ do owner và staff làm. Khách không tự dời hay huỷ đơn đã xác nhận.
    - `align-module-02-venue-court` phải archive sau change này. Khối MODIFIED trùng header của nó được dựng từ text delta ở đây.
    - Khi archive, rebase "Versioned reservation API compatibility" sang header "Unversioned reservation API" và giữ nguyên câu cấm huỷ, hoàn tiền, dời của nó. Khối MODIFIED "Unversioned reservation API" của `align-module-02-venue-court` sau đó thêm ngoại lệ cho lệnh của owner và staff với đơn bị ảnh hưởng.

## 15. Triển khai (design 13)

- [x] 15.1 Seed `booking_demo_btprod` bằng `.env.btprod` với code của nhánh, rồi chạy lại các kiểm tra ở 10.9 trên DB đó.
  - 2026-10-14 (HEAD bcf29bca): `migrate:fresh --seed` exit 0. Kiểm tra bằng SQL: 0 ví có số dư, 0 payout, 0 bút toán refund/settlement/payout, 0 attempt `booking_change`, mọi attempt `paid`, 0 khiếu nại `resolve_and_refund`, mọi khu sân `slot_duration` 60, mọi slot và entitlement đúng giờ chẵn và dài 60 phút. Cả 6 kết nối `verified`, cùng ngân hàng và số tài khoản đã cấu hình. Không dòng dữ liệu nào chứa URL localhost.
  - `routing_id` là UUID v5 nên cố định giữa các lần seed. Webhook của Cầu Giấy trên prod: `https://court-api.ziet.dev/api/v1/webhooks/sepay/connections/f670509d-95d2-5ea1-98a5-ffd928baddc2`.
- [x] 15.2 Backup prod bằng `pg_dump` vào `backend/storage/app/backups/`.
  - `booking-bt-server-20261014-1025-before-direct-owner-reseed.dump` (121 bảng; 86 migration, 44 user trước khi reseed).
- [x] 15.3 Sau khi người dùng chốt giờ, reseed prod bằng dump/restore. Bước này phải xong trước khi deploy.
  - 2026-10-14 10:30, người dùng bảo "merge vô nhánh chính rồi deploy đi". Dump `booking_demo_btprod` (bỏ schema `public`, extension `btree_gist` và comment của nó khỏi TOC), `lock_timeout` 30s, một transaction, khoảng 2 phút. Sau đó prod có 88 migration, cột mới có mặt, 0 ví có số dư, 6 kết nối `verified`.
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
