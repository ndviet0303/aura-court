## 1. Infrastructure Drivers (Highest Impact, Zero Code Change)

- [x] 1.1 Đổi `SESSION_DRIVER=database` → `SESSION_DRIVER=file` trong `.env`
- [x] 1.2 Đổi `CACHE_STORE=database` → `CACHE_STORE=file` trong `.env`
- [x] 1.3 Chạy `php artisan config:clear` và restart `php artisan serve` để apply

## 2. Optimize `/auth/me` & WalletPaymentService

- [x] 2.1 Sửa `WalletPaymentService::getOrCreateWallet()` — wrap organization lookup bằng `Cache::remember('default_org_id', 3600, ...)` để cache organization ID thay vì chạy 2-3 queries mỗi request
- [x] 2.2 Sửa `/auth/me` route trong `routes/api.php` — eager load user relationships cần thiết, tránh lazy loading

## 3. Server-side Caching cho Static Data

- [x] 3.1 Thêm `Cache::remember()` vào `CourtController::index()` — cache kết quả courts list với TTL 5 phút, cache key kèm hash query params (sport_type, venue_id, sport_zone_id)
- [x] 3.2 Thêm `Cache::remember()` vào `SportZoneController::index()` — cache sport zones list với TTL 5 phút
- [x] 3.3 Thêm `Cache::remember()` vào `EloquentVenueRepository::getActiveVenuesWithCourts()` — cache venues list với TTL 5 phút
- [x] 3.4 Thêm cache invalidation (flush cache keys) tại các admin mutation endpoints: `CourtController::store/update/destroy`, `SportZoneController::store/update/destroy`, `AdminController::storeVenue/updateVenue/destroyVenue`

## 4. Trim Response Payloads

- [x] 4.1 Sửa `SportZoneController::index()` — loại bỏ `with('courts')`, chỉ giữ `withCount('courts')` cho listing view
- [x] 4.2 Kiểm tra frontend `sport-zones` usage — xác nhận không có component nào phụ thuộc vào nested `courts` data từ listing endpoint

## 5. Optimize Notification Queries

- [x] 5.1 Sửa `NotificationController::index()` — tối ưu bằng cách gộp count unread vào paginate query hoặc dùng subquery thay vì 2 queries riêng biệt

## 6. Verify & Measure

- [x] 6.1 Restart backend server và test lại tất cả endpoints chậm từ network tab
- [x] 6.2 So sánh response times trước/sau — target: mọi endpoint < 500ms
