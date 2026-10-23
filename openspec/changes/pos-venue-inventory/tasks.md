## 1. Pha 1 – Ca theo cơ sở (D8)

Nhánh riêng. Không đổi hợp đồng API kho, nên merge và deploy được trước các pha sau.

- [x] 1.1 `PosController::activeShift`: bỏ nhánh lấy ca mở bất kỳ của tổ chức. Không có ca của người gọi thì trả `{"shift": null}`.
- [x] 1.2 `PosController::shifts`:
  - Lọc theo `getAuthorizedVenueIds`. Owner thấy thêm ca có `venue_id` null.
  - Nạp `venue:id,name`.
  - Rút khối tính tổng tiền ca dùng chung với `activeShift` thành một private method.
  - `PosController` không vượt 650 dòng. Nếu ngắn đi thì hạ ngưỡng trong allowlist.
- [x] 1.3 `PosQrPaymentService::assertOperableVenue`: bỏ ngoại lệ cho owner khi `venueId` null. `venueIdFor` giữ nguyên.
- [x] 1.4 `PosService::completeOrder`:
  - Bắt buộc có ca đang mở. Cơ sở của đơn là cơ sở của ca.
  - `venue_id` client gửi khác cơ sở của ca → 422.
  - Booking thuộc cơ sở khác → 422.
  - Mixed tender đọc chính sách từ cơ sở của đơn.
  - Mọi kiểm tra chạy trước transaction.
- [x] 1.5 Frontend `useAdminShiftReconciliation::mapPosShift`:
  - Lấy `venue_id` và `venue.name` từ API.
  - Ca không có cơ sở hiện "Chưa gắn cơ sở" và không khớp bộ lọc cơ sở nào.
  - Bỏ fallback `venuesList[0]`, `|| 1` và tên mặc định "Aura Badminton Arena".
- [x] 1.6 Test `backend/tests/Feature/PosVenueInventory/ShiftVenueScopeTest.php`, phủ mọi scenario của spec `pos-shift-venue-scope`:
  - Owner chưa có ca thì nhận `null` dù staff đang có ca.
  - Staff chỉ thấy ca của cơ sở được giao.
  - Ca không có cơ sở chỉ owner thấy.
  - Owner có hai cơ sở mở ca không chọn cơ sở → 422.
  - Staff có một cơ sở được tự gán.
  - Booking khác cơ sở → 422, không có payment, movement hay điểm.

  Thêm một spec Vitest cho `mapPosShift`.
- [ ] 1.7 Gate theo `local-test-gates`: Pint, PHPStan 0/5, `tests/Unit/Architecture`, test 1.6, và chuỗi `frontend_check`. Merge `--no-ff` vào main. Hẹn giờ với người dùng rồi push và chạy `glab ci run -b main`.

## 2. Pha 2 – Schema, cutover và repository (D1, D4, D6)

- [x] 2.1 Migration `create_venue_stocks_and_stock_transfers`:
  - Tạo `venue_stocks` và `stock_transfers` kèm unique, FK và check như D1, D5.
  - Chạy cutover D6 theo thứ tự: kiểm trước → chọn cơ sở → tạo `venue_stocks` → `stock_movements.venue_id` (backfill, `metadata.venue_backfill`, NOT NULL, FK, index) → xoá `pos_products.stock_quantity`.
  - Viết `down()` như D6.
- [x] 2.2 Model:
  - `VenueStock` mới.
  - `StockTransfer` mới.
  - `StockMovement` thêm `venue_id` và quan hệ `venue`.
  - `PosProduct` bỏ `stock_quantity` khỏi `Fillable` và casts, thêm quan hệ `venueStocks`.
- [x] 2.3 `VenueStockRepositoryInterface` và `EloquentVenueStockRepository` với `lock`, `held`, `apply`, `quantities` (D4). Bind trong `RepositoryServiceProvider`.
- [x] 2.4 Test `StockCutoverMigrationTest`:
  - Tổ chức hai cơ sở có 36 đơn vị: dồn đúng vào cơ sở active có id nhỏ nhất, tổng giữ nguyên, mọi movement có `venue_id` và cờ backfill.
  - Tổ chức có tồn nhưng không có cơ sở: migration dừng, nêu id, dữ liệu không đổi.
  - `down()` trả lại tổng.

## 3. Pha 3 – Luồng bán, giữ hàng, trả hàng theo cơ sở (D2, D3, D4)

- [x] 3.1 `PosService::createProduct`: `venue_id` bắt buộc khi `stock_quantity > 0`, ghi `initial_stock` qua repository. FormRequest hoặc rule validate tương ứng ở controller giữ trong ngưỡng inline-validation.
- [x] 3.2 `PosService::adjustStock` và `PosController::adjustStock`:
  - Nhận `venue_id` bắt buộc và kiểm cơ sở thuộc tổ chức.
  - Chế độ "đặt bằng số lượng" tính delta trên tồn của cơ sở đó.
- [x] 3.3 `PosService::completeOrder`:
  - Khoá `venue_stocks` theo `product_id` tăng dần.
  - Kiểm `quantity - held(venue)`.
  - Trừ qua repository khi đơn hoàn tất ngay.
- [x] 3.4 `PosQrPaymentService::confirmPaidAttempt`: trừ ở `order.venue_id` qua repository.
- [x] 3.5 `ServiceTabService`:
  - `getAvailableStock` nhận cơ sở của booking.
  - `addItem(s)` kiểm theo cơ sở đó.
  - `settleTab` trừ ở cơ sở đó qua repository.
- [x] 3.6 `PosService::refundOrder`:
  - Nhập lại ở `order.venue_id`.
  - Đơn không có cơ sở mà cần nhập kho → 422.
  - `restock = false` vẫn trả tiền được.
- [x] 3.7 `PosController::products`, `movements`, `inventoryKpis`:
  - Chọn cơ sở theo D2. Ngoài phạm vi → 403.
  - `stock_quantity` là tồn của cơ sở, hoặc `null`.
  - Movements và KPI chỉ của cơ sở đó.
  - Không vượt ngưỡng allowlist.
- [x] 3.8 Test `VenueStockBalanceTest`, phủ các scenario của "Venue-scoped stock balance" và "Stock events bound to one venue":
  - Bán ở A không đổi B.
  - Cơ sở mới bằng 0.
  - Dịch vụ không trừ kho.
  - Nhập ở B.
  - Trả hàng về A dù ca ở B.
  - QR muộn trừ ở A.
  - Thiếu `venue_id` → 422.
- [x] 3.9 Test `VenueStockHoldTest`:
  - Giữ hàng ở A không chặn B.
  - Hai yêu cầu đồng thời cho đơn vị cuối ở A: đúng một thành công, lỗi nêu sản phẩm.
- [x] 3.10 Test `InventoryVisibilityTest`:
  - Staff đọc được cơ sở được giao, cơ sở khác → 403.
  - KPI "sắp hết" tính theo từng cơ sở.

## 4. Pha 4 – Điều chuyển giữa cơ sở (D5)

- [x] 4.1 Thêm các lớp:
  - `StockTransferRepositoryInterface` kèm bản Eloquent, bind trong provider.
  - `Domain/Inventory/DTOs/TransferStockData`.
  - `Domain/Inventory/Actions/TransferStock`: khoá theo `venue_id` tăng dần, kiểm số còn bán được ở nguồn, ghi phiếu và hai movement.
- [x] 4.2 `Http/Requests/POS/StoreStockTransferRequest` và `Http/Controllers/POS/StockTransferController` (`index`, `store`).
  - Route `POST pos/inventory/transfers`: chỉ owner, chạy qua `IdempotencyService::run`.
  - Route `GET pos/inventory/transfers`: owner và staff, staff theo phạm vi D7.
- [x] 4.3 Test `StockTransferTest`, phủ mọi scenario của "Inter-venue stock transfer":
  - Chuyển thành công.
  - Vượt số còn bán được.
  - Cơ sở tổ chức khác.
  - Cùng cơ sở.
  - Sản phẩm không theo dõi tồn.
  - Staff → 403.
  - Gửi lại cùng idempotency key thì chỉ chuyển một lần.

## 5. Pha 5 – Frontend (D9)

- [x] 5.1 `features/admin`:
  - `types/inventory.ts`: thêm `venue_id` vào payload kho và `StockTransfer`.
  - `inventoryApi`: gửi `venue_id`, thêm `getTransfers` và `createTransfer`.
  - `useAdminInventory`: giữ cơ sở đang chọn và tải lại khi đổi.
- [x] 5.2 `AdminInventoryView`: ô chọn cơ sở (owner thấy mọi cơ sở, staff thấy cơ sở được giao). KPI, bảng sản phẩm, lịch sử và modal kho theo cơ sở đó. Modal sản phẩm gửi `venue_id` khi có tồn ban đầu.
- [x] 5.3 `InventoryTransferModal.vue` (owner, dưới 400 dòng): cơ sở đích khác cơ sở nguồn, số lượng tối đa bằng số còn bán được, ghi chú, hiện lỗi 409/422 theo từng trường. Bảng lịch sử hiển thị `transfer_in` và `transfer_out`. Thêm chuỗi i18n vi và en.
- [x] 5.4 `PosView`: tải sản phẩm sau `checkActiveShift`, và tải lại sau `handleOpenShift`.

  Ghi chú khi apply: giữ lời tải song song lúc mở trang, vì backend tự đọc ca đang mở của người gọi (D2) nên thứ tự không đổi kết quả. `PosView` đang ở trần 772 dòng. Sau `handleOpenShift` thì tải lại sản phẩm.
- [x] 5.5 Vitest:
  - Modal điều chuyển chặn số lượng vượt mức và gửi đúng payload.
  - `useAdminInventory` gửi `venue_id` của cơ sở đang chọn.

## 6. Pha 6 – Dữ liệu, gate và triển khai

- [x] 6.1 Seeder:
  - `PosProductSeeder`: tồn ban đầu cho từng cơ sở của tổ chức, mỗi cơ sở một movement `initial_stock`.
  - `PosSalesSeeder`: theo dõi tồn theo (cơ sở, sản phẩm). Bước tự kiểm so tồn với tổng movement của từng cơ sở.
  - `E2ESeedFixturesCommand` và `E2EArrangeTestDataCommand`: bỏ `stock_quantity` trên sản phẩm, ghi `venue_stocks`.

  Ghi chú khi apply: repo hiện không còn hai lệnh E2E này nên phần đó không áp dụng. Chuỗi seed tới `PosSalesSeeder` đã chạy hai lần trên DB test, bước tự kiểm đạt cả hai lần.
- [x] 6.2 Gate đầy đủ theo `local-test-gates`:
  - Backend: Pint, PHPStan 0/5 (baseline chỉ được giảm), `tests/Unit/Architecture`, toàn bộ `tests/Feature/PosVenueInventory`, `tests/Feature/Module0*`.
  - Frontend: oxlint, eslint, `format:check`, `type-check`, Vitest với `--maxWorkers=1`, `build-only`.

  Ghi chú khi apply: tất cả đạt. Trần allowlist kiến trúc của `PosController` (dòng, model-query), `PosService` và `ServiceTabService` hạ về số thực tế. Baseline PHPStan hạ 5 số đếm, bỏ 7 mục không còn khớp; thêm generic cho quan hệ `VenueStock`, `ServiceTab::items` và `ServiceTabItem::product`. `eslint-suppressions.json` hạ một số đếm của `useAdminShiftReconciliation.ts`.
- [x] 6.3 Đọc prod `booking` (chỉ đọc):
  - Đếm tổ chức có `pos_products.stock_quantity > 0` hoặc có `stock_movements` mà không có cơ sở.
  - Ghi lại cơ sở sẽ nhận tồn cho từng tổ chức theo D6.

  Có tổ chức không có cơ sở thì dừng và hỏi người dùng.

  Kết quả đọc ngày 2026-10-23 (`default_transaction_read_only=on`):
  - Không có tổ chức nào thiếu cơ sở. Không có sản phẩm hay movement nào thiếu `organization_id`.
  - Tổ chức 1 `hoang-gia-sports`: 1 cơ sở; nhận tồn tại cơ sở 6 "Hoàng Gia Sports Phú Mỹ Hưng" (active); 9 sản phẩm có tồn, tổng 458, 34 movement.
  - Tổ chức 2 `aura-badminton-club`: 5 cơ sở; nhận tồn tại cơ sở 1 "Aura Court Cầu Giấy" (active); 25 sản phẩm có tồn, tổng 1399, 167 movement.
  - Mốc so sánh sau deploy: 34 sản phẩm, tổng tồn 1857; 201 movement, tổng `quantity_delta` 1857.
  - Migration chờ chạy duy nhất là `2026_10_23_000001_create_venue_stocks_and_stock_transfers`; batch lớn nhất hiện là 2, nên batch thủ công kế tiếp là 3.
  - 1 ca đang mở, đã có cơ sở.
- [ ] 6.4 Backup prod theo quy trình reseed. Merge `--no-ff` vào main. Hẹn giờ với người dùng rồi push và chạy `glab ci run -b main`. Sau deploy, đọc lại prod để xác nhận tổng tồn mỗi sản phẩm không đổi.
- [ ] 6.5 `openspec validate pos-venue-inventory --strict` đạt, tick task. Archive khi người dùng đồng ý.
