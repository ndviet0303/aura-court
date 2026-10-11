## Why

Mọi API endpoint đều mất 3-10 giây để response, dù database chỉ chứa vài dòng seed data. Root cause: backend Laravel kết nối tới PostgreSQL trên VPS remote (`bt-server`) qua mạng, mỗi query mất ~500ms+ network roundtrip. Cộng thêm việc session, cache, và queue đều dùng `database` driver — tức mọi request phải roundtrip tới VPS nhiều lần chỉ cho infrastructure overhead trước khi chạy business logic.

Đây là blocking issue cho cả development lẫn UX. Load trang admin mất >20 giây là không chấp nhận được.

## What Changes

- **Chuyển session driver** từ `database` sang `file` — loại bỏ 1-2 DB roundtrips/request cho session verify
- **Chuyển cache driver** từ `database` sang `file` — cache dùng database driver trên remote DB hoàn toàn phản tác dụng
- **Tối ưu `/auth/me` endpoint** — loại bỏ chuỗi 3 queries Organization chỉ để lấy wallet balance, cache organization ID
- **Thêm Eager Loading optimization** — giảm số queries ở các endpoint `courts`, `sport-zones`, `venues`, `refunds`, `notifications`
- **Trim response payloads** — `sport-zones` trả 31 kB do load toàn bộ courts kèm theo (không cần thiết cho listing)
- **Thêm server-side caching cho static data** — courts, venues, sport-zones thay đổi rất ít, cache vài phút sẽ cắt giảm queries đáng kể
- **Tối ưu frontend request waterfall** — giảm số parallel requests khi load trang admin

## Capabilities

### New Capabilities

_(none — đây là performance optimization, không thêm capability mới)_

### Modified Capabilities

_(none — không thay đổi behavior hay requirement, chỉ tối ưu performance. Đây là pure infrastructure/refactor change → skip_specs)_

## Impact

- **Backend `.env`**: Thay đổi `SESSION_DRIVER`, `CACHE_STORE`
- **Backend Controllers**: `CourtController`, `SportZoneController`, `NotificationController`, `AdminController` (admin venues/refunds/coupons routes)
- **Backend Services**: `WalletPaymentService::getOrCreateWallet()` — optimize Organization lookup
- **Backend Routes**: `/auth/me` inline closure trong `api.php`
- **Frontend**: Có thể gộp/debounce một số API calls song song
- **Không breaking changes**: Response schema giữ nguyên, chỉ giảm data thừa ở một số endpoint listing
