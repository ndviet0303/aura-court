## Why

Ngày 2026-10-11, rà schema PostgreSQL 18.6 trên `bt-server` (124 bảng, khoảng 301 FK, 418 index). Phần nền đã ổn: mọi bảng có PK `bigint`, 91 cột tiền đều là `bigint` VND, sổ cái `journals`/`journal_entries` có trigger chặn sửa/xoá. Nhưng DB gần như không tự bảo vệ dữ liệu. Mọi ràng buộc nghiệp vụ đều nằm trong PHP:

- **Chống trùng lịch.**
  - Unique index chỉ bắt `starts_at` trùng tuyệt đối. Trong khi đó DB có cả slot 1 giờ lẫn 2 giờ, và lưới giờ khác nhau theo sport zone.
  - Lớp chặn thật nằm ở code: khoá dòng `courts` rồi query chồng lấn trong `SlotSelectionPolicy`. Writer nào bỏ qua khoá đó (ví dụ backfill legacy) là lọt.
  - Spec đã yêu cầu so theo khoảng thời gian chứ không so giờ bắt đầu, nhưng DB chưa bảo đảm điều đó.
- **Status.** Có 81 cột status mà 0 CHECK constraint. Code ghi khoảng 272 chuỗi status trực tiếp, nên gõ sai một chữ là dữ liệu sai lặng lẽ.
- **CASCADE.**
  - 45 FK `ON DELETE CASCADE` trỏ vào bảng tiền/audit. Xoá một organization hay user sẽ xoá luôn POS, ví, điểm, subscription, nghĩa vụ hoàn tiền…
  - Riêng `journals` khai báo CASCADE nhưng trigger append-only lại chặn. Hai thứ mâu thuẫn nhau.
- **Index.** 189/301 FK không có index. Postgres không tự tạo index cho FK, nên mỗi lần join hay kiểm tra khi xoá đều phải quét tuần tự.
- **Bảng chết.**
  - `reserves`: chỉ còn model, không chỗ nào đọc hay ghi.
  - `memberships`/`membership_plans`: chỉ 3 route admin v1 không có caller ghi vào, còn logic giảm giá đọc `tenant_memberships`. Cả ba bảng đều 0 dòng.

Data hiện còn nhỏ và chưa có vi phạm nào: 0 cặp slot chồng nhau, 0 slot active nằm dưới reservation đã kết thúc. Siết bây giờ rẻ hơn nhiều so với sau này.

## What Changes

- **Exclusion constraint cho slot.**
  - Slot `held`/`confirmed` của cùng một sân không được chồng khoảng thời gian `[starts_at, ends_at)`.
  - Không cần ngoại lệ cho dời lịch: `PriceQuoteService` đã từ chối lịch thay thế chồng lên lịch gốc, nên hai slot active của một lần dời lịch không bao giờ chồng nhau.
  - Khi vi phạm, API trả 409 với thông báo như hiện nay.
- **CHECK constraint cho status.** Thêm cho 72 cột status. Tập giá trị hợp lệ lấy từ code, đối chiếu với data. Không áp cho mã HTTP, bản sao status legacy, status nhận nguyên văn từ provider, cột text tự do của KYC và các bảng sắp xoá.
- **RESTRICT thay cho CASCADE.** Đổi 45 FK CASCADE trỏ vào bảng tiền/audit sang `ON DELETE RESTRICT`.
- **Index cho FK.** Thêm index cho mọi FK chưa có index nào phủ cột đầu.
- **Xoá bảng chết.** Xoá `reserves`, `memberships`, `membership_plans` và model của chúng. Migration từ chối chạy nếu bảng còn dữ liệu.
  - **BREAKING:** bỏ `GET/POST api/v1/admin/membership-plans` và `POST api/v1/admin/memberships`. Frontend admin dùng route v2 (`tenant_membership_plans`), và không tìm thấy caller nào của 3 route này.
- **Test schema conformance** (PHPUnit trên PostgreSQL). Test fail khi có FK thiếu index, có cột status thiếu CHECK, hoặc có FK CASCADE trỏ vào bảng tài chính. Mục đích là giữ schema sạch sau khi dọn.

## Capabilities

### New Capabilities

Không có.

### Modified Capabilities

- `reservations-and-pricing`: yêu cầu "Active inventory exclusivity" thêm điều kiện: DB phải tự từ chối slot active chồng khoảng thời gian, kể cả khi writer bỏ qua kiểm tra của ứng dụng.
- `financial-ledger-and-payouts`: thêm yêu cầu không được xoá chủ sở hữu (organization, user, đơn POS…) khi còn hồ sơ tài chính phụ thuộc. Lịch sử tài chính không bao giờ bị xoá dây chuyền.
- `platform-readiness`: thêm cổng schema integrity: status phải nằm trong tập hợp lệ, FK phải có index, không CASCADE vào bảng tài chính, và một test tự động kiểm tra cả ba.

## Impact

- **Migration:** khoảng 6 file mới. Cần extension `btree_gist`: trusted từ PG13, chỉ cần quyền CREATE trên database. Role `ziet_dev` trên bt-server đủ quyền; production phải kiểm tra trước khi deploy.
- **Backend:**
  - 4 chỗ bắt `QueryException` (`OrdinaryCheckoutService` ×2, `ReservationService`, `RescheduleService`) map thêm tên constraint mới sang 409.
  - Xoá `Reserve`, `Membership`, `MembershipPlan`, quan hệ `User::memberships()`, và 3 method của `PromotionController`.
  - Seeder, test và e2e fixture ghi status trái với từ vựng của code được sửa tại chỗ ghi.
- **Test:** thêm test chồng lịch khác lưới giờ, test dời lịch giữ cả lịch gốc lẫn lịch thay thế, và test schema conformance. `ReconcileLegacyPartnersCommandTest` đang xoá toàn bộ organization nên có thể phải sửa.
- **Không đổi:** response của các API còn lại. Mọi migration đều có `down()`.
- **Data:** bt-server có dòng `booking_slots.status = 'booked'`, phải sửa thành `confirmed` bằng một bước data fix riêng có review trước khi chạy migration CHECK.
- **Non-goals:**
  - Tắt V1 booking: đã tách thành change `retire-v1-booking`.
  - Đổi `json` sang `jsonb`, `timestamp` sang `timestamptz`: phải sửa rất nhiều chỗ mà chưa có lỗi thực tế nào cần đến.
  - Chuyển chuỗi status sang PHP enum: CHECK trên DB đã chặn giá trị sai ngay lúc ghi.
  - Unique index `reservation_entitlements_active_unique` đang chặn việc dời lịch có cùng giờ bắt đầu với chính lịch cũ. Đây là bất nhất có sẵn từ trước, ghi lại để xử lý riêng.
  - Việc vận hành trên bt-server: 1 migration chưa chạy, 922 `failed_jobs` do Redis từ chối kết nối, cache `courts:vN` hết hạn không được dọn.
