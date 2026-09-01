## Context

See [proposal.md](proposal.md) for motivation. Current state:

- **Database**: PostgreSQL trên VPS remote (`bt-server`), ~500ms+ latency/query
- **Session**: `SESSION_DRIVER=database` — mỗi request authenticate qua Sanctum đều roundtrip DB để verify session
- **Cache**: `CACHE_STORE=database` — cache layer đang dùng chính cái remote DB → cache chậm hơn không cache
- **Redis config**: Đã cấu hình sẵn trong `config/database.php` nhưng không dùng. User xác nhận không cài Redis trên máy dev
- **Data size**: Rất nhỏ (vài dòng seed) — tức bottleneck 100% là network latency, không phải query complexity
- **`.env.example`** ghi `CACHE_STORE=redis` (intended config) nhưng `.env` đang ghi `database`

## Goals / Non-Goals

**Goals:**
- Giảm response time từ 3-10s xuống <500ms cho mọi endpoint thông thường
- Loại bỏ unnecessary DB roundtrips cho session/cache infrastructure
- Giảm tổng số queries/request qua eager loading optimization
- Cache data ít đổi (courts, venues, sport-zones) server-side
- Không thay đổi response schema — frontend không cần sửa logic xử lý data

**Non-Goals:**
- Cài Redis trên máy dev (user xác nhận không muốn)
- Migrate database về local (bt-server là môi trường shared)
- Tối ưu database schema/indexes (data nhỏ, query plan không phải vấn đề)
- Connection pooling (PgBouncer) — overkill cho dev environment
- Chuyển đổi database engine

## Decisions

### D1: Session driver → `file`

**Chọn**: `file` driver  
**Alternatives**: `cookie`, `array`, `redis`  
**Rationale**: 
- `file` lưu session local, zero network overhead, hoạt động out-of-the-box
- `cookie` có size limitation và security concerns (session data lộ client-side)
- `redis` yêu cầu cài thêm service — user không muốn
- `array` chỉ tồn tại trong memory của process, không persist giữa requests
- **Impact**: Mỗi request giảm 1-2 DB roundtrips (~1 giây)

### D2: Cache driver → `file`

**Chọn**: `file` driver  
**Alternatives**: `array`, `redis`  
**Rationale**:
- `file` driver persist giữa requests, hoạt động ngay
- `array` mất cache khi process restart — không dùng được cho `php artisan serve` multi-request
- Với `file` cache, các static data queries sẽ được cache local thay vì roundtrip DB
- **Impact**: Cache thực sự hoạt động như cache — local read thay vì remote DB read

### D3: Optimize `/auth/me` — inline wallet lookup

**Chọn**: Cache organization ID bằng `Cache::remember()`, simplify query chain  
**Hiện tại**: `getOrCreateWallet()` chạy tới 3 queries `Organization::where()` mỗi lần gọi  
**Cách fix**:
```
Trước:  Organization::where('active')->whereHas('venues')->first()
           ?: Organization::where('active')->first()
           ?: Organization::first()
        → 2-3 queries × 500ms = 1-1.5s

Sau:    Cache::remember('default_org_id', 3600, fn() => ...lookup...)
        → 0 queries (cache hit) hoặc 1 query (cache miss, 1 lần/giờ)
```

### D4: Server-side caching cho static-ish data

**Chọn**: `Cache::remember()` với TTL 5 phút cho courts/venues/sport-zones  
**Rationale**:
- Data này thay đổi cực kỳ ít (admin tạo/sửa sân rồi ít đụng)
- Cache invalidation: flush cache key khi admin create/update/delete qua controller
- TTL 5 phút đảm bảo worst case data stale 5 phút — acceptable cho listing
- **Cache keys**: `courts:list`, `venues:active`, `sport-zones:list` (kèm query params hash nếu có filter)

### D5: Trim `sport-zones` response

**Chọn**: Loại bỏ `with('courts')` ở `SportZoneController::index()`  
**Hiện tại**: Load toàn bộ courts kèm sport zones → 31 kB response  
**Cách fix**: Chỉ giữ `withCount('courts')` cho listing, load courts detail khi `show()`  
**Impact**: Response size giảm từ ~31 kB xuống ~5 kB, bớt 1 query

### D6: Optimize `NotificationController::index()`

**Chọn**: Gộp `unreadNotifications()->count()` vào 1 query  
**Hiện tại**: 2 queries riêng biệt (1 count, 1 paginate)  
**Cách fix**: Dùng `$request->user()->notifications()->latest()->paginate(30)` rồi đếm unread từ collection, hoặc dùng `withCount` trên user model

### D7: Không tối ưu frontend waterfall trong phase này

**Chọn**: Tập trung backend trước  
**Rationale**:
- Backend optimization sẽ giảm latency từ ~500ms/query → gần 0ms (cache hit)
- Dù frontend fire 12 parallel requests, mỗi request nhanh thì tổng thời gian vẫn OK
- Sửa frontend waterfall là scope riêng, phức tạp hơn, risk cao hơn

## Risks / Trade-offs

- **Stale cache risk** → Mitigation: TTL 5 phút + flush cache khi admin thay đổi. Worst case: admin tạo sân mới, user thấy sau 5 phút. Acceptable cho booking platform
- **File-based session scalability** → Mitigation: Đây là dev environment. Production nên dùng Redis. File session đủ tốt cho dev/staging
- **File cache performance ceiling** → Mitigation: File cache nhanh hơn nhiều so với remote DB cache. Nếu cần tốt hơn, chuyển Redis sau
- **Cache invalidation bugs** → Mitigation: Flush cache ở controller level (create/update/delete). Nếu miss, TTL tự expire sau 5 phút
