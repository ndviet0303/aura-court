## 1. Phối hợp

- [x] 1.1 Không còn change nào để ghi chú: `main` đã gỡ mọi spec và change ở commit `c52bb214`, gồm cả `direct-owner-cutover` và `align-module-03-pricing-promotions`. Spec delta marketplace đã đổi thành chỉ ADDED.
- [x] 1.2 Không còn session module 04 đang chạy: `feat/module04-phase5` đã merge vào `main`. Thứ tự khoá D12 được làm theo trực tiếp trong code.
- [x] 1.3 Nhắc người dùng sửa report mục 2.3.9 (FR-BKG-19..21, BR-TRF) bằng đoạn trong design D10.

## 2. Dữ liệu

- [x] 2.1 Migration tạo `venue_credit_accounts`, `venue_credit_entries` và `venue_credit_cash_out_codes` theo design D2 và D7:
  - check `balance >= 0` và `reserved >= 0`;
  - unique (`organization_id`, `user_id`);
  - unique (`source_type`, `source_id`, `type`);
  - unique `code_hash`.
- [x] 2.2 Model cho ba bảng. Bút toán chỉ insert: model chặn update và delete. Không viết factory: credit chỉ được ghi qua `VenueCreditService`, test tạo credit bằng `issue` để giữ bất biến sổ cái.
- [x] 2.3 Migration dữ liệu nhả mọi khoá P2P (design D9):
  - `checkout` → `listed`, hoặc → `withdrawn` nếu đã quá giờ chốt;
  - bỏ khoá trong `metadata`, ghi `last_release.by` = `cutover`;
  - thông báo cho người mua bị nhả.
- [x] 2.4 Migration xoá `seller_receiver_ciphertext` (thêm bởi `2026_10_14_000007_add_seller_receiver_to_marketplace_listings`). Bỏ `sellerReceiver()` và cast liên quan khỏi `MarketplaceListing`.

## 3. VenueCreditService và sổ cái

- [x] 3.1 Thêm `owner_credit_liability` (liability) vào `LedgerService::systemAccounts`.
- [x] 3.2 Viết `App\Services\Finance\VenueCreditService` với các hàm `issue`, `reserve`, `release`, `capture`, `spend`, `return`, `cashOut`:
  - `insertOrIgnore` tài khoản, rồi `lockForUpdate`;
  - thiếu số dư trả 422;
  - trùng nguồn thì trả bút toán cũ;
  - các delta theo bảng D2.
- [x] 3.3 Sửa `FinancialPostingService`:
  - `postDirectOwnerReceipt` ghi Có `owner_credit_liability` khi purpose là `resale`;
  - thêm journal `venue_credit.spend`, `venue_credit.return` và `venue_credit.cash_out`, tài khoản theo D3, `ledger_entity` owner, idempotent theo `venue-credit:{entry_id}`.
- [x] 3.4 Viết `tests/Feature/ResaleVenueCredit/VenueCreditServiceTest.php` (bất biến sổ cái nằm ở `LedgerReconciliationTest.php` cùng thư mục, vì `issue` chỉ có journal khi đi qua receipt):
  - hai lần `reserve` đồng thời trên cùng số dư thì một lần bị 422;
  - gọi lại cùng nguồn không ghi thêm;
  - không tiêu được credit của tổ chức khác;
  - không có đường nạp hay chuyển credit;
  - credit cũ một năm vẫn tiêu được;
  - bất biến Σ(`balance` + `reserved`) = số dư `owner_credit_liability` sau một chuỗi issue, reserve, release, capture, spend, return và cash_out.

## 4. Nhượng do chủ sân thu

- [x] 4.1 Đổi tên `App\Services\Social\PeerToPeerResaleService` thành `OwnerCollectedResaleService`. Sửa `ResaleListingController`, `AdminDisputeController` và `routes/console.php` (đổi cả comment 1d). Không để alias.
- [x] 4.2 Thêm `resale` vào `PURPOSES` và `CONFIRMERS` của `OwnerPaymentAttemptService`. Viết:
  - `hasConfirmableResale`: khoá listing → entitlement → reservation; trả `false` thay vì `abort` khi suất không còn chuyển được;
  - `confirmPaidAttempt`: gọi `transfer()` rồi `VenueCreditService::issue`.
- [x] 4.3 Viết lại `lock()` theo design D4:
  - route `checkout` thêm middleware `verified`;
  - khoá dòng người mua; mỗi người mua chỉ một hold còn hạn (409);
  - listing paid mà venue không có kết nối nhận đã xác minh thì bị từ chối;
  - thứ tự khoá user → venue `FOR KEY SHARE` → listing;
  - tạo attempt `resale` với số tiền đúng giá và `expires_at` = `locked_until`.
- [x] 4.4 Sửa vòng đời hold:
  - `release` chỉ cho người mua (người bán nhận 403), hết hạn attempt qua `DB::afterCommit`;
  - `withdraw` trả 409 khi đang có hold;
  - `sweep` bỏ nhánh "đã báo chuyển" và hết hạn attempt sau commit.
- [x] 4.5 Sửa `transfer()`:
  - `settlement_mode` `owner_collected`, `idempotency_key` `resale:listing:{id}`, `marketplace_fee` 0;
  - `buyer_payment_reference` lấy từ attempt;
  - thông báo cho người bán (số credit và tổ chức) và cho người mua.
- [x] 4.6 Xoá route, method controller và `ResaleListingActionRequest` (nếu không còn dùng) của `payment-sent` và `confirm-receipt`. Bỏ trường tài khoản nhận khỏi `StoreResaleListingRequest` và khỏi chỗ tạo listing.
- [x] 4.7 Sửa `ResaleListingState` theo design D4:
  - bỏ `receiver` và `payment_reported_at`;
  - `payment` lấy từ attempt, chỉ trả cho người giữ khoá;
  - thêm `credit_issued` cho người bán.
- [x] 4.8 (Các suite cũ dưới đây đã bị gỡ ngày 2026-10-22, không còn file để sửa; scenario được phủ mới trong `tests/Feature/ResaleVenueCredit/OwnerCollectedResaleTest.php`.) Sửa test dùng luồng cũ: `AdvancedBookingMarketplaceTest`, `Booking/MarketplaceTransferVerificationTest`, `GatedMarketplaceAndSplitRegressionTest`, `ZeroCommissionRetirementRegressionTest`, `Readiness/ComprehensiveConcurrencyAndRetryInvariantsTest`, `Account/RestrictedAccountLifecycleTest` và `Infrastructure/DatabaseSeederTest`. Mỗi scenario trong spec "Owner-collected marketplace transfer" phải có ít nhất một test, đặc biệt:
  - receipt gửi lại;
  - tiền đến sau hạn;
  - sai số tiền;
  - suất bị tranh chấp trước khi tiền về;
  - hai người mua tranh nhau;
  - người mua giữ chỗ thứ hai;
  - người mua chưa xác thực email;
  - người bán nhả hoặc rút khi đang có hold;
  - nhượng miễn phí không sinh credit;
  - listing cũ lưu phí 5%.

## 5. Credit khi đặt sân

- [x] 5.1 Thêm `venue_credit_amount` vào request và vào `OrdinaryCheckoutService`:
  - áp dụng sau điểm và `assertAgreedAmount`;
  - `reserve` trong transaction tạo hold;
  - nhân viên đặt hộ không được dùng;
  - `netPayable` bằng 0 thì xác nhận ngay và `capture`.
- [x] 5.2 Nhánh `booking` của `processReceipt` gọi `capture` sau khi reservation chuyển sang `confirmed`.
- [x] 5.3 `HeldReservationRelease::releaseHeld` gọi `release` cạnh `releaseReservedPoints`.
- [x] 5.4 Request của recurring, split, tournament và marketplace từ chối `venue_credit_amount` (422).
- [x] 5.5 Test cho các scenario của "Venue credit at booking checkout": trả một phần, trả hết, hold hết hạn hoặc bị huỷ, hai checkout đồng thời, loại đơn không hỗ trợ.

## 6. POS

- [x] 6.1 Thêm tender `credit` (dùng riêng hoặc trong `mixed`) vào `PosService`. Bắt buộc `customer_id`, kiểm tra cùng tổ chức. Gọi `spend` trong transaction hoàn tất đơn.
- [x] 6.2 Đơn có phần QR: lúc tạo đơn chỉ kiểm tra số dư. `PosQrPaymentService::hasConfirmableOrder` kiểm tra lại số dư, còn `confirmPaidAttempt` gọi `spend` (design D6).
- [x] 6.3 `refundOrder` với `original_method` trả phần credit về bằng `return`.
- [x] 6.4 `postPosSale` chỉ ghi phần không phải credit, và không ghi khi phần đó bằng 0.
- [x] 6.5 Mã đổi tiền mặt theo design D7:
  - tạo mã (huỷ mã cũ còn sống);
  - lookup có giới hạn tần suất;
  - confirm khoá mã → tài khoản → ca, ghi `cash_out`, gọi `recordCashMovement` và ghi journal.
- [x] 6.6 Test cho các scenario của "Venue credit as POS tender" và "Counter cash-out of venue credit":
  - expected cash của ca đổi đúng;
  - dùng mã hai lần đồng thời;
  - mã hết hạn;
  - mã của tổ chức khác không lộ thông tin;
  - số dư bị tiêu sau khi tạo mã.

## 7. Endpoint xem

- [x] 7.1 Thêm route `GET me/venue-credits` và `POST me/venue-credits/{organization}/cash-out-codes` (kèm `verified`).
- [x] 7.2 Thêm route `GET pos/venue-credits/customers/{user}`, `POST pos/shifts/{shift}/venue-credit-cash-outs/lookup` và `POST pos/shifts/{shift}/venue-credit-cash-outs` trong nhóm `feature:tenant_pos_and_wallet`, quyền `organization_owner,staff`.
- [x] 7.3 Thêm route `GET owner-venue-credits` với quyền `organization_owner` và `feature:direct_owner_payments`. Trả tổng credit chưa dùng và bút toán, có phân trang.
- [x] 7.4 Test cho các scenario của "Venue credit visibility": khách xem số dư ở hai tổ chức; người bán nhận thông báo; chủ sân chỉ thấy tổ chức mình; người của tổ chức khác nhận 403.

## 8. Frontend

- [x] 8.1 Sửa `features/marketplace`:
  - `marketplaceApi.ts` và `types/marketplace.ts` bỏ `payment-sent`, `confirm-receipt` và receiver;
  - `useResaleLock` và `ResalePaymentDetails` hiện QR của chủ sân, đếm ngược và polling trạng thái;
  - xoá nút "đã chuyển" và `ResaleReceiverFields.vue`, sửa `MarketplaceSellModal`;
  - `MarketplaceMyListings` hiện "Đã bán · +X credit".
- [x] 8.2 Thêm tab credit trong `features/profile` (số dư theo tổ chức và lịch sử) và màn tạo mã đổi tiền (mã, QR, đếm ngược 10 phút).
- [x] 8.3 Thêm lựa chọn "Dùng credit" khi checkout, cạnh `LoyaltyPointSelector` / `QuoteCalculatorCard`. Chỉ hiện khi khách có credit ở tổ chức đó.
- [x] 8.4 POS:
  - tender credit trong `PosCartPanel`, có số dư của khách đã chọn qua `PosCustomerModal`;
  - modal đổi tiền mặt (nhập hoặc quét mã, xem tên và số tiền, xác nhận).
- [x] 8.5 Thêm màn credit trong `features/finance` theo mẫu `OwnerPaymentExceptionsView`.
- [x] 8.6 Sửa chuỗi trong `i18n/locales/vi.ts` và `en.ts`, bỏ chuỗi của luồng P2P.
- [x] 8.7 Sửa và thêm spec vitest cho mua suất, đăng bán, tab credit, checkout có credit, tender credit POS và màn credit của chủ sân.

## 9. Seed và demo

- [x] 9.1 (Repo không còn `E2ESeedFixturesCommand`; chỉ sửa `MarketplaceSeeder`.) Sửa `MarketplaceSeeder`, `E2ESeedFixturesCommand` và seed demo cho đủ ba ca:
  - một lượt bán do chủ sân thu, có credit;
  - một đơn trả một phần bằng credit;
  - một lượt đổi tiền mặt;
  - không còn listing có tài khoản người bán.

## 10. Kiểm tra

- [x] 10.1 `openspec validate resale-venue-credit --strict` đạt.
- [x] 10.2 (Chạy tuần tự từng thư mục vì vendor dùng chung chưa cài paratest: Module01–07, PosVenueInventory và ResaleVenueCredit đều pass trên `booking_rvc_test`.) Chạy test backend bằng cấu hình phpunit tạm, trỏ vào DB `*_test` trên bt-server, không bao giờ vào `booking`. Chạy paratest 2–4 tiến trình.
- [x] 10.3 Chạy PHPStan bằng `.env` tạm và các architecture guard của CI.
- [x] 10.4 Chạy vitest riêng với `--maxWorkers=1`, rồi `vue-tsc` / build frontend.
- [x] 10.5 `grep` không còn `PeerToPeerResale`, `payment-sent`, `confirm-receipt`, `seller_receiver` hay `ResaleReceiverFields` ngoài migration cũ và lịch sử.
