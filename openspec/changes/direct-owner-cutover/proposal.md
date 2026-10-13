## Why

Báo cáo `dacn_report.docx` (bản đã bỏ FR hủy sân và hoàn tiền; FR dời lịch cũng sẽ bỏ theo quyết định ngày 2026-10-13) mô tả một mô hình duy nhất: khách quét QR trả thẳng cho chủ sân, Aura Court không giữ tiền và chỉ thu phí gói dịch vụ. Prod hiện chạy khác báo cáo ở nhiều chỗ:

- **Cờ tắt.** `FEATURE_DIRECT_OWNER_PAYMENTS=false` trong build-arg (`.gitlab-ci.yml:148`), nên booking vẫn trừ ví Aura. Stack Portainer còn khai `FEATURE_*: ${…:-false}`, có thể ghi đè giá trị của image.
- **Seed.** Seed nạp ví, tạo payout, hoàn tiền và hủy sân. Mọi cơ sở trỏ về tài khoản MBBank giả, nên QR có thể dẫn tới tài khoản của một người lạ.
- **Giao diện.** Vẫn còn Ví Aura, menu Rút tiền, thẻ "Phí & Hoa hồng" và nút trả bằng ví ở POS.
- **Ba luồng tiền đi chệch thanh toán trực tiếp:**
  - QR ở POS dùng tài khoản nền tảng (`config('services.sepay.*')`).
  - Màn mua lại slot gửi mã tham chiếu cố định nên luôn lỗi 422. Backend thu tiền qua chủ sân rồi bắt chủ sân hoàn cho người bán, trong khi báo cáo mô tả người mua trả thẳng cho người bán.
  - Phí giải đấu trừ ví.
- **Hủy sân, hoàn tiền và dời lịch vẫn chạy:**
  - refund-requests và reschedule (v1/v2);
  - phán quyết `resolve_and_refund`;
  - nhánh hoàn tiền của `RecurringReleaseService`;
  - luồng thu tiền chênh riêng khi dời lịch (`booking_change`).

  Báo cáo chỉ giữ bán lại slot.
- **Độ dài slot không thống nhất.**
  - Báo giá V2 nhận giờ bắt đầu lẻ 30 phút, còn checkout V1 và danh sách chờ đòi đúng giờ.
  - Mỗi khu sân tự đặt độ dài slot (30–120 phút).
  - Nhân viên kéo dài lượt chơi được mà không thu tiền.

  Dữ liệu prod hiện đều là slot 1 giờ.
- **Webhook chưa nhận được sự kiện thật.** Webhook theo kết nối chỉ nhận `X-SePay-Signature` (HMAC kèm `X-SePay-Timestamp`). SePay chỉ hỗ trợ `No_Authen`, `OAuth2.0` hoặc `Api_Key` (`Authorization: Apikey …`), nên khi bật cờ, mọi giao dịch thật sẽ bị trả 401 và booking không bao giờ tự xác nhận.
- **Sân chưa có giá vẫn hiện công khai.** Ví dụ sân rác "ABC" và "ABCD" trên prod. Frontend còn tự điền giá dự phòng cho những sân này.

Buổi bảo vệ còn khoảng 3 tuần (tính từ 2026-10-13). Mục tiêu demo: chuyển khoản thật vài nghìn đồng vào TPBank đang nối SePay, booking tự xác nhận ngay trước giám khảo, và mọi màn hình khớp báo cáo.

## What Changes

- **Bật thanh toán trực tiếp trên prod.**
  - CI build-arg `FEATURE_DIRECT_OWNER_PAYMENTS=true` và `PILOT_ORGANIZATION_IDS=*`. Nhờ vậy mọi tổ chức đều thanh toán trực tiếp, kể cả đối tác mới được duyệt trong buổi demo.
  - Bỏ các dòng `FEATURE_*` và `PILOT_ORGANIZATION_IDS` trong stack Portainer, để image là nguồn giá trị duy nhất.
- **Seed khớp báo cáo.**
  - Booking và hợp đồng định kỳ dùng `direct_owner_v1`.
  - Bỏ nạp ví, payout/settlement, hoàn tiền và hủy sân.
  - Tài khoản nhận tiền thật đọc từ env. Ngoài `local`/`testing`, seed dừng nếu thiếu env, và mọi tài khoản nhận tiền được seed (cơ sở, người bán lại) đều là tài khoản thật đó.
- **Webhook SePay thật.** **BREAKING (provider contract):** webhook theo kết nối xác thực bằng `Authorization: Apikey <credential của kết nối>` thay cho HMAC kèm timestamp tự đặt. Chống trùng vẫn dựa trên id giao dịch SePay của từng kết nối.
- **Ẩn những thứ báo cáo không có:**
  - Mọi điểm vào Ví Aura: menu, route, thẻ trong hồ sơ, nút trả bằng ví khi đặt sân và ở POS.
  - Nhóm menu Tài chính: Rút tiền, Đối soát, Ngoại lệ. Cả ba trang đều đang 404 vì nằm sau cờ payouts.
  - Thẻ "Phí & Hoa hồng".

  Ngoài ra, POS từ chối thanh toán bằng ví ở tổ chức đã bật thanh toán trực tiếp.
- **QR ở POS về tài khoản chủ sân.**
  - Đơn QR tạo `OwnerPaymentAttempt` với mục đích `pos_order`, trên kết nối nhận tiền của cơ sở.
  - Biên lai đã xác minh tự hoàn tất đơn.
  - Mã tham chiếu do server sinh.
  - Doanh thu QR không bị ghi sổ hai lần.
  - Đơn chờ thanh toán hết hạn theo lịch.
  - Tab dịch vụ ở tổ chức đã bật thanh toán trực tiếp chỉ thu tiền mặt. Cách thu QR trên tab hiện nay hoàn tất theo lời thu ngân, không có bằng chứng.
- **Bán lại slot theo báo cáo (người mua trả thẳng người bán).**
  - Người bán khai tài khoản nhận tiền khi đăng bán.
  - Mỗi lúc chỉ một người mua được giữ chỗ (khóa listing có hạn). Người đó thấy VietQR của người bán và chuyển khoản trực tiếp.
  - Người bán xác nhận đã nhận tiền thì quyền dùng sân chuyển sang người mua trong một giao dịch nguyên tử.
  - Bỏ đường thu tiền qua chủ sân và bước bằng chứng hoàn tiền cho người bán.
  - **BREAKING:** bỏ `POST marketplace/listings/{listing}/purchase`.
- **Phí giải đấu qua QR chủ sân.**
  - Đăng ký có phí tạo attempt `tournament_registration`, giữ chỗ có hạn, và chỉ xác nhận người tham gia khi biên lai khớp.
  - Giải có phí phải gắn một cơ sở của tổ chức có kết nối nhận tiền đã xác minh (cột mới `tournaments.venue_id`). Form tạo giải chọn cơ sở thay cho ô nhập tự do.
  - Ban tổ chức tự thêm người vào giải có phí thì phải ghi miễn phí kèm lý do. Không còn trừ ví.
- **Bỏ hủy sân, hoàn tiền và dời lịch.** Khách không đến được thì chỉ còn cách bán lại slot (pass sân).
  - **BREAKING:** bỏ route `refund-quote`, `refund-requests`, `reschedule-preview` và `reschedule-requests` (v1 và v2). Bỏ cùng lúc:
    - hộp thư hoàn tiền của chủ sân;
    - lựa chọn phán quyết hoàn tiền trong khiếu nại;
    - các trường chính sách hủy, hoàn tiền và dời lịch;
    - mục 5 của Điều khoản.
  - Bỏ luồng thu tiền chênh khi dời lịch (attempt `booking_change`).
  - Kết thúc hợp đồng định kỳ chỉ nhả các buổi chưa thanh toán.
  - Tiền đến muộn hoặc không khớp thành ngoại lệ tài chính, chủ sân xử lý ngoài hệ thống. Trường hợp này gồm cả phần chia tiền đã trả của một nhóm hết hạn.
  - Không xóa bảng hay dữ liệu hoàn tiền và dời lịch lịch sử.
- **Slot 1 giờ ở dữ liệu và chữ hiển thị, không thêm kiểm tra trong code** (người dùng chốt ngày 2026-10-13).
  - Dữ liệu seed và prod đều là slot 1 giờ bắt đầu đúng giờ. `CreateVenue` cũng tạo khu sân 60 phút. Một booking gồm một hoặc nhiều slot.
  - Code không thêm kiểm tra độ dài hay giờ bắt đầu của slot. Các đường đặt sân giữ quy tắc hiện có.
  - **BREAKING:** bỏ route `attendance/extend-session`. Khách muốn chơi thêm thì nhân viên đặt slot kế tiếp thành một booking tại quầy, có thanh toán riêng.
  - Chữ tiếng Việt không dùng "slot". Khi đếm thì dùng "giờ", khi nói về một đơn vị thì dùng "1h".
  - Không cần migration dữ liệu.
- **Chỉ công khai sân có giá.**
  - API danh mục công khai chỉ trả sân active có giá xác định.
  - Frontend bỏ giá dự phòng.
  - Seed lại prod sẽ xóa luôn các sân rác.

## Capabilities

### New Capabilities

Không có.

### Modified Capabilities

- `direct-owner-payments`: bỏ nghĩa vụ hoàn tiền do chủ sân thực hiện. Tiền đến muộn hoặc không khớp thành ngoại lệ tài chính.
- `wallet-payment`: booking trực tiếp không hủy hay dời lịch được, nên không tạo bút toán ví hay nghĩa vụ hoàn tiền.
- `advanced-booking-marketplace`:
  - Chia tiền không còn phân bổ hoàn tiền.
  - Bán lại slot thành giao dịch trực tiếp: người mua trả người bán, người bán xác nhận.
  - Mỗi listing là đúng một slot đã đặt.
- `reservations-and-pricing`:
  - Bỏ hủy booking, hoàn tiền và dời lịch. Booking đã xác nhận là cuối cùng, chỉ có thể bán lại.
  - Dữ liệu seed dùng slot 1 giờ. Chữ tiếng Việt gọi đơn vị đặt sân là giờ.
- `api-alignment`: màn booking và chính sách không còn hủy, hoàn tiền hay dời lịch. Chỉ giới thiệu bán lại slot.
- `support-case-management`: phán quyết khiếu nại không tạo nhiệm vụ hoàn tiền.
- `venue-court-operations`:
  - Chính sách cơ sở bỏ phần hủy, hoàn tiền và dời lịch.
  - Thay đổi lịch hoạt động xung đột với booking thì bị từ chối. Booking vẫn giữ tới khi chơi xong hoặc được chuyển sân do sự cố.
  - Chỉ sân active có giá mới được công khai.
- `financial-ledger-and-payouts`: giao diện payout và phí chỉ hiện khi tổ chức bật capability payout cũ.
- `organization-access`: bỏ nghĩa vụ hoàn tiền khỏi danh sách thao tác của chủ sân.
- `platform-readiness`:
  - Cổng PSP và bằng chứng nghiệm thu không còn yêu cầu theo dõi hoàn tiền.
  - Hành trình trình duyệt kiểm tra rằng không còn thao tác hủy, hoàn tiền hay dời lịch.
- `on-site-attendance`: bỏ gia hạn bằng cách kéo dài slot. Chơi thêm là một booking tại quầy cho slot kế tiếp. Đổi sân do sự cố giữ nguyên.
- `point-of-sale-and-loyalty`:
  - Ví đóng chỉ còn cho cohort cũ.
  - QR ở POS phải dẫn về tài khoản chủ sân và tự hoàn tất đơn từ biên lai.

## Impact

- **Backend:**
  - Xác thực webhook: `SePayReceivingConnectionService`, `ReceivingConnectionWebhookController`.
  - `OwnerPaymentAttemptService::processReceipt`: thêm nhánh `pos_order` và `tournament_registration`.
  - Các service luồng tiền: `PosService`, `ProcessQrPayment`, `MarketplaceService`, `TournamentService`.
  - Bỏ hủy, hoàn tiền và dời lịch: `RescheduleService`/`RescheduleController`, `OwnerRefundService`/`OwnerRefundController`, `RecurringReleaseService`, `SplitPaymentService`, `ArbitrateDispute`, `ExpirePendingBookings`, `StoreVenueBookingPolicyRequest`.
  - Bỏ gia hạn tại sân: `AttendanceController::extendSession`, `AttendanceService::extendSession`.
  - Chữ "slot" trong thông báo: `SlotSelectionPolicy`, `PriceQuoteService`, `OrdinaryCheckoutService`, `WaitlistService`, `RecurringSeriesService`, `CreateRecurringBooking`, `LoyaltyPolicyService`.
  - Danh mục sân công khai: `CourtController`.
  - `routes/api.php` và seeder.
  - Ví và tab dịch vụ: `WalletDepositController`, `ServiceTabService`, capability `wallet_enabled` trong `/me`.
  - Eligibility sân có giá: `PriceQuoteService`, `VerifyEffectiveBookingEligibility`.
  - Migration bổ sung, không phá hủy: cột mã hóa chứa tài khoản người bán trên `marketplace_listings`, và `tournaments.venue_id`.
- **Frontend:**
  - Điều hướng ví, admin và POS.
  - Composable và modal của bán lại slot.
  - Trang thanh toán giải đấu.
  - Các modal hủy, hoàn tiền và dời lịch, cùng mọi điểm vào dời lịch.
  - Chọn thời lượng ở trang sân, form chính sách và bộ mô phỏng giá.
  - Form chính sách cơ sở và Điều khoản.
  - Bản dịch vi/en và test.
- **Hạ tầng:**
  - `.gitlab-ci.yml` và `devops/portainer/booking_app.stack.yml`.
  - Stack Environment trên Portainer.
  - Cấu hình SePay. Phần này người dùng tự làm: webhook kiểu Api_Key, mẫu mã thanh toán `AC` + 18 ký tự, tắt webhook nền tảng cũ.
- **Dữ liệu:**
  - Seed lại `booking` trên prod theo quy trình đã dùng ngày 2026-10-12, có backup trước.
  - Không có migration phá hủy. Bảng ví, payout và hoàn tiền giữ nguyên làm lịch sử.
- **Phụ thuộc:**
  - `retire-v1-booking` cũng sửa requirement "Versioned reservation API compatibility". Delta của change đó phải được cập nhật cho khớp. Change này bỏ route refund và reschedule v1 trước Phase C của nó.
  - `harden-db-integrity` có test bắt buộc mọi cột status phải có CHECK. Change này tái dùng trạng thái sẵn có, nên không cần thêm CHECK.
- **Non-goals:**
  - Ép slot 1 giờ trong code: một kiểm tra chung cho mọi đường đặt sân, bỏ cài đặt `slot_duration`, chính sách bội số 60 phút, slot giá `HH:00`, bỏ lựa chọn 1,5 giờ ở trang sân. Người dùng chốt không làm phần này (2026-10-13).
  - Trả/đổi hàng ở POS (`refundOrder`).
  - Gỡ API ví ở backend: ví cũ vẫn đọc được, nạp ví đã bị chặn theo cohort.
  - Tự động chia một tài khoản ngân hàng cho nhiều cơ sở.
  - Xử lý tranh chấp tiền giữa người mua và người bán ngoài kênh khiếu nại.
  - Các lỗi dễ lộ khi demo (email/SĐT, tỷ số trọng tài): làm ở change riêng.
