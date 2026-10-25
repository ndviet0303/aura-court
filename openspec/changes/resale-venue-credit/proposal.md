## Why

Nhượng sân đang trả tiền theo kiểu P2P (`direct-owner-cutover` decision 5): người mua chuyển khoản thẳng cho người bán, hệ thống chỉ tin vào nút người bán bấm. Vì hệ thống không thấy tiền, có ba cách lừa:

- Người bán nhận tiền rồi bấm "chưa nhận", hoặc im tới giờ chốt. Người bán giữ cả tiền lẫn suất, rồi bán lại suất đó.
- Người bán mở khoá đúng lúc tiền đang chuyển tới.
- Một người mua bấm "đã chuyển" trên mọi suất để chiếm chợ tới giờ chốt.

Khiếu nại không chữa được ba lỗ này: phán quyết chỉ được ghi lại, còn mã `RS…` bị xoá khi mở khoá.

Tài khoản duy nhất hệ thống xác minh được là tài khoản nhận của chủ sân, đã nối SePay. Change này cho người mua trả vào tài khoản đó, và trả người bán bằng **credit sân** thay vì chuyển khoản. Nhờ vậy:

- Chủ sân không phải chuyển khoản cho ai.
- Nền tảng không giữ tiền.
- Không còn bước nào dựa vào lời tự khai.

Người dùng chốt luồng này ngày 2026-10-24. Họ chấp nhận đi ngược decision 5 và từ chối phương án chủ sân tự chuyển khoản trả người bán.

## What Changes

- Luồng đăng bán giữ nguyên: chủ suất đặt giá, không cao hơn giá đã trả, trước giờ chốt.
- **BREAKING** Người mua trả bằng QR vào tài khoản nhận đã xác minh của chủ sân, đúng giá bán, kèm mã do server sinh. Khi SePay xác nhận tiền đã về đúng, suất chuyển ngay cho người mua và người mua có mã check-in mới.
- Người bán nhận **credit sân bằng đúng giá bán** ngay khi suất chuyển. Credit thuộc về tổ chức (chủ sân) của suất, dùng được ở mọi sân của tổ chức đó và không dùng được ở tổ chức khác. Credit tính bằng VND (1 credit = 1đ) và không hết hạn. Credit dùng để:
  - trừ vào tiền đặt sân thường, một phần hoặc toàn bộ;
  - trả tiền ở quầy POS, riêng hoặc kết hợp tiền mặt/QR;
  - đổi tiền mặt tại quầy: khách tạo mã dùng một lần trong app, thu ngân đưa tiền, hệ thống trừ credit và ghi phiếu chi vào ca.
- **BREAKING** Bỏ các endpoint `marketplace/listings/{listing}/payment-sent` và `confirm-receipt`. Người bán không còn mở được khoá giữ chỗ của người mua. Người mua vẫn huỷ được giữ chỗ trước khi trả.
- **BREAKING** Bỏ ô tài khoản nhận của người bán khi đăng bán. Xoá các cột lưu tài khoản người bán.
- Mỗi người mua chỉ giữ được một suất tại một thời điểm, và phải xác thực email mới giữ được.
- Tiền đến sau khi giữ chỗ hết hạn, sai số tiền, hoặc đến lúc suất không còn chuyển được thì không chuyển suất và không cộng credit. Khoản đó thành ngoại lệ thanh toán của chủ sân, giống đơn đặt sân.
- Nhượng miễn phí giữ nguyên và không sinh credit.
- Không thu phí nhượng. Credit không mua được, không nạp được và không chuyển cho người khác.

## Capabilities

### New Capabilities

- `venue-credit`: số dư credit theo (tổ chức, khách) mà chủ sân phát hành khi suất nhượng bán được, gồm:
  - trừ credit khi đặt sân;
  - credit làm phương thức thanh toán ở POS;
  - đổi tiền mặt tại quầy;
  - khách và chủ sân xem số dư, lịch sử;
  - đối soát với sổ cái của chủ sân.
- `advanced-booking-marketplace`: requirement "Owner-collected marketplace transfer". `main` đã gỡ spec cũ (có requirement P2P) ở commit `c52bb214`, nên delta chỉ ADDED.

### Modified Capabilities

- Không có.

## Impact

- **Backend.**
  - Sửa: `PeerToPeerResaleService` (đổi vai trò thành dịch vụ nhượng do chủ sân thu), `MarketplaceService`, `ResaleListingState` và controller nhượng sân.
  - `OwnerPaymentAttemptService`: thêm purpose `resale` và confirmer của nó.
  - `FinancialPostingService` và `LedgerService`: thêm tài khoản `owner_credit_liability` cùng các bút toán phát hành, tiêu và đổi tiền mặt.
  - `OrdinaryCheckoutService` và `HeldReservationRelease`: giữ, tiêu và trả lại credit.
  - `PosService`: thêm tender `credit` và nghiệp vụ đổi tiền mặt.
  - Migration bảng credit; xoá cột tài khoản người bán.
  - Route trong `routes/api.php`; lệnh sweep trong `routes/console.php`.
- **Frontend.**
  - `features/marketplace`: modal mua, `useResaleLock`, `ResalePaymentDetails`, `MarketplaceSellModal`, `ResaleReceiverFields`, danh sách nhượng của tôi.
  - Thêm thẻ credit ở hồ sơ, mã đổi tiền và lựa chọn "Dùng credit" khi đặt sân.
  - POS: tender credit và màn đổi tiền mặt.
  - Màn tài chính của chủ sân: danh sách credit.
  - Chuỗi i18n vi/en.
- **Dữ liệu và demo.**
  - Lúc deploy, nhả các khoá giữ chỗ P2P đang mở.
  - Lượt nhượng đã hoàn tất giữ nguyên.
  - Sửa `MarketplaceSeeder`, `E2ESeedFixturesCommand` và seed demo để có lượt nhượng do chủ sân thu, credit và lượt đổi tiền mặt.
- **Phối hợp.**
  - Confirmer `resale` theo thứ tự khoá mà `align-module-04-booking` đã chốt cho `processReceipt` (D12, đã merge).
- **Report.** Mục chuyển nhượng 2.3.9 (FR-BKG-19..21, BR-TRF) đang mô tả tiền đi P2P. Người dùng tự sửa văn bản report; `design.md` có đoạn thay thế.
