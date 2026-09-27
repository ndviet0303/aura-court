# Aura Court API

Laravel 13 REST API cho đặt sân cầu lông, dùng Sanctum và PostgreSQL.

## Chạy local

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Đặt thông tin PostgreSQL trong `.env`. API local mặc định ở `http://127.0.0.1:8000`.

## Endpoint chính

Tất cả endpoint phiên bản hiện tại nằm dưới `/api/v1`:

- `POST /auth/register`, `POST /auth/login`, `POST /auth/logout`
- `POST /auth/forgot-password`, `POST /auth/reset-password`
- `GET /courts`, `GET /availability`
- `GET/POST /bookings`, đặt định kỳ, hủy, đổi lịch và trạng thái thanh toán
- `POST /webhooks/sepay`
- Nhóm `/admin/*` cho sân, giá, khóa sân, booking, thanh toán, refund và báo cáo

`GET /` trả trạng thái API dạng JSON. `GET /up` dùng cho health check.

## Cấu hình production

Các biến quan trọng:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://court-api.ziet.dev
FRONTEND_URL=https://court.ziet.dev
CORS_ALLOWED_ORIGINS=https://court.ziet.dev
SANCTUM_STATEFUL_DOMAINS=court.ziet.dev
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
CACHE_STORE=database
QUEUE_CONNECTION=sync
```

SMTP Brevo dùng cho email reset mật khẩu. SePay dùng cho webhook và trạng thái thanh toán. Không commit secret vào repository.

## Thực thi tác vụ và lập lịch (Worker-Free Architecture)

Hệ thống hoạt động theo mô hình **không cần container worker hay scheduler riêng biệt**:
- `QUEUE_CONNECTION=sync`: Các tác vụ được thực thi synchronous ngay trong tiến trình gọi (`after_commit = true` đảm bảo chạy sau khi commit database transaction gốc).
- **Lập lịch qua host `crond`**: Máy chủ host gọi `php artisan schedule:run` mỗi phút vào container `court-server` để thực thi các tác vụ định kỳ và phục hồi thất bại thứ cấp (`recoverOutbox`, `recoverProviderEvents` và `recoverOwnerPaymentReceipts`).
- Tác vụ realtime chạy qua service `booking-realtime` (`php artisan realtime:work`).
- Chi tiết triển khai và runbook vận hành xem tại [`docs/operations/host-crond-scheduling-runbook.md`](../docs/operations/host-crond-scheduling-runbook.md).

## Tài khoản demo và test

```bash
php artisan demo:issue
php artisan demo:issue --admin
php artisan test
vendor/bin/pint --test
```

Tài khoản demo quản trị được rollback các thay đổi quản trị trong phiên. Test cần PostgreSQL test database `booking_test` theo `phpunit.xml`.
