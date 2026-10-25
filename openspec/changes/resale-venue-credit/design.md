## Context

Động cơ xem `proposal.md` (Why). Yêu cầu xem hai spec delta. Phần này chỉ ghi hiện trạng code liên quan đến cách làm.

- **Nhượng sân hiện tại.**
  - `App\Services\Social\PeerToPeerResaleService` giữ khoá `checkout` 15 phút. Khoá nằm trong `marketplace_listings.metadata` (`locked_by`, `locked_until`, `reference`, `payment_reported_at`).
  - `ResaleListingState` cho người giữ khoá thấy tài khoản người bán (`seller_receiver_ciphertext`). Suất chỉ chuyển khi người bán gọi `confirm-receipt`.
  - `transfer()` tạo `EntitlementTransfer` với `idempotency_key` `p2p:listing:{id}` và `metadata.settlement_mode` `peer_to_peer`.
  - Lịch `marketplace:sweep-resale-locks` (`routes/console.php`) chạy mỗi phút: nhả khoá hết hạn và chuyển listing quá giờ chốt sang `withdrawn`.
- **Đường tiền của chủ sân đã có sẵn.**
  - `OwnerPaymentAttemptService::createAttempt` tạo attempt gắn với kết nối nhận đã xác minh.
  - Webhook SePay dẫn vào `processReceipt`. Hàm này khớp `reference`, rồi `applyAttemptChecks` bắt các lỗi `transfer_after_expiry`, `partial_amount` và `excess_amount`.
  - Nếu khớp, hàm gọi confirmer theo `CONFIRMERS[purpose]`, sau đó `FinancialPostingService::postDirectOwnerReceipt` ghi Nợ `owner_bank_cash` / Có `owner_direct_revenue` (`ledger_entity` owner, idempotent theo `owner-receipt:{id}`).
  - Nếu sai, `OwnerPaymentExceptionRecorder::record` ghi ngoại lệ.
- **Các số dư sẵn có đều không dùng được cho credit.**
  - Ví cũ (`WalletAccount`, "Ví Aura") bị `WalletAvailabilityService` chặn với nhóm tổ chức direct.
  - Điểm (`PointLedger`) tính theo tổ chức, có hạn dùng và quy đổi theo tỉ lệ chủ sân đặt. Điểm được giữ khi hold bằng một bút âm `source_type` `reservation` và được trả trong `HeldReservationRelease::releaseHeld`.
- **POS.**
  - `CashShift` có `cash_movements` loại `cash_in` và `cash_out`. Tiền mặt dự kiến của ca trừ `cash_out`, ghi qua `PosService::recordCashMovement`.
  - Tender hiện có: `cash`, `qr`, `wallet`, `mixed`.
  - Hoàn đơn: `refundOrder(..., 'cash' | 'original_method' | 'wallet')`.
  - `postPosSale` ghi toàn bộ tổng đơn vào sổ tenant (`cash_clearing` / `tenant_payable`).
- **OpenSpec trên `main`.** Commit `c52bb214` đã gỡ mọi spec và change sau đợt conformance. Spec cũ có requirement "Peer-to-peer marketplace transfer" không còn, nên delta marketplace chỉ ADDED. Lý do bỏ P2P nằm ở proposal (Why).
- **Thứ tự khoá.** `processReceipt` khoá receipt → attempt → đối tượng của purpose. Quyết định D12 của `align-module-04-booking` chốt: venue `FOR KEY SHARE` trước attempt; attempt → reservation → program; hết hạn attempt chạy trong `DB::afterCommit`.

## Goals / Non-Goals

**Goals:**

- Tiền mua suất đi đúng đường attempt → receipt → exception của chủ sân. Không mở đường tiền mới, không có endpoint tự khai.
- Credit có sổ riêng, bút toán bất biến, khoá theo dòng tài khoản. Lúc nào cũng đối soát được với sổ cái owner.
- Mỗi bước tiền idempotent theo receipt hoặc theo nguồn (đơn, mã đổi tiền), để webhook gửi lại hay người dùng bấm lại không sinh tiền đôi.

**Non-Goals:**

- Không tự chuyển khoản cho người bán: không payout, không SePay refund API, không nghĩa vụ hoàn tiền của chủ sân.
- Không đổi credit ra tiền qua ngân hàng. Chỉ đổi tiền mặt tại quầy.
- Không chuyển số dư ví cũ hay điểm sang credit, và ngược lại.
- Không đổi nhượng miễn phí, tranh chấp, hay lượt nhượng đã hoàn tất.
- Không sửa việc `postPosSale` của tổ chức direct vẫn ghi vào sổ tenant. Đó là việc của change khác.

## Decisions

### D1. Người mua trả qua purpose `resale` của `OwnerPaymentAttemptService`

- Thêm `resale` vào `PURPOSES` và vào `CONFIRMERS`: `[OwnerCollectedResaleService::class, 'hasConfirmableResale', 'resale_hold_unavailable']`.
- Attempt được tạo ngay trong transaction giữ chỗ, với:
  - `purpose_id` = id listing;
  - `amount` = `asking_price`;
  - `expires_at` = `locked_until`;
  - kết nối nhận đã xác minh của venue có suất;
  - `reference` do server sinh. QR dựng giống `ReservationPaymentService::instructions`.
- `confirmPaidAttempt` chạy `transferable()` rồi `transfer()`, sau đó `VenueCreditService::issue` cho người bán. Ngay sau đó `processReceipt` ghi journal receipt (xem D3).
- `transferable()` hiện đang `abort`. Trong confirmer, kiểm tra đó chuyển thành nhánh `hasConfirmableResale` trả `false`, để receipt thành ngoại lệ `resale_hold_unavailable` thay vì làm hỏng webhook.
- Đổi tên `PeerToPeerResaleService` thành `OwnerCollectedResaleService` (cùng namespace) vì tên cũ không còn đúng. Cập nhật mọi chỗ gọi: controller, `routes/console.php`, `AdminDisputeController`. Không để alias.

Phương án đã cân nhắc:

- Giữ P2P: loại vì ba cách lừa trong proposal.
- Nền tảng giữ tiền (escrow): loại vì cần giấy phép trung gian thanh toán.
- Chủ sân chuyển khoản trả người bán qua `OwnerRefundObligation`: người dùng từ chối vì chủ sân mất công.
- SePay refund API: loại vì chỉ có với tài khoản doanh nghiệp VietinBank, và tiền chỉ hoàn về người đã trả chứ không về người bán.

### D2. Credit lưu ở bảng riêng, có hai cột số dư

- `venue_credit_accounts`:
  - cột: `organization_id`, `user_id`, `balance`, `reserved` (cả hai ≥ 0), timestamps;
  - unique (`organization_id`, `user_id`).
- `venue_credit_entries`, bất biến, chỉ insert:
  - cột: `organization_id`, `venue_credit_account_id`, `user_id`, `type`, `balance_delta`, `reserved_delta`, `balance_after`, `reserved_after`, `source_type`, `source_id`, `reference`, `created_by` (nullable), `metadata`, `created_at`;
  - unique (`source_type`, `source_id`, `type`) để chặn ghi đôi.
- Các loại bút toán:

  | type | balance_delta | reserved_delta | Nguồn |
  |---|---|---|---|
  | `issue` | +x | 0 | `entitlement_transfer` |
  | `reserve` | −x | +x | `reservation` |
  | `release` | +x | −x | `reservation` |
  | `capture` | 0 | −x | `reservation` |
  | `spend` | −x | 0 | `pos_order` |
  | `return` | +x | 0 | bản ghi hoàn của POS |
  | `cash_out` | −x | 0 | `venue_credit_cash_out_code` |

- Nợ thật của chủ sân với khách là `balance + reserved`. Vì vậy mỗi bút toán làm khoản nợ đổi đúng `balance_delta + reserved_delta`. `reserve` và `release` có tổng bằng 0 nên không ghi journal.
- `VenueCreditService` là cửa duy nhất được ghi vào hai bảng này. Mỗi thao tác:
  1. `insertOrIgnore` dòng tài khoản;
  2. `lockForUpdate` dòng đó;
  3. kiểm tra số dư đủ (422 nếu thiếu);
  4. ghi bút toán và cập nhật hai cột trong cùng transaction.

  Cách này giống mẫu khoá của `WalletPaymentService`. Vi phạm unique của nguồn nghĩa là đã ghi rồi, nên trả về bút toán cũ.

Phương án đã cân nhắc:

- Dùng lại `WalletAccount`: loại. Nó mang nghĩa nền tảng giữ tiền, sẽ biến số dư ví cũ thành tiền mặt rút được, và mở lại các cổng Ví Aura đang bị chặn.
- Dùng lại `PointLedger`: loại vì điểm có hạn dùng, có tỉ lệ quy đổi và là khuyến mãi.
- Dùng `Coupon`: loại vì coupon là mã khuyến mãi theo chiến dịch, không có số dư và không đổi ra tiền.
- Một cột số dư rồi tính phần đang giữ từ các bút toán: loại vì đối soát phải cộng dồn lịch sử và khó khoá.

### D3. Sổ cái owner

- Thêm tài khoản hệ thống `owner_credit_liability` (liability) vào `LedgerService::systemAccounts`.
- **Phát hành.** Với attempt có `purpose` `resale`, `postDirectOwnerReceipt` ghi Có `owner_credit_liability` thay cho `owner_direct_revenue`. Journal receipt chính là journal phát hành, nên một receipt chỉ có một journal. Bút toán `issue` lưu id receipt trong `reference`.
- **Tiêu credit.** `capture` (đặt sân) và `spend` (POS) ghi Nợ `owner_credit_liability` / Có `owner_direct_revenue`.
  - `event_type` `venue_credit.spend`, `event_id` `venue-credit:{entry_id}`, `ledger_entity` owner, `payment_model` `direct_owner_v1`, qua `postEntityJournal`.
- **Hoàn đơn POS về credit.** Ghi ngược lại (`venue_credit.return`).
- **Đổi tiền mặt.** Ghi Nợ `owner_credit_liability` / Có `cash_clearing` (`venue_credit.cash_out`). Dùng `cash_clearing` vì đó là tài khoản mà tiền mặt POS của cùng ngăn kéo đang ghi Nợ.
- **POS.** `postPosSale` chỉ ghi phần không phải credit. Nếu phần đó bằng 0 thì không ghi.
- **Đối soát.** Với mỗi tổ chức, Σ(`balance` + `reserved`) = số dư `owner_credit_liability` = Σ(`balance_delta` + `reserved_delta`). Viết một test cho bất biến này.

Phương án đã cân nhắc: ghi doanh thu khi bán rồi ghi chi phí khi tiêu. Loại vì sổ sẽ báo doanh thu chủ sân chưa thực sự có, và nợ với khách không hiện ra ở đâu.

### D4. Luật giữ chỗ (hold)

- Route `checkout` thêm middleware `verified`. `lock()` khoá dòng người mua (`OrganizationLockRepositoryInterface::lockUserForUpdate`) rồi kiểm tra người mua chưa giữ listing nào còn hạn. Nếu đã giữ thì trả 409.
- Thứ tự khoá trong transaction giữ chỗ: user → venue `FOR KEY SHARE` → listing → attempt mới. Listing paid mà venue không có kết nối nhận đã xác minh thì bị từ chối trước khi khoá listing.
- `hasConfirmableResale` chạy sau khi attempt đã bị khoá trong `processReceipt`, theo thứ tự listing → entitlement → reservation. Đây là thứ tự D12 của align-module-04. Hàm trả `true` khi:
  - listing ở `checkout`, `locked_by` = `attempt.user_id`, `reference` = `attempt.reference`;
  - và `transferable()` vẫn đúng.
- `release` chỉ còn cho người mua (người bán gọi thì 403). Người mua huỷ thì:
  1. nhả khoá trong transaction;
  2. sau commit gọi `expirePendingForPurpose('resale', id)` qua `DB::afterCommit`, để đường giữ chỗ không bao giờ UPDATE attempt cũ khi đang giữ khoá listing.
- `withdraw` trả 409 khi listing đang có hold còn hạn.
- `sweep` giữ phần nhả khoá hết hạn và rút listing quá giờ chốt, rồi hết hạn attempt sau commit. Bỏ các thông báo về "đã báo chuyển".
- `transfer()`:
  - ghi `metadata.settlement_mode` `owner_collected`, `idempotency_key` `resale:listing:{id}`, `marketplace_fee` 0;
  - `buyer_payment_reference` = reference của attempt;
  - các bản ghi cũ có khoá `p2p:` giữ nguyên.
- `ResaleListingState`:
  - bỏ `receiver` và `payment_reported_at`;
  - `payment` lấy từ attempt (QR, số tiền, reference, `expires_at`) và chỉ trả cho người giữ khoá;
  - thêm `credit_issued` cho người bán khi listing `sold`.
- Thông báo: người bán nhận "Suất đã bán · +X credit tại <tổ chức>". Người mua nhận "Đã nhận suất".

### D5. Credit khi đặt sân thường

- `OrdinaryCheckoutService` nhận thêm trường `venue_credit_amount` (số nguyên ≥ 0), áp dụng sau voucher, coupon, membership và điểm, ngay sau `assertAgreedAmount`. Số tiền vượt phần phải trả hoặc vượt số dư trả 422.
- Chỉ áp dụng khi chính khách tự checkout. Nhân viên đặt hộ không được dùng credit của khách.
- Credit được `reserve` trong cùng transaction tạo hold. QR chỉ hỏi phần còn lại.
- Nếu `netPayable` sau credit bằng 0, đơn được xác nhận ngay (giống nhánh `netPayable` 0 hiện có) và `capture` chạy luôn.
- Xác nhận bằng receipt: nhánh `booking` của `processReceipt` gọi `VenueCreditService::captureForReservation` sau khi chuyển reservation sang `confirmed`.
- Hold hết hạn hoặc khách huỷ: `HeldReservationRelease::releaseHeld` gọi `release` ngay cạnh `releaseReservedPoints`.
- `recurring`, `split`, `tournament` và marketplace không nhận trường này. Gửi lên thì 422.

### D6. Tender credit ở POS

- Thêm tender `credit`, dùng riêng hoặc trong `mixed`.
- Bắt buộc có khách đã xác định (`customer_id`, giống tender `wallet`) và có credit ở cùng tổ chức với ca đang mở.
- `spend` chạy trong transaction hoàn tất đơn, khoá tài khoản credit sau khi khoá đơn và tồn kho.
- Đơn có phần QR thì `spend` chạy lúc confirmer QR hoàn tất đơn (`PosQrPaymentService::confirmPaidAttempt`), không chạy lúc tạo đơn chờ. Lúc tạo đơn chỉ kiểm tra số dư.

  > ponytail: chỗ này có cửa sổ race. Khách có thể tiêu credit ở nơi khác trong lúc đơn chờ QR, nên lúc hoàn tất có thể thiếu số dư. `hasConfirmableOrder` cũng kiểm tra số dư credit, để trường hợp thiếu thành ngoại lệ `pos_order_unavailable` thay vì làm hỏng webhook, rồi thu ngân thu lại. Nếu lỗi này xảy ra nhiều thì nâng lên `reserve` ở POS.

- `refundOrder` với `original_method` trả phần credit về credit (`return`), phần còn lại xử lý như cũ.
- `WalletAvailabilityService` không liên quan đến tender này, nên không mở lại tender `wallet`.

### D7. Mã đổi tiền mặt

- Bảng `venue_credit_cash_out_codes`:
  - cột: `organization_id`, `user_id`, `code_hash` (unique), `amount`, `expires_at`, `used_at`, `used_by`, `cash_shift_id`, timestamps.
- Khách tạo mã: 8 ký tự in hoa, bỏ các ký tự dễ nhầm `0O1I`, kèm QR chứa mã. Mã có hạn 10 phút. Lúc tạo chỉ kiểm tra số dư, không giữ credit, vì khách vẫn đang đứng ở quầy.
- Mỗi lần tạo mã mới thì huỷ mã cũ chưa dùng của cùng khách tại cùng tổ chức. Mỗi khách chỉ có một mã sống tại một tổ chức.
- POS có hai bước:
  - **lookup** chỉ trả tên khách và số tiền khi mã thuộc tổ chức của ca đang mở;
  - **confirm** `lockForUpdate` mã → tài khoản credit → ca, kiểm tra còn hạn, chưa dùng, số dư đủ, rồi:
    1. ghi `cash_out`;
    2. gọi `recordCashMovement($shift, $cashier, 'cash_out', $amount, 'venue_credit:'.$code->id)`;
    3. ghi journal (D3);
    4. đặt `used_at`.
- Mã sai, hết hạn hoặc thuộc tổ chức khác đều trả cùng một lỗi 422, không lộ khách hay số tiền. Lookup bị giới hạn tần suất theo ca.

### D8. Endpoint

| Route | Middleware | Việc |
|---|---|---|
| `GET me/venue-credits` | auth, not_restricted | Số dư và bút toán theo từng tổ chức của chính khách |
| `POST me/venue-credits/{organization}/cash-out-codes` | thêm `verified` | Tạo mã đổi tiền |
| `GET pos/venue-credits/customers/{user}` | `feature:tenant_pos_and_wallet`, `organization_owner,staff` | Số dư của khách đang phục vụ |
| `POST pos/shifts/{shift}/venue-credit-cash-outs/lookup` | như trên | Tra mã |
| `POST pos/shifts/{shift}/venue-credit-cash-outs` | như trên | Xác nhận đổi tiền |
| `GET owner-venue-credits` | `organization_owner`, `feature:direct_owner_payments` | Tổng credit chưa dùng và bút toán của tổ chức (giống `owner-payment-exceptions`) |

- Bỏ `payment-sent` và `confirm-receipt` khỏi `routes/api.php` và khỏi controller.
- Đọc chéo tổ chức trả 403 qua `RequireOrganizationRole` và tenant scope.

### D9. Dữ liệu khi deploy

- Một migration dữ liệu đưa mọi listing `checkout` về `listed` (hoặc `withdrawn` nếu đã quá giờ chốt). Migration bỏ khoá P2P trong `metadata`, ghi `last_release.by` = `cutover`, rồi gửi thông báo cho người mua bị nhả.
- Migration xoá cột `seller_receiver_ciphertext` (thêm bởi `2026_10_14_000007_add_seller_receiver_to_marketplace_listings`) và các cột liên quan. Hàm `down` chỉ thêm lại cột rỗng. Dữ liệu tài khoản người bán bị xoá vĩnh viễn, và đó là chủ ý.
- Migration tạo ba bảng credit.
- Cập nhật `MarketplaceSeeder`, `E2ESeedFixturesCommand` và seed demo để có:
  - một lượt bán do chủ sân thu, có credit;
  - một lượt đặt sân trả một phần bằng credit;
  - một lượt đổi tiền mặt.

### D10. Đoạn thay cho report 2.3.9

Người dùng tự sửa report. Đoạn đề xuất:

> Người mua thanh toán đúng giá bán bằng QR vào tài khoản nhận đã xác minh của chủ sân. Khi cổng thanh toán xác nhận tiền đã về đúng số tiền và mã, hệ thống chuyển suất cho người mua và cấp mã check-in mới. Người bán nhận credit sân bằng giá bán, dùng ở mọi sân của cùng chủ. Credit trừ vào tiền đặt sân, trả tại quầy, hoặc đổi tiền mặt tại quầy bằng mã dùng một lần. Credit không hết hạn, không nạp và không chuyển được. Nền tảng không giữ tiền và không thu phí nhượng.

### D11. Quyết định khi apply (2026-10-25)

Các điểm spec chưa nói rõ, đã chốt khi làm. Mỗi điểm ghi phương án được chọn, phương án bị bỏ và lý do.

- **Sổ cái:**
  - `event_type` là `venue_credit.{type}`.
  - `source_id` lưu dạng chuỗi, vì nguồn có thể là id số hoặc mã.
  - Không viết factory cho credit. Test tạo credit bằng `issue`.
- **Tách `ResaleTransfer` khỏi `OwnerCollectedResaleService`:**
  - Phần chuyển suất (`transferable`, `move`) tách sang `ResaleTransfer`, để đường hold và đường receipt dùng chung và mỗi file nằm dưới ngưỡng 300 dòng.
  - Phương án bị bỏ: giữ một service lớn rồi thêm vào allowlist.
- **Hold khi bán lại:**
  - `lockSlot` vẫn khoá court rồi venue `FOR UPDATE` (D9).
  - Khi một hold đã hết hạn bị thay, attempt cũ không bị làm hết hạn ngay. Attempt tự hết hạn theo `expires_at`, và tiền đến muộn thành ngoại lệ của chủ sân.
  - `hasConfirmableResale` không đòi `lockActive`. Hạn của attempt đã được `applyAttemptChecks` kiểm tra.
- **Thông báo cutover:** chỉ gửi qua kênh database.
- **Credit khi đặt sân:**
  - `total_amount` vẫn là giá đầy đủ. Credit ghi ở `metadata.venue_credit_amount`.
  - `ReservationPaymentService::initiate` trừ `reservedFor()`, nên tạo lại QR cũng chỉ hỏi phần còn lại.
  - Phương án bị bỏ: giảm `total_amount`, vì làm lệch doanh thu và hoàn tiền.
  - `POST reservations` (staff) cấm `venue_credit_amount`. `checkout` cấm khi có `customer_id` (`prohibits`).
- **POS:**
  - Tách `PosRefundService` khỏi `PosService` để thêm credit mà không vượt trần dòng.
  - Hoàn tiền `original_method` trả phần credit về trước, phần còn lại mới hoàn bằng tiền.
  - `QuotePosOrderRequest` và `CompletePosOrderRequest` nhận `credit`.
- **Mã đổi tiền:**
  - Tạo mã không dùng idempotency, vì khách chưa có ngữ cảnh tổ chức, và mã mới đằng nào cũng huỷ mã cũ.
  - Xác nhận đổi tiền có dùng idempotency.
  - Lookup bị giới hạn 10 lần/phút cho mỗi ca.
- **Endpoint xem:**
  - Sao kê của khách trả 50 bút toán mới nhất cho mỗi tổ chức (`ponytail:`). Thêm phân trang theo tổ chức khi cần xem cũ hơn.
  - Danh sách của chủ sân có phân trang và kèm `outstanding`.
- **Test:**
  - Nằm ở `tests/Feature/ResaleVenueCredit`.
  - Bộ test cũ liệt kê ở task 4.8 đã bị xoá khỏi repo ngày 2026-10-22, nên các scenario được phủ bằng test mới.
  - Khách gửi `organization_id` qua input chứ không qua header, vì header chỉ dành cho thành viên tổ chức.
- **Seed:**
  - Repo không có `E2ESeedFixturesCommand`, nên chỉ sửa `MarketplaceSeeder`.
  - Khách demo bán một suất (`RES-MY-101`), rồi dùng credit trả tối đa nửa lượt `RES-CREDIT-001`.
  - Người bán số 5 đổi 50.000đ ở ca đang mở của thu ngân demo.
- **Sửa lỗi có sẵn:** `VerifyEffectiveBookingEligibility::assertResalable` đọc `message` ngay cả khi venue hợp lệ, gây lỗi 500. Giờ nó chỉ đọc `message` khi từ chối.
- **Tiền đến sau khi người mua huỷ hold:** exception có mã `transfer_after_expiry`, giống trường hợp hết hạn. Lý do: huỷ hold cũng hết hạn attempt, và pipeline kiểm tra hết hạn trước "attempt không còn pending". Spec chỉ yêu cầu đây là owner payment exception, không quy định mã.
- **POS:** credit trả theo tổng đơn đã gồm VAT, giống tiền mặt.
- **Frontend:**
  - Vẫn giữ namespace i18n `resale.p2p`, chỉ viết lại nội dung chuỗi.
  - "Đã bán · +X credit" lấy theo giá bán, vì danh sách listing không trả `credit_issued`; spec quy định credit bằng giá bán.
  - Đơn POS trả hỗn hợp có credit luôn gửi `allow_mixed_tender`.
  - Màn đổi tiền ở POS không quét bằng camera: máy quét USB gõ thẳng vào ô mã.

## Risks / Trade-offs

- [Chủ sân hết tiền mặt hoặc không chịu đổi credit] → Credit không hết hạn và vẫn dùng được cho đặt sân và POS. Chủ sân thấy tổng credit chưa dùng trong màn tài chính. Report ghi rõ đổi tiền mặt phụ thuộc quầy.
- [Người bán không ở gần sân nên credit khó đổi thành tiền] → Đây là đánh đổi người dùng đã chấp nhận ngày 2026-10-24, thay cho việc chủ sân chuyển khoản. Màn đăng bán ghi rõ "nhận credit tại <tổ chức>".
- [Tiền đến sau khi hold hết hạn] → `applyAttemptChecks` đã đánh dấu `late`/`transfer_after_expiry`. Khoản đó thành ngoại lệ của chủ sân, và chủ sân xử lý như đơn đặt sân trễ. Suất và credit không đổi.
- [Mã đổi tiền bị nhìn trộm hoặc chụp lại] → Mã có hạn 10 phút, dùng một lần, khoá theo tổ chức, tạo mã mới thì mã cũ bị huỷ. Thu ngân thấy tên khách trước khi đưa tiền.
- [Race giữa POS đang chờ QR và lúc tiêu credit] → Xem ghi chú `ponytail:` ở D6.
- [Cash-out ghi vào `cash_clearing` trong khi tiền mặt POS của tổ chức direct vẫn ở sổ tenant] → Khoản nợ credit vẫn đối soát đúng. Việc gom tiền mặt POS về sổ owner để cho change khác.
- [Đổi tên service làm vỡ test và import] → Cutover một lần, `grep` mọi chỗ gọi, không giữ alias.

## Migration Plan

1. Deploy backend gồm migration bảng credit, migration nhả khoá P2P và migration xoá cột tài khoản người bán. Frontend deploy ngay sau, vì app cũ còn gọi `payment-sent` sẽ nhận 404 và chỉ cần tải lại.
2. Reseed demo trên bt-server theo quy trình thường lệ (pg_dump trước).
3. Rollback: revert code và chạy `down` cho bảng credit. Cột tài khoản người bán quay lại rỗng. Khoá P2P đã nhả không khôi phục, vì listing chỉ đang quay về trạng thái bán.
