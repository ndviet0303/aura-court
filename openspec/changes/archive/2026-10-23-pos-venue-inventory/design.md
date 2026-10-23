## Context

Lý do và phạm vi xem `proposal.md`. Phần này ghi hiện trạng code ở `origin/main` `4128da51` và các ràng buộc quyết định cách làm.

- **Tồn kho theo tổ chức.**
  - `pos_products (organization_id, sku, …, stock_quantity unsigned)`, unique `(organization_id, sku)` và `(organization_id, barcode)`.
  - `stock_movements (organization_id, product_id, type, quantity_delta, unit_cost, reference_type, reference_id, created_by, metadata)`. Không có `venue_id`. `metadata.balance_after` là tồn của cả tổ chức.
  - Migration gốc: `2026_08_22_000012_create_pos_membership_wallet_tables.php`.
- **Các chỗ ghi tồn.** Mỗi chỗ tự `increment` hoặc `decrement` rồi tự tạo `StockMovement`:
  - `PosService`: `createProduct` (`initial_stock`), `adjustStock`, `completeOrder` (`sale`), `refundOrder` (`return`).
  - `PosQrPaymentService::confirmPaidAttempt`: QR xác nhận muộn.
  - `ServiceTabService::settleTab`.
- **Giữ hàng** được tính lúc đọc, không lưu thành bảng riêng:
  - Tab dịch vụ: `ServiceTabItem` ở trạng thái `reserved`. Tab thuộc booking, và booking → sân → `venue_id`.
  - QR đang chờ: `PosOrderItem` của đơn `pending_payment`. Đơn đã có `venue_id`.
  - Có ở `PosService::completeOrder` và `ServiceTabService::getAvailableStock`. Cả hai đang cộng giữ hàng của cả tổ chức.
- **Cơ sở của đơn** tính ở `PosService::completeOrder`, thứ tự ưu tiên:
  1. `venue_id` client gửi.
  2. Cơ sở của ca.
  3. Cơ sở của booking.
  4. `PosQrPaymentService::venueIdFor`: tự chọn khi người gọi chỉ có một cơ sở.

  Sau đó `assertOperableVenue` cho phép `null` nếu người gọi là owner.
- **Ca.**
  - `PosController::activeShift` lấy ca của người gọi, không có thì lấy ca mở bất kỳ của tổ chức.
  - `PosController::shifts` không lọc theo cơ sở và không nạp quan hệ `venue`.
  - Frontend `useAdminShiftReconciliation::mapPosShift` lấp `venue_id` và `venue_name` bằng `venuesList[0]`.
  - Hai hàm trong controller lặp lại khoảng 40 dòng tính tổng tiền ca.
- **Giới hạn kiến trúc** (`architecture-allowlist.json`):
  - `PosController`: tối đa 650 dòng, 28 method public, 15 inline validation, 15 model query.
  - `PosService`: tối đa 1092 dòng. `ServiceTabService`: tối đa 439 dòng.
  - Các ngưỡng này chỉ được giảm. Code mới phải theo Controller → FormRequest → DTO → Action → Repository.
- **Phân quyền route** (`routes/api.php:540-560`):
  - Ghi sản phẩm và kho: chỉ `organization_owner`.
  - Đọc movements và KPI, mở/đóng ca, bán: owner và staff.
  - Kiểm cơ sở dùng `User::getAuthorizedVenueIds($orgId)` và `canOperateVenue($venue)`.
- **Triển khai.** Push lên `main` tự deploy frontend qua Cloudflare. Backend chỉ deploy khi chạy tay `glab ci run -b main`.

## Goals / Non-Goals

**Goals:**

- Mọi số tồn và biến động đều đúng một cơ sở. Với mỗi cặp (cơ sở, sản phẩm), tồn luôn bằng tổng biến động.
- Gom code ghi tồn đang lặp ở ba service về một chỗ. Các file cũ nhỏ đi, không file nào vượt ngưỡng.
- Phần sửa ca (Pha 1) merge và deploy được riêng, trước phần kho.

**Non-Goals:**

- Điều chuyển hai bước (gửi, đang vận chuyển, nhận).
- Giá, VAT, ngưỡng tối thiểu hoặc danh mục riêng theo cơ sở.
- Cho staff ghi kho. Quyền ghi giữ nguyên là owner.
- Màn tổng hợp tồn "tất cả cơ sở".
- Quy trình kiểm kê riêng. Kiểm kê vẫn dùng điều chỉnh tồn như hiện nay.
- Sửa `venue_id` của đơn POS cũ.

## Decisions

### D1. Bảng `venue_stocks` giữ số tồn

`venue_stocks (id, organization_id, venue_id, product_id, quantity unsigned default 0, timestamps)`:

- unique `(venue_id, product_id)`.
- index `(organization_id, product_id)`.
- FK `venue_id` → `venues` restrict, `product_id` → `pos_products` restrict.

Dòng được tạo khi có biến động đầu tiên. Ghi bằng `insertOrIgnore` rồi `lockForUpdate`, nên hai request đồng thời không tạo trùng.

Phương án khác:

- Tính tồn bằng `SUM(stock_movements)` mỗi lần đọc. Không khoá được một dòng khi bán đồng thời, và chậm dần theo lịch sử.
- JSON `{venue_id: qty}` trên `pos_products`. Mọi cơ sở phải tranh nhau khoá chung một dòng sản phẩm.

### D2. Xoá `pos_products.stock_quantity`, API vẫn trả trường `stock_quantity`

Cutover sạch: xoá cột, không giữ song song. API sản phẩm vẫn trả `stock_quantity`, nhưng từ nay là tồn tại cơ sở được hỏi. Nhờ vậy type ở frontend (`InventoryProduct`, `Product`) không đổi. Nếu không xác định được cơ sở, trường này là `null`: chỉ trả danh mục.

Cơ sở của một lượt đọc lấy theo thứ tự:

1. Query `venue_id`. Phải qua `canOperateVenue`, nếu không → 403.
2. Cơ sở của ca đang mở của người gọi.
3. Cơ sở duy nhất người gọi được vận hành.
4. Không có → `null`.

Phương án khác: thêm trường mới `venue_stock` và giữ `stock_quantity` cũ. Hai trường cho một ý nghĩa sẽ dễ dùng nhầm.

### D3. Cơ sở của một lượt ghi tồn

| Thao tác | Cơ sở |
|---|---|
| Nhập, xuất, điều chỉnh | `venue_id` bắt buộc trong request |
| Tạo sản phẩm có tồn ban đầu | `venue_id` bắt buộc khi `stock_quantity > 0` |
| Bán | `pos_orders.venue_id`, tức cơ sở của ca (D8) |
| QR xác nhận muộn | `pos_orders.venue_id` |
| Thêm vào tab, chốt tab | cơ sở của sân trong booking của tab |
| Trả hàng | `pos_orders.venue_id` của đơn gốc |

Đơn cũ có `venue_id = null` không nhập lại kho được. Nếu cần nhập kho khi trả hàng, request bị 422 với thông báo rõ. Trả tiền với `restock = false` vẫn làm được.

### D4. Repository dùng chung cho mọi lượt ghi tồn

Thêm `Contracts/Repositories/VenueStockRepositoryInterface` và `Infrastructure/Repositories/Eloquent/EloquentVenueStockRepository`, bind trong `RepositoryServiceProvider`. Repository có các method:

- `lock(orgId, venueId, productId): VenueStock`: tạo dòng nếu chưa có, rồi khoá dòng.
- `held(venueId, productId): int`: phần đang giữ, gồm tab `reserved` có booking ở cơ sở đó và item của đơn `pending_payment` có `venue_id` đó.
- `apply(VenueStock, delta, type, actorId, referenceType, referenceId, metadata): StockMovement`:
  - Từ chối nếu tồn âm, trả 409 nêu SKU.
  - Cập nhật `quantity`.
  - Ghi movement có `venue_id` và `metadata.balance_after` là tồn của cơ sở.
- `quantities(venueId, productIds): array` để đọc hàng loạt cho danh sách sản phẩm và KPI.

`PosService`, `ServiceTabService` và `PosQrPaymentService` bỏ các khối `increment`/`decrement` + `StockMovement::create` tự viết và gọi repository. Ba file vì vậy ngắn đi, đủ chỗ cho logic chọn cơ sở mà không phải nâng ngưỡng.

Khi một đơn khoá nhiều dòng, khoá theo `product_id` tăng dần để tránh deadlock. Điều chuyển khoá hai dòng theo `venue_id` tăng dần.

Phương án khác: một `VenueStockService` trong `app/Services/POS`. Không theo quy ước Repository cho truy vấn, và Action điều chuyển (D5) không được gọi Eloquent trực tiếp.

### D5. Điều chuyển một bước, có phiếu riêng

Bảng `stock_transfers (id, organization_id, product_id, from_venue_id, to_venue_id, quantity, note, created_by, created_at)`, có check `from_venue_id <> to_venue_id` và `quantity > 0`.

Một giao dịch làm các việc sau:

1. Khoá hai dòng tồn.
2. Kiểm số còn bán được ở nguồn: tồn trừ `held`.
3. Ghi phiếu.
4. Ghi movement `transfer_out` ở nguồn và `transfer_in` ở đích, cả hai có `reference_type = 'stock_transfer'` và `reference_id` = id phiếu.

Code theo kiến trúc mới:

- `Http/Controllers/POS/StockTransferController` với `index` và `store`.
- `Http/Requests/POS/StoreStockTransferRequest`.
- `Domain/Inventory/DTOs/TransferStockData`.
- `Domain/Inventory/Actions/TransferStock`.
- `StockTransferRepositoryInterface` kèm bản Eloquent.

Route:

- `POST pos/inventory/transfers`: chỉ owner, chạy qua `IdempotencyService::run`.
- `GET pos/inventory/transfers?venue_id=`: owner và staff, staff bị giới hạn theo D7.

Phương án khác:

- Điều chuyển hai bước có trạng thái "đang vận chuyển". Sát thực tế hơn nhưng thêm trạng thái, màn nhận hàng và việc xử lý phiếu treo. Người dùng chưa yêu cầu.
- Dùng hai lần điều chỉnh tồn. Mất liên kết giữa hai đầu và không chặn được lệch số lượng.

### D6. Cutover dữ liệu trong một migration

Postgres chạy DDL trong transaction, nên migration làm tất cả trong một lần:

1. **Kiểm trước.** Liệt kê tổ chức có `pos_products.stock_quantity > 0` hoặc có `stock_movements` nhưng không có cơ sở nào. Có thì `throw` kèm danh sách id và chưa sửa gì.
2. **Chọn cơ sở nhận tồn cho mỗi tổ chức.** Lấy cơ sở `active` có id nhỏ nhất. Không có cơ sở active thì lấy id nhỏ nhất bất kể trạng thái. Quy tắc này xác định được và giải thích được. Owner điều chuyển lại sau bằng D5.
3. **Tạo `venue_stocks`** từ các sản phẩm có `stock_quantity > 0`, ở cơ sở đã chọn.
4. **Movements.** Thêm `stock_movements.venue_id`, gán mọi dòng cũ vào cơ sở đã chọn và thêm `metadata.venue_backfill = true`. Đặt NOT NULL, FK, index `(venue_id, product_id, created_at)`. Sửa `idx` cũ nếu thừa.
5. **Xoá** `pos_products.stock_quantity`. Cột này không có check constraint riêng.

`down()`:

- Thêm lại cột, đặt bằng tổng `venue_stocks` theo sản phẩm.
- Xoá `venue_id` ở movements.
- Xoá `stock_transfers` và `venue_stocks`.

Tổng tồn được giữ. Phiếu điều chuyển mất khi rollback.

Phương án khác: gán movement bán hoặc trả theo `pos_orders.venue_id`. Khi đó tồn của từng cơ sở không còn bằng tổng biến động, và có thể âm vì hàng nhập ở A nhưng bán ở B. Bỏ, vì bất biến "tồn = tổng biến động" quan trọng hơn: seeder và kiểm toán đều dựa vào nó.

### D7. Phạm vi đọc kho

`products`, `movements`, `inventoryKpis` và `transfers` nhận `venue_id`, và chọn cơ sở mặc định theo D2.

- Owner đọc được mọi cơ sở của tổ chức.
- Staff chỉ đọc cơ sở trong `getAuthorizedVenueIds`. Cơ sở khác → 403.

KPI tính trên tồn của cơ sở đó so với `metadata.min_stock` chung của sản phẩm.

`PosController` đang chạm trần 650 dòng. Phần đọc tồn theo cơ sở chỉ là một lời gọi `quantities()`. Bù lại, rút khối tính tổng tiền ca bị lặp (D8) thành một private method.

### D8. Sửa ca và cơ sở của đơn (Pha 1)

- **`activeShift`**: bỏ nhánh lấy ca mở bất kỳ. Không có ca thì trả `{"shift": null}`. `usePosShift` và màn Quản lý Ca đã xử lý `null`.
- **`shifts`**:
  - Owner: `whereIn('venue_id', getAuthorizedVenueIds)` hoặc `venue_id IS NULL`.
  - Staff: chỉ `whereIn`.
  - Nạp `venue:id,name`.
  - Tổng tiền ca dùng chung một private method với `activeShift`.
- **`openShift`**: `assertOperableVenue` không còn cho `null`, kể cả owner. Vẫn tự chọn khi người gọi chỉ có một cơ sở (`venueIdFor`). `PosShiftModal` đã bắt chọn cơ sở khi có nhiều cơ sở.
- **`completeOrder`**:
  - Bắt buộc có ca đang mở. Điều này trùng điều kiện 409 đã có ở controller, nay đưa vào service.
  - Cơ sở của đơn là cơ sở của ca.
  - `venue_id` client gửi khác cơ sở của ca → 422.
  - Booking gắn vào thuộc sân của cơ sở khác → 422.
  - Chính sách mixed tender đọc từ cơ sở của đơn.
  - Các kiểm tra này chạy trước transaction, nên bị từ chối thì không có hiệu ứng nào.
- **Frontend `mapPosShift`**:
  - Dùng `raw.venue_id` và `raw.venue?.name`.
  - Ca không có cơ sở thì `venue_id = null`, tên là "Chưa gắn cơ sở".
  - Bỏ fallback `venuesList[0]` và `|| 1`.

Phương án khác: giữ fallback ca mở bất kỳ, nhưng chỉ cho owner xem. Bỏ, vì màn POS sẽ tưởng owner đang trong ca đó, rồi bị 409 khi thanh toán.

### D9. Frontend

- **Kho & Hàng hoá.**
  - Chọn cơ sở ở đầu trang. Owner chọn trong `adminApi.getVenues()`, staff chỉ thấy cơ sở được giao. Mặc định là cơ sở đầu tiên.
  - Mọi lời gọi `inventoryApi` gửi `venue_id`.
  - Modal mới `InventoryTransferModal.vue`, dưới 400 dòng, chỉ hiện cho owner: chọn cơ sở đích, số lượng tối đa bằng số còn bán được, ghi chú.
  - Bảng lịch sử thêm cột hoặc nhãn cho `transfer_in`/`transfer_out`.
- **POS.**
  - `PosView` tải sản phẩm sau `checkActiveShift`, và tải lại sau `handleOpenShift`. Backend tự lấy cơ sở từ ca (D2), nên `posApi.getProducts` không cần tham số.
- **Không file nào vượt trần** của `architecture.spec.ts`. `InventoryCatalogTable.vue` (850 dòng) đang trong allowlist và không được dài thêm.

### D10. Test

Main chỉ còn `tests/Feature/Module01..06`. Thêm thư mục `backend/tests/Feature/PosVenueInventory/`, mỗi file một requirement:

- `ShiftVenueScopeTest`
- `VenueStockBalanceTest`
- `VenueStockHoldTest`
- `StockTransferTest`
- `InventoryVisibilityTest`
- `StockCutoverMigrationTest`

Chạy bằng `.claude/plans/conformance/phpunit-run.sh` trên DB `pos_venue_inventory_test`. Không thêm vào CI, theo quyết định ngày 2026-10-22.

Frontend: một spec Vitest cho `mapPosShift` và một cho modal điều chuyển.

## Risks / Trade-offs

- **Prod có tổ chức có tồn nhưng không có cơ sở**, migration sẽ dừng. → Trước khi deploy, đọc prod (chỉ đọc) bằng truy vấn ở task 6.3. Có thì hỏi người dùng cách xử lý.
- **Frontend và backend lệch phiên bản**, vì push `main` deploy frontend ngay còn backend phải chạy tay.
  - Frontend mới chạy với backend cũ: `venue_id` bị bỏ qua, màn kho vẫn chạy với số của cả tổ chức.
  - Frontend cũ chạy với backend mới: điều chỉnh tồn bị 422 vì thiếu `venue_id`.
  - → Hẹn giờ với người dùng, chạy pipeline backend ngay sau khi push. Pha 1 không đổi hợp đồng API nên ship riêng trước được.
- **Dồn tồn vào một cơ sở** làm số liệu cơ sở khác bằng 0 sau cutover. → Ghi rõ trong ghi chú phát hành. Owner điều chuyển lại. Demo prod được seed lại theo cơ sở (task 6.1).
- **Deadlock khi nhiều đơn khoá chéo sản phẩm.** → Khoá theo `product_id` tăng dần (D4). Một test đồng thời ở `VenueStockHoldTest`.
- **Trả hàng của đơn cũ không có cơ sở** không nhập lại kho được. → Thông báo 422 rõ ràng, vẫn trả tiền được với `restock = false`.
- **Owner có nhiều cơ sở phải chọn cơ sở khi mở ca.** Đây là thay đổi hành vi. → Modal đã có ô chọn. Thông báo lỗi tiếng Việt: "Vui lòng chọn cơ sở bạn được giao."

## Migration Plan

1. **Pha 1** (ca theo cơ sở): nhánh riêng, merge `--no-ff`, push, chạy pipeline backend theo giờ đã hẹn.
2. **Pha 2–5**: một nhánh, gate đủ theo `local-test-gates` (PHPStan 0/5, architecture, Pint, oxlint, eslint, format, type-check, Vitest, build).
3. **Trước khi deploy**:
   - Đọc prod (chỉ đọc) theo task 6.3.
   - Backup prod `booking` theo quy trình reseed.
4. **Deploy**: push `main` rồi chạy `glab ci run -b main`. Migration chạy trong lúc deploy.
5. **Rollback**: `php artisan migrate:rollback --step=1` trả về tồn của cả tổ chức (D6). Revert commit frontend.
6. **Nếu người dùng muốn seed lại demo**: chạy reseed với seeder mới (task 6.1).
