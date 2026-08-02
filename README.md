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

## Docker production

```bash
cp .env.example .env
cp backend/.env.example backend/.env
# Đặt APP_KEY, thông tin PostgreSQL, SMTP reset password và SePay trong backend/.env
docker compose build
docker compose run --rm app php artisan key:generate --show
docker compose run --rm app php artisan migrate --seed --force
docker compose up -d
```

Đặt HTTPS ở reverse proxy của VPS hoặc bổ sung chứng chỉ vào service `web`. Scheduler tự giải phóng booking quá hạn mỗi phút; queue xử lý các tác vụ nền.
