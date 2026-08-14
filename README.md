# Aura Court

Aura Court là monorepo đặt sân cầu lông gồm:

- `backend/`: Laravel 13 REST API, Sanctum, PostgreSQL.
- `frontend/`: Vue 3 + TypeScript SPA, Pinia và Vue Router.
- `devops/`: Docker image, GitLab CI và Portainer stack.

Đây là website demo. Dữ liệu và thanh toán chỉ phục vụ trình diễn; không chuyển tiền thật hoặc nhập thông tin nhạy cảm.

## Môi trường đang chạy

| Thành phần | Địa chỉ |
| --- | --- |
| Frontend | <https://court.ziet.dev> |
| API | <https://court-api.ziet.dev> |
| API health check | <https://court-api.ziet.dev/up> |
| API root | <https://court-api.ziet.dev/> |

API root trả JSON trạng thái, còn toàn bộ endpoint phiên bản hiện tại nằm dưới `/api/v1`.

## Tính năng chính

- Đăng ký, đăng nhập, đăng xuất, xác thực email và đổi mật khẩu.
- Quên mật khẩu và reset mật khẩu qua SMTP Brevo.
- Danh sách sân, chi tiết sân, giá theo thứ trong tuần và khung giờ.
- Kiểm tra lịch trống, giữ nhiều slot, đặt định kỳ, hủy một phần và đổi lịch.
- Thanh toán QR VietQR/SePay, webhook xác nhận và polling trạng thái thanh toán.
- Trang lịch sử đặt sân, hồ sơ và thông báo lỗi 404.
- Khu vực quản trị: CRUD sân, giá theo khung giờ, khóa sân, quản lý booking/thanh toán, hoàn tiền và báo cáo CSV.
- Danh sách chờ theo slot, thông báo email/in-app, coupon và membership giảm giá.
- Xuất lịch đặt sân dạng Google Calendar/iCal, đánh giá sân, dashboard occupancy theo giờ và audit log quản trị.
- Tài khoản demo quản trị được mô phỏng trong phiên; thao tác quản trị demo được rollback để không phá dữ liệu thật.

## Chạy backend local

Yêu cầu PHP 8.3+, Composer và PostgreSQL.

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
```

Điền thông tin PostgreSQL trong `backend/.env`, sau đó chạy:

```bash
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Backend local mặc định chạy tại `http://127.0.0.1:8000`.

## Chạy frontend local

```bash
cd frontend
npm ci
npm run dev
```

Frontend local chạy tại `http://localhost:5173`. Biến `VITE_API_URL` trong `.env.local` hoặc `.env.production` phải trỏ tới origin API, ví dụ:

```env
VITE_API_URL=http://127.0.0.1:8000
```

Frontend production hiện dùng `https://court-api.ziet.dev` làm fallback build-time.

## Tài khoản demo

Không công khai mật khẩu demo trên màn hình đăng nhập. Cấp tài khoản riêng bằng Artisan:

```bash
cd backend
php artisan demo:issue          # tài khoản khách hàng
php artisan demo:issue --admin  # tài khoản quản trị
```

Lệnh chỉ in thông tin một lần để gửi riêng cho người trải nghiệm. Không commit tài khoản hoặc mật khẩu vào Git.

## Cấu hình backend production

Các biến dưới đây được nhập trong Portainer Stack Environment hoặc file env riêng trên máy chủ. Không commit giá trị bí mật.

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://court-api.ziet.dev
FRONTEND_URL=https://court.ziet.dev
CORS_ALLOWED_ORIGINS=https://court.ziet.dev
SANCTUM_STATEFUL_DOMAINS=court.ziet.dev

SESSION_DRIVER=database
SESSION_DOMAIN=.ziet.dev
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
CACHE_STORE=database
QUEUE_CONNECTION=database

DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=booking
DB_USERNAME=...
DB_PASSWORD=...

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME="Aura Court"

SEPAY_ENABLED=true
SEPAY_BANK=VCB
SEPAY_ACCOUNT=...
SEPAY_API_KEY=...
SEPAY_HMAC_SECRET=...
```

`CORS_ALLOWED_ORIGINS` phải là origin thuần, không có dấu `/` cuối hoặc dấu `=`. Backend đã tự loại dấu `=` dư do lỗi copy/paste, nhưng vẫn nên nhập đúng như trên.

## Deploy API bằng GitLab và Portainer

Pipeline `.gitlab-ci.yml` build image `linux/amd64` và đẩy:

```text
registry.gitlab.com/bantool/ziet-projects/booking/api:latest
registry.gitlab.com/bantool/ziet-projects/booking/server:latest
```

`server:latest` là image runtime FrankenPHP/Caddy, lắng nghe port `8080`. Stack production nằm tại [`devops/portainer/booking_app.stack.yml`](devops/portainer/booking_app.stack.yml) và gồm:

- `court-server`: HTTP API.
- `booking-queue`: `php artisan queue:work`.
- `booking-scheduler`: `php artisan schedule:work`.

Redis không được sử dụng. Cache và queue dùng database. Scheduler tự xử lý booking đang giữ quá hạn mỗi phút.

Trong Portainer, nhập biến môi trường trực tiếp vào Stack Environment. Không trỏ `env_file` tới đường dẫn chỉ tồn tại trong repository nếu Portainer không mount thư mục đó.

Sau khi deploy image mới, chạy migration trong container API nếu có migration mới:

```bash
docker exec -w /app/booking-api court-server php artisan migrate --force
```

## Deploy frontend lên Cloudflare Workers

Worker frontend sử dụng `frontend/wrangler.jsonc` và entrypoint [`frontend/worker.ts`](frontend/worker.ts). Worker vẫn phục vụ toàn bộ thư mục `dist`, đồng thời cho phép dùng runtime Variables/Secrets.

Cloudflare Workers build settings:

- Root directory: `frontend`.
- Build command: `npm ci && npm run build`.
- Deploy command: `npx wrangler deploy`.
- Assets directory: `./dist`.

Thêm biến runtime dạng Plaintext trong Workers → Settings → Variables and secrets:

```env
PUBLIC_API_URL=https://court-api.ziet.dev
```

Endpoint `/runtime-config.json` chỉ trả public API origin. Không đưa API key, mật khẩu SMTP, SePay secret hoặc `APP_KEY` vào frontend Worker.

## Kiểm thử và kiểm tra chất lượng

Backend:

```bash
cd backend
php artisan test
vendor/bin/pint --test
```

Test backend cần PostgreSQL test database đúng với `phpunit.xml` (`booking_test`).

Frontend:

```bash
cd frontend
npm run type-check
npm run test:unit -- --run
npm run test:e2e
npm run build
npx wrangler deploy --dry-run
```

Kiểm tra nhanh production:

```bash
curl -fsS https://court-api.ziet.dev/
curl -fsS https://court.ziet.dev/runtime-config.json
```

Khi kiểm tra bằng Chrome DevTools, Network phải trả `200` cho frontend, `/runtime-config.json`, `/api/v1/courts` và `/api/v1/availability`; Console không được có lỗi CORS.

## Xử lý lỗi DNS trên máy có Tailscale

Nếu điện thoại mở được nhưng Chrome trên máy báo `ERR_NAME_NOT_RESOLVED`, thường là Tailscale đang nhận DNS cũ. Có thể tạm thời dùng DNS công cộng:

```bash
tailscale set --accept-dns=false
networksetup -setdnsservers Wi-Fi 1.1.1.1 8.8.8.8
dscacheutil -flushcache
```

Sau đó vào `chrome://net-internals/#dns` → **Clear host cache**, rồi `chrome://net-internals/#sockets` → **Flush socket pools**. Nếu cần khôi phục MagicDNS của Tailscale:

```bash
tailscale set --accept-dns=true
```

## Cấu trúc thư mục

```text
backend/                 Laravel API, migrations, seeders, PHPUnit
frontend/                Vue SPA, Worker entrypoint, assets
devops/build/            Dockerfile runtime server
devops/env/              Mẫu biến môi trường production
devops/portainer/        Portainer stack và mẫu env
.gitlab-ci.yml           Pipeline build/deploy API
docker-compose.yml       Compose local cũ dùng cho môi trường phát triển
```

## License

Dự án phục vụ demo nội bộ Aura Court.
