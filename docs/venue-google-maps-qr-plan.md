# Plan: Địa điểm, Google Maps và QR chia sẻ

## Tóm tắt

- Tạo mô hình nhiều địa điểm; mỗi sân bắt buộc thuộc một địa điểm.
- Admin nhập tên, địa chỉ và paste link Google Maps.
- Mỗi địa điểm có link Aura Court ổn định dạng `/venues/{slug}/directions`.
- Admin xem QR, copy link và tải QR PNG; QR dẫn tới trang Aura Court rồi user bấm mở Google Maps.
- Không lưu ảnh QR hoặc thống kê lượt quét trong v1.

## Thay đổi chính

### Backend và dữ liệu

- Tạo bảng `venues`: `name`, `slug` duy nhất, `address`, `google_maps_url`, `is_active`, timestamps.
- Thêm `courts.venue_id` với foreign key không nullable và `restrictOnDelete`; địa điểm chỉ được ngừng hoạt động, không xóa cứng.
- Migration tạo địa điểm mặc định `Aura Court`, gán toàn bộ sân hiện tại vào đó; địa chỉ và Maps URL được admin bổ sung sau.
- Thêm quan hệ `Venue hasMany Courts`, `Court belongsTo Venue`; cập nhật seeder để idempotently tạo venue mặc định và gán sân.
- Slug sinh phía server từ tên, tự thêm hậu tố khi trùng và không đổi khi admin sửa tên.
- Link chia sẻ được backend tính từ `FRONTEND_URL`, không phụ thuộc domain mà admin đang mở.

### API

- Public:
  - `GET /api/venues/{slug}` chỉ trả địa điểm đang hoạt động.
  - Court response bổ sung object `venue`.
- Admin:
  - `GET /api/admin/venues`
  - `POST /api/admin/venues`
  - `PATCH /api/admin/venues/{venue}`
  - `DELETE /api/admin/venues/{venue}` để chuyển `is_active=false`.
- Venue response gồm `id`, `name`, `slug`, `address`, `google_maps_url`, `is_active`, `share_url`.
- Tạo/cập nhật venue yêu cầu tên, địa chỉ và Google Maps URL.
- Chỉ chấp nhận HTTPS thuộc Google Maps: `google.com/.../maps`, các Google country domain có path Maps, `maps.app.goo.gl`, hoặc legacy `goo.gl/maps`; không resolve short URL ở backend.
- Court create/update nhận `venue_id`; booking và availability từ chối sân có venue không hoạt động.
- Public court listing ẩn toàn bộ sân thuộc venue đã ngừng hoạt động.

### Frontend

- Thêm type `Venue`; `Court` luôn chứa `venue`.
- Tạo trang admin `/admin/venues` riêng:
  - Danh sách địa điểm và trạng thái.
  - Form tạo/sửa tên, địa chỉ, link Google Maps.
  - Bật/tắt địa điểm.
  - Copy link chia sẻ.
  - Preview và tải QR PNG tên `{slug}-directions-qr.png`.
- Thêm dropdown địa điểm bắt buộc vào form tạo/sửa sân.
- Thêm public route `/venues/:slug/directions`:
  - Hiển thị tên, địa chỉ, nút copy địa chỉ và “Mở Google Maps”.
  - Google Maps mở tab mới với `noopener,noreferrer`.
  - Hiển thị 404 nếu venue không tồn tại hoặc đã ngừng hoạt động.
- Thêm địa chỉ và nút chỉ đường vào trang chi tiết sân.
- Dùng package frontend `qrcode`; QR 1024×1024, error correction mức H, mã hóa `share_url` do API trả về. Không lưu QR trong database/filesystem.
- Demo admin tiếp tục mô phỏng thay đổi cục bộ, không ghi dữ liệu thật.

## Kiểm thử và tiêu chí chấp nhận

- Migration gán mọi sân cũ vào venue mặc định và không để `venue_id` rỗng.
- Admin CRUD venue hoạt động; user thường bị từ chối; demo admin không làm thay đổi database.
- Tên venue trùng tạo slug duy nhất; đổi tên không làm đổi QR/link cũ.
- URL HTTP, domain ngoài Google hoặc URL Maps sai cấu trúc trả lỗi validation.
- Venue inactive trả 404 public, ẩn sân liên quan và không cho tạo booking mới.
- Trang chỉ đường hiển thị đúng dữ liệu và mở chính xác link Maps đã lưu.
- QR tải xuống quét được và luôn mở đúng `share_url` production.
- Court admin bắt buộc chọn venue; court detail hiển thị đúng địa chỉ.
- Chạy backend feature tests, frontend unit tests, type-check và production build.
- Kiểm tra thủ công trên mobile: copy link, quét QR, mở Google Maps, đổi Maps URL mà QR cũ vẫn sử dụng được.

## Giả định và triển khai

- V1 chỉ chia sẻ địa điểm; không đưa booking hoặc kèo mở vào link.
- Không geocode, không lưu latitude/longitude và không gọi Google Maps API.
- Không ghi scan event; analytics có thể bổ sung sau.
- `FRONTEND_URL` hiện có là nguồn chuẩn để tạo link QR.
- Triển khai backend migration/API trước, sau đó frontend; thay đổi API chỉ bổ sung field nên tương thích client cũ.
