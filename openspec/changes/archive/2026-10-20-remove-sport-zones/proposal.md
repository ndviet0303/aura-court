## Why

Form "Thêm sân mới" bắt chủ sân chọn "Khu vực thể thao (Sport Zone)", một tầng trung gian giữa Cơ sở và Sân mà chủ sân không dùng tới. Chủ sân nghĩ theo mô hình: một chủ quản lý nhiều sân, mỗi sân thuộc một Cơ sở có địa chỉ để khách tra cứu. Hiện tại môn thể thao, độ dài khung giờ và một mức giá đều nằm ở zone, nên không thể giấu ô này mà không giữ lại cả tầng zone. Quy ước dự án cấm shim, vì vậy bỏ hẳn zone thay vì giấu trên giao diện.

## What Changes

- Mô hình mới: Organization (chủ) → Venue/Cơ sở (địa chỉ, `google_maps_url`, toạ độ) → Court. Không còn tầng zone.
- Mỗi sân tự mang `sport_type` (bắt buộc, một trong 8 môn hiện có) và `slot_duration` (mặc định 60 phút). Migration chép hai giá trị này từ zone của từng sân.
- Rule giá đang theo zone chuyển sang phạm vi **Cơ sở + môn** (`venue_id` + `sport_type`). Thứ tự ưu tiên giữ nguyên: sân > cơ sở+môn > cơ sở > tổ chức. Migration không làm đổi giá của sân nào; nếu dữ liệu không cho phép giữ nguyên, migration dừng và rollback.
- **BREAKING** Bỏ endpoint `GET /api/sport-zones`, `GET /api/sport-zones/{id}` và `/api/admin/sport-zones` (CRUD). Các endpoint này trả 404.
- **BREAKING** Payload sân bỏ `sport_zone_id` và `sport_zone`, thêm `sport_type` và `slot_duration`. Payload rule giá bỏ `sport_zone_id`, thêm `sport_type`. Tham số lọc `sport_zone_id` bị bỏ khỏi `/courts`, `/admin/courts` và `/admin/price-rules`.
- Bộ lọc theo môn (`/courts?sport_type=`, `/venues?sport_type=`, `/admin/courts?sport_type=`, trang chủ) lọc theo `courts.sport_type`.
- Tạo Cơ sở mới không còn tự sinh zone "Khu Cầu Lông".
- Admin:
  - Form sân thay ô "Khu vực thể thao" bằng ô "Môn thể thao". Cơ sở vẫn chọn ngầm như commit `679ccc90` (cơ sở đang lọc, hoặc cơ sở đang hoạt động đầu tiên).
  - Modal rule giá thay ô zone bằng ô "Môn thể thao". Ô này chỉ bật khi đã chọn Cơ sở.
  - Thẻ Cơ sở hiện các môn có ở cơ sở đó thay cho tên zone.
- Phía khách: thẻ sân, bộ chọn sân ở trang lịch, lưới sân ở trang chỉ đường, icon, ảnh mặc định và lịch sử đặt sân lấy môn từ `court.sport_type`. Tên zone được thay bằng tên môn theo ngôn ngữ đang chọn.
- Bỏ bảng `sport_zones`, cột `courts.sport_zone_id` và cột `price_rules.sport_zone_id`. Thứ tự hai bước: thêm và backfill cột mới trước khi deploy, xoá zone sau khi deploy.

## Capabilities

### New Capabilities

Không có.

### Modified Capabilities

- `venue-court-operations`:
  - Hợp đồng catalog công khai không còn danh sách và chi tiết sport-zone.
  - Thêm yêu cầu: mỗi sân tự mang môn và độ dài khung giờ, và lọc theo môn dựa vào sân.
- `reservations-and-pricing`: phạm vi rule giá đổi từ tổ chức/cơ sở/zone/sân sang tổ chức/cơ sở/cơ sở+môn/sân, kèm thứ tự ưu tiên.
- `localization/database-i18n`: bỏ Sport Zones khỏi ví dụ thực thể có nội dung đa ngôn ngữ.

## Impact

- **Backend.** Khoảng 18 file trong `backend/app`:
  - Model: `Court`, `Venue`, `PriceRule`; xoá `SportZone`.
  - Controller: xoá `SportZoneController`; sửa `CourtController`, `AvailabilityController`, `PriceRuleController`, `AdminController`.
  - Action và repository: `CreateVenue`, `UpdateVenue`, `EloquentVenueRepository`, `EloquentVenueLifecycleRepository`.
  - Service: `PriceQuoteService`, `CourtPriceRuleService`, `SlotSelectionPolicy`, `RecurringSeriesService`, `LoyaltyPolicyService`.
  - Route `sport-zones` trong `routes/api.php`.
  - Hai migration mới. Migration cũ `2026_08_28_000003` chuyển sang `DB::table` vì model `SportZone` không còn.
  - 8 seeder: `SportZoneCourtSeeder` đổi tên thành `CourtSeeder`.
  - 17 test hiện có, cộng test mới.
- **Frontend.** Khoảng 26 file trong `frontend/src`:
  - Admin: `CourtFormModal`, `useAdminManageCourts`, `PricingRuleModal`, `PricingCalendarGrid`, `usePricingCalendar`, `VenueCard`, `useAdminVenues`, `AdminCourtsTable`, `adminApi`.
  - Phía khách: `courtApi`, các type, thẻ sân, bộ chọn sân, trang chỉ đường, trang chủ, `courtImage`.
  - i18n `vi.ts`/`en.ts` và các spec liên quan.
- **Prod.** Hai migration chạy tay trên DB `booking` của bt-server. Bước hai xoá bảng nên phải `pg_dump` trước, và người dùng chốt thời điểm. Xem design.md, mục Migration Plan.
- **Change đang mở `direct-owner-cutover`.** Delta "One-hour booking slots" của change này còn nhắc zone. Sửa câu chữ sang "sân", không đổi ý nghĩa.
- **Báo cáo `dacn_report.docx`.** Mô hình dữ liệu trong báo cáo có thể còn nhắc tới khu vực thể thao. Sửa báo cáo nằm ngoài phạm vi change này; người dùng tự cập nhật nếu cần.
- **Không đổi:** Google Maps, chỉ đường (`/courts/:slug/directions`), tìm theo khu vực và vị trí, chính sách đặt sân, lịch hoạt động.
