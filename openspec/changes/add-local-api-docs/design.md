## Context

Lý do làm: xem proposal.md, phần Why. Các sự thật dưới đây quyết định cách làm.

- API:
  - Mọi route nằm dưới prefix mặc định `api` (`backend/bootstrap/app.php:13-18`), không có số phiên bản (`backend/README.md`).
  - Riêng callback OAuth và SePay giữ nhóm `v1` (`backend/routes/api.php:151-152`).
- Auth chỉ dùng session:
  - `statefulApi()` (`backend/bootstrap/app.php:31`). Guard Sanctum là `['web']` (`backend/config/sanctum.php:45`).
  - `login` gọi `Auth::attempt` rồi trả `['user' => ...]`, không tạo token (`backend/app/Http/Controllers/Auth/AuthController.php:55-79`).
  - Trong `app/` không có `createToken`.
  - Vì vậy request ghi cần cookie session và header `X-XSRF-TOKEN`.
- Stateful domains:
  - `.env` local đã có `localhost:8000` và `127.0.0.1:8000`.
  - `backend/.env.example:73` thì chưa có, chỉ có cổng 5173. Sanctum so cả host lẫn cổng của Origin/Referer.
- Production:
  - `backend/Dockerfile:22` chạy `composer install --no-dev`, sau đó `package:discover` chạy lại qua `post-autoload-dump` (`backend/composer.json:68-71`).
  - `bootstrap/cache/` không được track, chỉ có `.gitignore`.
  - `devops/build/booking.prod.Dockerfile:1-2` dựng từ ảnh API đó.
  - Package dev vì vậy không vào prod.
- `.env` local hiện là `APP_ENV=local` và `DB_DATABASE=booking`, tức DB demo trên bt-server (memory: không có Postgres local).
- CI chỉ chạy Pint, PHPStan và `vendor/bin/phpunit tests/Unit/Architecture` (`.gitlab-ci.yml`, job `backend_static`).
  - Architecture test là PHPUnit thuần, đọc file, không dùng DB (`backend/tests/Unit/Architecture/BackendArchitectureTest.php`).
- Ràng buộc từ người dùng: chỉ thêm `dedoc/scramble` (đã duyệt) với renderer Scalar; bỏ Telescope; không mở docs ở prod.
- Ghi chú: thông tin về cấu hình Scramble và Scalar lấy từ Context7 qua session điều phối, chưa đọc từ package đã cài. Các task có bước tự kiểm cho chỗ này.

## Goals / Non-Goals

**Goals:**
- Có docs có "try it" ở local, không phải viết tay.
- Hai lớp chặn prod: không cài package trong ảnh prod, và Scramble chặn môi trường không phải local. Có test CI khoá cả hai.
- Gọi được endpoint `auth:sanctum` từ docs.

**Non-Goals:**
- Annotation hay mô tả từng endpoint.
- Mở docs cho bt-server hay staging.
- Token auth.
- Telescope.

## Decisions

### D1. Scramble trong require-dev, config publish và commit
`composer require dedoc/scramble --dev`, rồi publish `config/scramble.php` bằng tag `scramble-config` của package.

- Scramble suy ra tài liệu từ FormRequest và API Resource, đúng mẫu Controller → FormRequest → Resource của dự án (`.agents/rules/backend-architecture.md:36`), nên không phải viết tay.
- `config/scramble.php` có trong ảnh prod nhưng không chạy, vì không ai đọc nó. Tham chiếu `X::class` chỉ là chuỗi, không autoload.
- Đã loại:
  - Scribe và l5-swagger: Scribe cần sinh lại, l5-swagger cần annotation.
  - Viết OpenAPI tay: 349 route, chắc chắn lệch.
  - Telescope: người dùng đã bỏ.

### D2. Giữ nguyên `api_path`
Giá trị mặc định là `api`, khớp prefix của app và gồm cả `/api/v1/*`.

- Task chỉ kiểm, không sửa.
- `api_domain` để `null`.

### D3. Renderer Scalar, không proxy, ghim CDN
Trong `config/scramble.php`:

- `'renderer' => 'scalar'`.
- `renderers.scalar.proxyUrl` để rỗng hoặc `null`:
  - Docs và API cùng origin (`php artisan serve`, cổng 8000), nên không cần proxy CORS.
  - Proxy mặc định `https://proxy.scalar.com` sẽ gửi cookie session và payload ra ngoài.
- `renderers.scalar.credentials` giữ `'include'`.
- `renderers.scalar.cdn` ghim bản chính xác của `@scalar/api-reference` (lấy bằng `npm view @scalar/api-reference version` lúc làm). Như vậy giao diện không tự đổi theo bản mới nhất.
- Các khoá khác giữ mặc định, kể cả `dev_tools.enabled`, mặc định theo `APP_DEBUG`, là `true` ở local.
- Đã loại: trang Blade tự viết nạp Scalar, vì Scramble đã hỗ trợ sẵn.

Nếu file publish không có đúng các khoá trên: dừng và báo lại, không đoán tên khoá.

### D4. Quyền truy cập: giữ mặc định, không định nghĩa gate
Giữ `'middleware' => ['web', RestrictedDocsAccess::class]` mặc định:

- `APP_ENV=local` thì cho qua.
- Có gate `viewApiDocs` thì theo gate.
- Còn lại trả 403.

Không định nghĩa `viewApiDocs` ở bất kỳ đâu. Lớp chính vẫn là `--no-dev` (prod trả 404). Lớp này đỡ trường hợp package lọt vào môi trường không phải local.

Đã loại: gate theo email hay vai trò admin, vì nó mở docs trên demo, trái yêu cầu.

### D5. Header CSRF dùng chung cho mọi request
Trong `AppServiceProvider::boot()` thêm một document transformer khai báo security scheme `apiKey` ở header `X-XSRF-TOKEN` cho toàn bộ tài liệu.

- Scalar hiện một ô Authentication. Dán giá trị một lần là mọi request đều kèm header.
- Cookie session đi tự động, vì cùng origin và `credentials: include`.
- Lời gọi được bọc trong `if (class_exists(Scramble::class))`, vì package không có ở prod.
  - Đây là bắt buộc, không phải shim. Không bọc thì `boot()` gây fatal ở prod.
- API theo Context7:

  ```php
  Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
      $openApi->secure(SecurityScheme::apiKey('header', 'X-XSRF-TOKEN'));
  });
  ```

  - Class: `Dedoc\Scramble\Scramble`, `Dedoc\Scramble\Support\Generator\OpenApi`, `Dedoc\Scramble\Support\Generator\SecurityScheme`.
  - Nếu bản đã cài không có `withDocumentTransformers`, dùng `Scramble::afterOpenApiGenerated(...)` với đúng thân closure đó.
- Đã loại:
  - Chỉ ghi hướng dẫn thêm header tay: 349 route, mỗi request phải dán lại.
  - Phát bearer token: đổi hợp đồng auth, ngoài phạm vi.
  - Bỏ CSRF cho `api/*`: hạ bảo mật toàn app.

Đánh đổi: mọi endpoint, kể cả endpoint công khai, hiện biểu tượng khoá. Chấp nhận được vì header thừa không gây hại.

### D6. `GET /` chỉ quảng cáo docs ở local
Trong `backend/routes/web.php`, dùng `array_filter` hoặc spread có điều kiện `app()->isLocal()` để `docs: '/docs/api'` chỉ có ở local. `name` và `status` giữ nguyên.

Đã loại:
- Luôn trả `/docs/api`: prod lại có link chết (404).
- Xoá hẳn key: người dùng muốn trỏ đúng URL docs.

### D7. Architecture test chặn lộ docs
Thêm `backend/tests/Unit/Architecture/ApiDocsExposureTest.php`, extends `PHPUnit\Framework\TestCase` giống `BackendArchitectureTest`, chỉ đọc file. Test khẳng định:

1. `composer.json`: `dedoc/scramble` có trong `require-dev` và không có trong `require`.
2. `Dockerfile` chứa `composer install --no-dev`.
3. Không file `.php` nào trong `app/`, `routes/` hay file `bootstrap/app.php` chứa chuỗi `viewApiDocs`.
   - Không quét `config/`: comment trong `config/scramble.php` publish từ package có thể nhắc tên gate.
   - Không quét `bootstrap/cache/`: đó là file sinh ra.

Test chạy trong CI sẵn có, không cần DB. Đã loại: feature test gọi `/docs/api`, vì cần DB test và chỉ kiểm lại middleware của package.

### D8. Tài liệu cho người test
- Thêm mục "Tài liệu API (local)" vào `backend/README.md`, viết tiếng Việt như phần còn lại của file.
- Sửa `backend/.env.example:73`, thêm `localhost:8000,127.0.0.1:8000` vào `SANCTUM_STATEFUL_DOMAINS`.

## Risks / Trade-offs

- [Rủi ro] "Try it" là request thật. `.env` local đang trỏ DB `booking` trên bt-server, nên POST, PATCH và DELETE ghi thật vào demo.
  - Giảm thiểu: README dặn dùng tài khoản demo (`docs/demo-accounts.md`). Với tài khoản demo, middleware `SimulateDemoMutations` rollback mutation của nhóm `admin`. Ngoài nhóm đó vẫn ghi thật.
  - Đổi DB local là quyết định riêng, ngoài phạm vi.
- [Rủi ro] Khi sinh tài liệu, Scramble đọc schema bảng (cột) từ DB đang kết nối, ở đây là `booking` trên bt-server. Chỉ đọc, chấp nhận.
- [Rủi ro] Scramble có thể suy sai kiểu ở một số endpoint, hoặc báo lỗi phân tích. Chấp nhận: đây là công cụ dev, sai thì sửa khi cần (Out of scope).
- [Rủi ro] Ảnh prod sao chép `.env.example`, có `APP_ENV=local`, thành `.env` (`backend/Dockerfile:20`). Nếu Portainer quên đặt `APP_ENV` thì prod chạy như local.
  - Docs vẫn không lộ, vì package không được cài (D7 khoá điều này).
  - Chưa xác minh biến môi trường trên bt-server.
- [Đánh đổi] Mọi endpoint hiện khoá CSRF trong Scalar (D5).

## Migration Plan

- Không có migration. Không deploy riêng: CI chỉ đổi ở chỗ có thêm một architecture test.
- Ảnh prod không đổi, trừ `GET /` mất key `docs`.
- Rollback: `composer remove dedoc/scramble --dev`, rồi xoá `config/scramble.php`, khối trong `AppServiceProvider` và test.

## Open Questions

Không có.
