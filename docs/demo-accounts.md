# Tài khoản và dữ liệu demo

`php artisan migrate:fresh --seed` tạo bộ dữ liệu trình diễn: hai tổ chức đối tác, đủ tài khoản cho mọi vai trò và dữ liệu
cho từng phân hệ. Ngày giờ tính theo thời điểm seed (múi giờ Asia/Ho_Chi_Minh): lịch sắp tới, giải đang diễn ra và hạn xử lý
khiếu nại sẽ trôi theo thời gian.

## Thời điểm seed

- Seed sau 07:00, trong vòng 1-2 ngày trước buổi thuyết trình. Seed trước 07:00 thì ca thu ngân đang mở bắt đầu từ 05:30.
  Sau 6-8 ngày, ba lượt giữ chỗ chờ thanh toán (BK-066, BK-067, BK-069) hết hạn; sau 13 ngày, bốn hợp đồng định kỳ đổi
  trạng thái.
- Hoạt động của người dùng (đặt sân, thanh toán, bài đăng, kết bạn, bình luận) chỉ rơi vào giờ thức, không có mốc nào lúc
  nửa đêm. Trước 06:00 chỉ có job đêm (đối soát 01:00, hết hạn điểm 00:30) và check-in cho ca 06:00.
  `DatabaseSeederTest` giữ quy tắc này.

## Mật khẩu

Tài liệu này không ghi mật khẩu (theo quy ước trong README). Seeder đọc mật khẩu từ biến môi trường lúc chạy:

| Biến | Áp dụng cho |
|---|---|
| `SEED_ADMIN_PASSWORD` | `admin@auracourt.vn`, `SEED_ADMIN_EMAIL` (mặc định `admin@example.com`) |
| `SEED_DEMO_ADMIN_PASSWORD` | `SEED_DEMO_ADMIN_EMAIL` (mặc định `demo-20260811@demo.auracourt.vn`) |
| `SEED_DEMO_PASSWORD` | `user@example.com` và mọi tài khoản `@demo.auracourt.vn` |

Mã nguồn không có mật khẩu mặc định. Khi `APP_ENV` khác `local`/`testing`, seed dừng với lỗi nếu thiếu một trong ba biến;
ở local, để trống thì tài khoản seed nhận mật khẩu ngẫu nhiên. Khi seed môi trường công khai, đặt giá trị riêng và gửi riêng
cho người thuyết trình.

## Tổ chức

| Tổ chức | Cơ sở | Ghi chú |
|---|---|---|
| Aura Sports Marketplace Club (`aura-badminton-club`) | Cầu Giấy (`aura-court`), Ba Đình, Tây Hồ, Nam Từ Liêm (Hà Nội), Bình Thạnh (TP.HCM) | 27 sân, 8 môn, gói PLATFORM_GROWTH |
| Hoàng Gia Sports (`hoang-gia-sports`) | Phú Mỹ Hưng, Quận 7 (TP.HCM) | 4 sân pickleball, tennis, bóng đá 5 người; gói PLATFORM_STARTER |

Các tính năng pilot (bán lại slot, chia tiền, payout, POS và ví, giải đấu và ghép trận, thanh toán trực tiếp cho chủ sân)
chỉ bật cho tổ chức có id trong `PILOT_ORGANIZATION_IDS`. Image production (CI `build_and_deploy`) đặt cohort là 1 và 2,
bật bán lại slot, chia tiền, POS và ví, giải đấu và ghép trận; payout và thanh toán trực tiếp cho chủ sân vẫn tắt.

## Tài khoản theo vai trò

| Email | Vai trò | Dùng để trình diễn |
|---|---|---|
| `admin@auracourt.vn` | Quản trị nền tảng, đồng thời là chủ Aura | Duyệt hồ sơ đối tác, gói nền tảng, mọi trang quản trị; thao tác được lưu thật |
| `admin@example.com` | Quản trị nền tảng | Như trên |
| `demo-20260811@demo.auracourt.vn` | Quản trị nền tảng (demo) | Xem trang quản trị; thao tác ghi chỉ mô phỏng (xem lưu ý) |
| `owner@demo.auracourt.vn` (Trần Quốc Bảo) | Chủ tổ chức Aura | Bảng điều khiển chủ sân, doanh thu, nhân sự, khuyến mãi |
| `manager@demo.auracourt.vn` (Nguyễn Hữu Thắng) | Quản lý mọi cơ sở Aura | Lịch sân, booking, khóa sân |
| `manager.caugiay@demo.auracourt.vn` (Đỗ Minh Khoa) | Quản lý cơ sở Cầu Giấy | Phân quyền theo cơ sở |
| `staff@demo.auracourt.vn` (Vũ Đức Thịnh) | Nhân viên Cầu Giấy | Check-in, lịch sân; không mở được ca POS |
| `cashier@demo.auracourt.vn` (Ngô Thị Lan) | Thu ngân Cầu Giấy | Ca POS, bán hàng, đối soát ca |
| `owner.hoanggia@demo.auracourt.vn` (Lâm Gia Huy) | Chủ Hoàng Gia Sports | Cách ly dữ liệu giữa hai tổ chức |
| `user@example.com` (Nguyễn Minh Anh) | Khách hàng chính | Lịch sắp tới, lịch sử, ví, hội viên Vàng, đánh giá, ghép trận, giải đấu, bạn bè, bảng tin, thông báo |
| `khach01` … `khach24@demo.auracourt.vn` | Khách hàng | Người chơi trong booking, ghép trận, giải đấu, cộng đồng |
| `referee@demo.auracourt.vn` (Hoàng Văn Trọng) | Khách hàng, trọng tài giải | Nhập tỷ số giải đấu |
| `newbie@demo.auracourt.vn` | Chưa hoàn tất onboarding | Luồng onboarding |
| `unverified@demo.auracourt.vn` | Chưa xác thực email | Luồng xác thực email |
| `restricted@demo.auracourt.vn` | Bị tạm khóa | Thông báo tài khoản bị hạn chế |
| `partner.draft@demo.auracourt.vn` | Hồ sơ đối tác nháp | Tiếp tục điền hồ sơ |
| `partner.pending@demo.auracourt.vn` | Hồ sơ chờ duyệt | Trang trạng thái hồ sơ; admin duyệt |
| `partner.revision@demo.auracourt.vn` | Hồ sơ cần bổ sung | Lịch sử duyệt và yêu cầu bổ sung |
| `partner.flagged@demo.auracourt.vn` | Hồ sơ bị gắn cờ trùng địa chỉ | Hàng đợi rà soát của admin |
| `newstaff@demo.auracourt.vn` | Lời mời nhân viên chưa chấp nhận | Không có tài khoản; lời mời hiển thị ở trang nhân sự |

## Lưu ý khi trình diễn

- Tài khoản `@demo.auracourt.vn`: thao tác ghi qua API quản trị v1 (`/api/v1/admin/*`) chỉ được mô phỏng. API trả về thành
  công nhưng giao dịch bị rollback, tải lại trang sẽ không thấy thay đổi. Cần thao tác lưu thật thì dùng `admin@auracourt.vn`.
- Thông báo trong ứng dụng được seed thẳng vào bảng `notifications`; seed không gửi email. Trên server có scheduler và
  queue chạy thật, job hết hạn giữ chỗ có thể gửi email tới `khach03`, `khach06`, `khach13@demo.auracourt.vn`.
- Lịch hoạt động: chọn một cơ sở để thấy giờ mở cửa 06:00-23:00. Chế độ "Tất cả cơ sở" hiện cả tuần "Tạm đóng".
- Ma trận giá: giá giờ vàng (17:00-22:00, +25%) và cuối tuần (+20%) nằm trong bảng giá theo giờ của từng sân (nút "Bảng
  giá"). Thẻ "Giờ cao điểm" và "Ngày lễ & ngoại lệ" chỉ đếm quy tắc giá riêng nên bằng 0.
- Chi trả: "Thực nhận" đã trừ tiền hoàn cho khách, nhưng thẻ "Khấu trừ & phí" chỉ cộng phí cổng và hoa hồng nên hiện 0 ₫.
- Ngoại lệ tài chính và hàng đợi xuất bản cơ sở để trống: đối soát khớp hết, không có cơ sở nào chờ duyệt.
