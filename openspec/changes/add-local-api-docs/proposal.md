## Why

Backend có khoảng 349 route trong `backend/routes/api.php` nhưng không có tài liệu API nào để xem và gọi thử: không có OpenAPI, Postman/Bruno collection, file `.http` hay route docs. `GET /` còn quảng cáo `'docs' => '/api'` (`backend/routes/web.php:10`) nhưng `/api` không có route, nên đó là link chết. Người dùng hỏi có công cụ kiểu Scalar không và đã duyệt thêm `dedoc/scramble` (require-dev), dùng renderer Scalar.

## What Changes

- Thêm `dedoc/scramble` vào `require-dev` của `backend/composer.json`. Nó sinh tài liệu OpenAPI từ route, FormRequest và API Resource sẵn có.
- Trang docs ở `/docs/api` dùng renderer Scalar có nút "try it". Spec JSON ở `/docs/api.json`. Phạm vi là mọi route dưới `/api`, kể cả nhóm `/api/v1`.
- Chỉ mở trên máy local:
  - Ảnh production cài `--no-dev`, nên route docs không tồn tại ở đó.
  - Quyền vào docs dùng middleware mặc định của Scramble: chỉ cho qua khi `APP_ENV=local`. Không định nghĩa gate `viewApiDocs`.
  - Một architecture test khoá cả ba điều kiện này.
- Request "try it" đi thẳng tới API, không qua proxy ngoài của Scalar. Bản Scalar CDN được ghim cố định.
- Gọi thử endpoint có `auth:sanctum` bằng session cookie, vì app không phát bearer token. Tài liệu có một ô nhập `X-XSRF-TOKEN` dùng chung cho mọi request. `backend/README.md` có hướng dẫn đăng nhập trong docs.
- `GET /` chỉ trả `docs: "/docs/api"` khi `APP_ENV=local`. Môi trường khác bỏ hẳn key `docs`.
- `backend/.env.example` thêm `localhost:8000,127.0.0.1:8000` vào `SANCTUM_STATEFUL_DOMAINS`, để docs chạy bằng `php artisan serve` được tính là stateful.

Ngoài phạm vi:
- Laravel Telescope: người dùng đã bỏ. Nó cần bảng trong DB mà `.env` local đang trỏ vào DB demo `booking`.
- Mở docs trên bt-server hay staging: cần gate và quyết định của người dùng.
- Viết tay mô tả, ví dụ hay annotation cho từng endpoint. Dùng đúng những gì Scramble tự suy ra; chỗ nào suy sai thì sửa sau khi có người cần.
- Xuất file OpenAPI tĩnh hay Postman collection.
- Phát bearer token cho người test.

## Capabilities

### New Capabilities
- `api-docs`: tài liệu API có "try it", chỉ mở ở local, và trỏ tới từ `GET /`.

### Modified Capabilities

(không có)

## Impact

- Code: `backend/composer.json`, `backend/composer.lock`, `backend/config/scramble.php` (mới, publish từ package), `backend/app/Providers/AppServiceProvider.php`, `backend/routes/web.php`, `backend/.env.example`, `backend/README.md`, `backend/tests/Unit/Architecture/ApiDocsExposureTest.php` (mới).
- Route mới, chỉ có ở local: `GET /docs/api`, `GET /docs/api.json`. Route API hiện có không đổi.
- Dữ liệu: không có migration. Khi sinh tài liệu, Scramble có thể đọc schema bảng từ DB mà `.env` trỏ tới (chỉ đọc).
- Production: ảnh `backend/Dockerfile:22` cài `--no-dev`, và `devops/build/booking.prod.Dockerfile` kế thừa ảnh đó, nên prod không có package hay route docs. Chỉ thay đổi ở `GET /`: prod không còn key `docs`.
- Rủi ro: request "try it" là request thật. Với `.env` local hiện tại, chúng ghi vào DB `booking` trên bt-server (xem design.md, phần Risks).
