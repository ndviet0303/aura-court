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

Tất cả endpoint nằm dưới `/api` (không có số phiên bản):

- `POST /auth/register`, `POST /auth/login`, `POST /auth/logout`
- `POST /auth/forgot-password`, `POST /auth/reset-password`
- `GET /courts`, `GET /availability`
- `POST /price-quotes`, `POST /reservations` (giữ chỗ), `POST /reservations/checkout`, `GET /reservations/{id}`, `GET /me/reservations`
- `POST /recurring-series` cho đặt định kỳ
- `POST /webhooks/sepay`, `POST /webhooks/sepay/connections/{routingId}`
- Nhóm `/admin/*` cho sân, giá, khóa sân, đặt sân (`/admin/reservations`), thanh toán, refund và báo cáo

`GET /` trả trạng thái API dạng JSON. `GET /up` dùng cho health check.

## Tài liệu API (local)

Chỉ có khi chạy local (`APP_ENV=local`). Bản production cài `--no-dev` nên không có trang này; môi trường khác trả 403.

- Mở trang docs bằng đúng host trong `APP_URL` (mặc định `http://localhost:8000/docs/api`, Scalar có nút "Test Request"). OpenAPI JSON ở `/docs/api.json`.
- Tài liệu tự sinh từ route, FormRequest và API Resource bằng Scramble. Khi sinh, Scramble đọc cấu trúc bảng từ DB trong `.env`, nên DB đó phải truy cập được và đã migrate. Request vào `/docs/api*` tự nâng `memory_limit` lên 1G và bỏ giới hạn thời gian; mỗi lần mở trang sinh lại tài liệu (không cache), với DB ở xa mất khoảng một phút.

Gọi endpoint cần đăng nhập (API dùng session cookie, không có bearer token):

1. Gọi `GET /sanctum/csrf-cookie` trong trang docs. Scalar tự gắn header `X-XSRF-TOKEN` từ cookie vào mọi request, không cần dán tay.
2. Gọi `POST /api/auth/login` với tài khoản trong [`docs/demo-accounts.md`](../docs/demo-accounts.md) (mật khẩu đặt qua biến `SEED_*`). Các request sau dùng chung session.

Chưa đăng nhập thì endpoint cần auth trả 401. Gọi `POST` công khai khi chưa có cookie CSRF thì trả 419.

Lưu ý: request gửi từ trang docs là request thật vào DB mà `.env` đang trỏ tới. Ưu tiên tài khoản demo: với tài khoản demo, các thao tác ghi trong nhóm `/api/admin` được rollback; ngoài nhóm đó vẫn ghi thật.

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
