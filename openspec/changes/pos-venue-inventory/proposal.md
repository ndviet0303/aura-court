## Why

Một tổ chức có nhiều cơ sở, nhưng POS chỉ giữ **một số tồn cho cả tổ chức**: `pos_products.stock_quantity`. Lịch sử kho (`stock_movements`) cũng không ghi cơ sở. Hệ quả:

- Cầu Giấy bán 1 chai thì tồn hiển thị ở mọi cơ sở khác cũng giảm.
- Cơ sở hết hàng vẫn bán được, vì hệ thống chỉ nhìn tổng của tổ chức.
- Không nhập kho, kiểm kê hay cảnh báo hết hàng riêng cho từng cơ sở được.
- Không có cách ghi nhận việc chở hàng từ cơ sở này sang cơ sở khác.

Ca thu ngân cũng có hai lỗi lộ phạm vi:

- `GET pos/shifts/active` trả về một ca đang mở **bất kỳ** trong tổ chức khi người gọi chưa mở ca của mình (`PosController::activeShift`). Màn POS khi đó hiện ca của người khác, ví dụ "Ca #12 (Ngô Thị Lan)", có thể của cơ sở khác.
- `GET pos/shifts` liệt kê ca của cả tổ chức. Staff thấy ca của cơ sở mình không được giao.
- API danh sách ca không trả cơ sở của ca. Màn "Quản lý Ca & Đối soát" (`useAdminShiftReconciliation::mapPosShift`) vì thế lấp mọi ca bằng cơ sở đầu tiên trong danh sách. Bộ lọc cơ sở của màn này do đó lọc sai.

POS không thuộc report module 01–06. Report module 06 ghi rõ "bán nước, cho thuê dụng cụ, bàn giao ca" nằm ngoài phạm vi. Vì vậy change này không có mã FR/BR để truy vết. Nguồn là yêu cầu của người dùng ngày 2026-10-23.

## What Changes

- **Ca chỉ thuộc về người mở và cơ sở của nó.**
  - `GET pos/shifts/active` chỉ trả ca đang mở của chính người gọi, không còn lấy ca của người khác.
  - `GET pos/shifts` chỉ trả ca ở các cơ sở người gọi được vận hành, và trả kèm cơ sở của từng ca.
  - Màn "Quản lý Ca & Đối soát" hiển thị và lọc theo cơ sở thật của ca. Ca cũ không có cơ sở được ghi là chưa gắn cơ sở.
  - **BREAKING**: mở ca bắt buộc có cơ sở, kể cả owner. Người được giao đúng một cơ sở vẫn được tự gán như hiện nay. Đơn POS cũng bắt buộc có cơ sở.
- **Tồn kho theo cơ sở.**
  - Bảng mới `venue_stocks (venue_id, product_id)` giữ số tồn. Danh mục, giá, VAT và ngưỡng tối thiểu vẫn chung cho tổ chức.
  - **BREAKING**: bỏ cột `pos_products.stock_quantity`. Trường `stock_quantity` trong API sản phẩm từ nay là tồn tại cơ sở được hỏi.
  - `stock_movements` có `venue_id` bắt buộc. Mọi biến động ghi đúng cơ sở: nhập, xuất, điều chỉnh, bán, trả hàng, tab dịch vụ, QR xác nhận muộn.
  - Giữ hàng cho đơn QR đang chờ và tab dịch vụ chỉ tính trong cơ sở của đơn hoặc tab đó.
  - Khi migrate, dồn tồn hiện có về một cơ sở của tổ chức theo quy tắc trong design.
- **Điều chuyển giữa cơ sở.**
  - Owner chuyển một số lượng hàng từ cơ sở A sang cơ sở B cùng tổ chức. Thao tác ghi một phiếu điều chuyển và hai biến động trong cùng một giao dịch.
  - Không cho chuyển quá số hàng còn bán được ở A, tức tồn trừ phần đang giữ.
- **Màn Kho & Hàng hoá** có chọn cơ sở. Số tồn, KPI, lịch sử và thao tác kho đều theo cơ sở đang chọn. Thêm thao tác "Điều chuyển".
- **Màn POS** hiện tồn của cơ sở theo ca đang mở.

## Capabilities

### New Capabilities

- `pos-venue-inventory`: tồn kho theo cơ sở, giữ hàng và trừ kho theo cơ sở của đơn/tab, điều chuyển giữa cơ sở, phạm vi đọc kho của owner và staff.
- `pos-shift-venue-scope`: ca và đơn POS bắt buộc gắn cơ sở, ca đang mở chỉ của người gọi, danh sách ca theo cơ sở được giao.

### Modified Capabilities

Không có. Main đã xoá toàn bộ `openspec/specs` ở `c52bb214`, nên không còn spec gốc để viết delta. Hai capability trên được viết mới hoàn toàn.

## Impact

- **Backend**
  - Migration:
    - Tạo `venue_stocks` và `stock_transfers`.
    - Thêm `stock_movements.venue_id`, backfill rồi đặt NOT NULL.
    - Backfill tồn, rồi xoá `pos_products.stock_quantity`.
  - Sửa:
    - `PosController`: products, adjustStock, movements, inventoryKpis, shifts, activeShift.
    - `PosService`: createProduct, adjustStock, completeOrder, refundOrder, openShift.
    - `PosQrPaymentService`: confirmPaidAttempt, venueIdFor, assertOperableVenue.
    - `ServiceTabService`: tồn còn bán được, addItem(s), settleTab.
    - Model `PosProduct`, `StockMovement`.
  - Thêm theo kiến trúc Controller → FormRequest → DTO → Action → Repository: điều chuyển kho và kho theo cơ sở.
  - Route mới: `POST pos/inventory/transfers`, `GET pos/inventory/transfers`.
- **Frontend**
  - `features/admin` inventory: api, types, `useAdminInventory`, `AdminInventoryView`, KPI, modal sản phẩm, modal kho, và một modal điều chuyển mới.
  - `features/pos`: tải lại sản phẩm sau khi mở ca để lấy tồn của cơ sở theo ca.
  - `useAdminShiftReconciliation`: bỏ việc lấp cơ sở đầu tiên cho ca.
- **Dữ liệu và demo**
  - Seeder `PosProductSeeder` và `PosSalesSeeder` ghi tồn theo cơ sở.
  - Lệnh `E2ESeedFixturesCommand` và `E2EArrangeTestDataCommand` bỏ `stock_quantity` trên sản phẩm.
  - Prod `booking` cần chạy migration. Trước khi deploy phải đọc prod (chỉ đọc) để biết tổ chức nào có tồn nhưng không có cơ sở.
- **Không đổi**
  - Thanh toán, ledger, loyalty.
  - Quy tắc QR theo connection của cơ sở.
  - Phân quyền route hiện có: chỉ owner ghi kho, owner và staff đọc.
