# Cầu Lông 24

Monorepo gồm Laravel 13 REST API và Vue 3 TypeScript SPA cho đặt sân cầu lông.

## Chạy local

```bash
cd backend
cp .env.example .env
php artisan key:generate
# Cấu hình DB_CONNECTION=pgsql, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD trong .env
php artisan migrate --seed
php artisan serve

cd ../frontend
npm install
npm run dev
```

Frontend dev server proxy `/api` và `/sanctum` tới `http://localhost:8000`.

## Cấp tài khoản trải nghiệm

Không hiển thị tài khoản demo công khai trên trang đăng nhập. Khi cần gửi quyền truy cập riêng, chạy:

```bash
cd backend
php artisan demo:issue                 # tài khoản khách hàng
php artisan demo:issue --admin          # tài khoản quản trị
```

Lệnh in email và mật khẩu một lần để gửi riêng cho người trải nghiệm. Các thao tác quản trị của tài khoản demo chỉ mô phỏng trong phiên và được backend bảo vệ bằng rollback giao dịch; đặt sân và thanh toán của người dùng vẫn ghi dữ liệu thật.

## Docker production (API)

```bash
cp .env.example .env
cp backend/.env.example backend/.env
# Đặt APP_KEY, thông tin PostgreSQL, SMTP reset password và SePay trong backend/.env
docker compose build
docker compose run --rm app php artisan key:generate --show
docker compose run --rm app php artisan migrate --seed --force
docker compose up -d app queue scheduler db
```

Docker Compose chỉ chạy Laravel API, queue, scheduler và PostgreSQL. Cache/session/queue dùng database; scheduler tự giải phóng booking quá hạn mỗi phút.

## Deploy frontend lên Cloudflare Pages

Kết nối repository GitLab này với Cloudflare Pages, chọn thư mục gốc `frontend`, sau đó cấu hình:

- Build command: `npm ci && npm run build`
- Output directory: `dist`
- Environment variable: `VITE_API_URL=https://api.example.com` (origin public của Laravel API)

Có thể dùng `frontend/wrangler.jsonc` để deploy trực tiếp bằng Wrangler. Trên backend production cần đặt `FRONTEND_URL`, `VITE_API_URL`, `CORS_ALLOWED_ORIGINS` và `SANCTUM_STATEFUL_DOMAINS` theo domain Cloudflare Pages. Nếu frontend và API khác site, bật `SESSION_SAME_SITE=none` cùng `SESSION_SECURE_COOKIE=true` trên HTTPS.
