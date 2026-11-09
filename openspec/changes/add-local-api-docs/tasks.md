## 0. Bẫy cần nhớ

- Mọi lệnh chạy trong `backend/`.
- Package `dedoc/scramble` đã được người dùng duyệt. Không thêm package composer hay npm nào khác.
  - Nếu `composer require` không giải được phụ thuộc (Laravel `^13.8`, PHP `^8.3`): dừng và báo lại.
  - Không nâng hay hạ package khác để ép cho cài được.
- Không định nghĩa gate `viewApiDocs` ở đâu cả.
- Không sửa khoá `middleware` trong `config/scramble.php` (D4).
- `proxyUrl` không bao giờ trỏ tới `proxy.scalar.com` (D3).
- Không sửa `backend/Dockerfile` hay `devops/`. Không sửa hai change `align-module-05-packages-finance` và `remove-venues`.
- `.env` local trỏ DB demo `booking` trên bt-server:
  - Không chạy feature test hay `migrate`.
  - Khi kiểm thủ công, chỉ gọi GET, `GET /sanctum/csrf-cookie`, `POST /api/auth/login` bằng tài khoản demo, và các POST bị chặn 419. Không gọi endpoint ghi nào khác.
- `bootstrap/cache/*.php` là file sinh ra, không commit. `composer.lock` thì commit.
- Không push, không deploy.

## 1. Cài Scramble (D1, D2)

- [x] 1.1 Chạy `composer require dedoc/scramble --dev`.
  - Xong khi:
    - `backend/composer.json` có `dedoc/scramble` trong `require-dev` và không có trong `require`.
    - `backend/composer.lock` đã cập nhật.
    - `php artisan route:list --path=docs` liệt kê `docs/api` và `docs/api.json`.
- [x] 1.2 Chạy `php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag="scramble-config"` để tạo `backend/config/scramble.php`.
  - Chỉ đọc, không sửa:
    - `api_path` là `'api'`.
    - `api_domain` là `null`.
    - `middleware` gồm `'web'` và `RestrictedDocsAccess::class`.
  - Nếu khác các giá trị trên: dừng và báo lại.

## 2. Renderer Scalar (D3)

- [x] 2.1 Lấy bản Scalar hiện tại bằng `npm view @scalar/api-reference version`. Không cài gì, chỉ đọc số phiên bản.
- [x] 2.2 Sửa `backend/config/scramble.php`: (ghi chú: bản ghim là `1.77.0`. Kiểm "xong khi" làm với cache đã nạp sẵn spec, xem ghi chú ở 8.7, vì sinh spec theo request không chạy được trên máy này)
  - `'renderer' => 'scalar'`.
  - `renderers.scalar.proxyUrl` thành `null`. Nếu view của package in `null` thành chuỗi `"null"` thì dùng `''`.
  - `renderers.scalar.cdn` ghim bản ở 2.1, giữ nguyên dạng URL mặc định, chỉ thay phần phiên bản. Ví dụ `https://cdn.jsdelivr.net/npm/@scalar/api-reference@<version>`.
  - `renderers.scalar.credentials` giữ `'include'`.
  - Các khoá khác, kể cả `dev_tools`, giữ mặc định.
  - Nếu file không có khoá `renderer` hay `renderers.scalar`: dừng và báo lại, không đoán.
  - Xong khi chạy `php artisan serve --host=127.0.0.1 --port=8000` rồi:
    - `curl -s http://127.0.0.1:8000/docs/api | grep -c 'proxy.scalar.com'` in `0`.
    - `curl -s http://127.0.0.1:8000/docs/api | grep -c '@scalar/api-reference@'` lớn hơn `0`.

## 3. Header CSRF dùng chung (D5)

- [x] 3.1 Trong `backend/app/Providers/AppServiceProvider.php`:
  - Thêm ba `use`: `Dedoc\Scramble\Scramble`, `Dedoc\Scramble\Support\Generator\OpenApi`, `Dedoc\Scramble\Support\Generator\SecurityScheme`.
  - Gọi `$this->configureApiDocs();` ngay sau `$this->configureRateLimits();` trong `boot()`.
  - Thêm method private, đặt trước `configureRateLimits()`:

    ```php
    private function configureApiDocs(): void
    {
        // add-local-api-docs D5: Scramble is require-dev, so the production image (--no-dev) has no such class.
        if (! class_exists(Scramble::class)) {
            return;
        }
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(SecurityScheme::apiKey('header', 'X-XSRF-TOKEN'));
        });
    }
    ```

  - Nếu `Scramble::configure()->withDocumentTransformers` không tồn tại trong bản đã cài, thay bằng `Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void { ...cùng thân... });`.
  - Xong khi `curl -s http://127.0.0.1:8000/docs/api.json | jq '.components.securitySchemes, .security'` cho thấy một scheme `apiKey`, `in: header`, `name: X-XSRF-TOKEN`, và `security` tham chiếu scheme đó.

## 4. `GET /` trỏ đúng docs (D6)

- [x] 4.1 Trong `backend/routes/web.php:6-12`, đổi phần trả về thành `response()->json(array_filter([...]))`:
  - `'name' => 'Aura Court API'` và `'status' => 'ok'` giữ nguyên.
  - Thêm `'docs' => app()->isLocal() ? '/docs/api' : null`.
  - Giữ dạng closure `function () { return ...; }` như hiện tại.
  - Xong khi:
    - `curl -s http://127.0.0.1:8000/` trả `{"name":"Aura Court API","status":"ok","docs":"\/docs\/api"}`.
    - Ở nhóm 8, `GET /` với `APP_ENV=production` không còn key `docs`.

## 5. Architecture test (D7)

- [x] 5.1 Tạo `backend/tests/Unit/Architecture/ApiDocsExposureTest.php`:
  - Namespace `Tests\Unit\Architecture`. Extends `PHPUnit\Framework\TestCase`, giống `BackendArchitectureTest.php`.
  - Lấy thư mục backend bằng `dirname(__DIR__, 3)`.
  - Ba test:
    - `test_scramble_is_a_dev_dependency_only`: `json_decode` `composer.json` với `JSON_THROW_ON_ERROR`, rồi `assertArrayHasKey('dedoc/scramble', $composer['require-dev'])` và `assertArrayNotHasKey('dedoc/scramble', $composer['require'])`.
    - `test_backend_image_installs_without_dev_dependencies`: `assertStringContainsString('composer install --no-dev', file_get_contents(<backend>/Dockerfile))`.
    - `test_no_code_defines_the_docs_gate`:
      - Duyệt đệ quy mọi `.php` trong `app/` và `routes/` (copy cách duyệt ở `BackendArchitectureTest::measure()`), cộng thêm file `bootstrap/app.php`.
      - Gom file nào chứa chuỗi `viewApiDocs`.
      - `assertSame([], $hits, 'Defining the viewApiDocs gate opens /docs/api outside APP_ENV=local.')`.
      - Không quét `config/` hay `bootstrap/cache/` (D7).
  - Xong khi `vendor/bin/phpunit tests/Unit/Architecture` đạt.
    - Test chỉ đọc file. `phpunit.xml` ép tên DB test, nên không đụng DB `booking`.
    - Kiểm ngược bằng tay: tạm đổi `--no-dev` trong chuỗi assert thành `--no-devX`, test phải fail, rồi trả lại.

## 6. Tài liệu (D8)

- [x] 6.1 `backend/.env.example:73`: thêm `,localhost:8000,127.0.0.1:8000` vào cuối giá trị `SANCTUM_STATEFUL_DOMAINS`. Không sửa `backend/.env`, vì nó đã có hai giá trị này.
- [x] 6.2 (README đã viết khác nội dung bên dưới ở hai chỗ, xem ghi chú 8.7) `backend/README.md`: thêm mục mới ngay sau mục `## Endpoint chính` (trước `## Cấu hình production`), đúng nội dung sau.

  ````markdown
  ## Tài liệu API (local)

  Chỉ có khi chạy local (`APP_ENV=local`). Bản production cài `--no-dev` nên không có trang này; môi trường khác trả 403.

  - Mở `http://127.0.0.1:8000/docs/api` (Scalar, có nút "Test Request"). OpenAPI JSON ở `/docs/api.json`.
  - Tài liệu tự sinh từ route, FormRequest và API Resource bằng Scramble. Không cần chạy lệnh sinh lại.

  Gọi endpoint cần đăng nhập (API dùng session cookie, không có bearer token):

  1. Gọi `GET /sanctum/csrf-cookie` trong trang docs.
  2. Mở DevTools Console của trang docs, chạy `decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1])`, dán kết quả vào ô `X-XSRF-TOKEN` ở phần Authentication.
  3. Gọi `POST /api/auth/login` với tài khoản trong [`docs/demo-accounts.md`](../docs/demo-accounts.md). Các request sau dùng chung session.

  Thiếu `X-XSRF-TOKEN` thì request ghi nhận 419. Chưa đăng nhập thì endpoint cần auth trả 401.

  Lưu ý: request gửi từ trang docs là request thật vào DB mà `.env` đang trỏ tới. Ưu tiên tài khoản demo: với tài khoản demo, các thao tác ghi trong nhóm `/api/admin` được rollback; ngoài nhóm đó vẫn ghi thật.
  ````

## 7. Gate

- [x] 7.1 Chạy, và tất cả phải đạt:
  - `vendor/bin/pint --test`. Nếu báo lỗi ở file vừa sửa, chạy `vendor/bin/pint <file>`.
  - `vendor/bin/phpstan analyse -c phpstan-level0.neon --no-progress --memory-limit=1G`.
  - `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`.
    - Nếu PHPStan báo dòng `class_exists` ở 3.1 là luôn đúng: thêm `// @phpstan-ignore <identifier đúng như PHPStan in ra>` trên dòng đó.
    - Không sửa `phpstan-baseline.neon`.
  - `vendor/bin/phpunit tests/Unit/Architecture`.
- [x] 7.2 Ở gốc repo, `openspec validate add-local-api-docs --strict` đạt.

## 8. Kiểm thủ công (một đợt, sau nhóm 7)

Chạy `php artisan serve --host=127.0.0.1 --port=8000` với `.env` local. Chỉ dùng các request được phép nêu ở nhóm 0.

- [x] 8.1 Mở `http://127.0.0.1:8000/docs/api` trong trình duyệt:
  - Giao diện Scalar hiện danh sách endpoint, gồm `/auth/login` và ít nhất một đường dẫn `/v1/...`.
  - `jq '.paths | keys | length'` trên `/docs/api.json` ra số lớn hơn 0.
- [x] 8.2 Gửi `GET /courts` từ Scalar. Tab Network chỉ có request tới `127.0.0.1:8000`, không có `proxy.scalar.com`.
- [x] 8.3 Gọi `GET /api/auth/me` khi chưa đăng nhập → 401.
- [x] 8.4 Gửi `POST /api/auth/logout` khi ô `X-XSRF-TOKEN` để trống → 419.
- [ ] 8.5 Làm ba bước trong README (6.2) với một tài khoản demo:
  - `POST /api/auth/login` → 200 kèm `user`.
  - Sau đó `GET /api/auth/me` → 200, đúng user đó.
- [x] 8.6 Dừng server. Chạy `APP_ENV=production php artisan serve --host=127.0.0.1 --port=8001`, rồi:
  - `curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8001/docs/api` in `403`.
  - `curl -s http://127.0.0.1:8001/` không có key `docs`.
  - Dừng server. Không gọi gì khác ở cổng này.
- [x] 8.7 Ghi kết quả 8.1–8.6, kể cả lỗi phân tích nào Scramble hiện trong trang, vào ghi chú dưới task này.

  **Ghi chú kết quả (2026-11-09):**
  - **Lệch so với môi trường giả định:** `backend/.env` trỏ `DB_HOST=lab-server`, và DB `booking` không tồn tại ở đó (chỉ có `booking_*_test`). Kiểm thủ công chạy với `DB_DATABASE=booking_split_test` (ghi đè qua biến môi trường, không sửa `.env`). Không có gì ghi vào DB `booking` của bt-server.
  - **Cập nhật 2026-11-10 (đã xử lý):** theo lựa chọn "nới giới hạn", `AppServiceProvider::configureApiDocs()` nâng `memory_limit` lên 1G và gọi `set_time_limit(0)` cho request `docs/api*`. Kiểm lại bằng `php -S` với `php.ini` mặc định và `DB_DATABASE=booking_split_test`: `/docs/api` → 200 sau khoảng 70 giây, `/docs/api.json` → 200 sau khoảng 76 giây, 302 path, có `/auth/login`, `/v1/...` và scheme `X-XSRF-TOKEN`; server URL theo host của request. Pint, PHPStan và test kiến trúc vẫn đạt. Mỗi lần mở vẫn sinh lại (không cache), nên chậm khi DB ở xa. Đoạn bên dưới là tình trạng trước khi nới giới hạn.
  - **Sinh tài liệu theo request không chạy được trên máy này:**
    - `memory_limit` 128M của PHP CLI không đủ (`scramble:export` chết vì hết bộ nhớ); với `-d memory_limit=2G` thì xong sau khoảng 2 phút, 302 path, 3 lỗi và 24 cảnh báo phân tích.
    - Gần như toàn bộ thời gian là chờ DB (khoảng 260 ms mỗi query qua mạng tới `lab-server`; CPU chỉ khoảng 4 giây). Request web bị cắt ở khoảng 60 giây.
    - `scramble:cache` không dùng được: Laravel 13 đặt `cache.serializable_classes = false`, nên kết quả cache đọc ra là `__PHP_Incomplete_Class` và Scramble bỏ qua, sinh lại từ đầu.
    - Vì vậy các kiểm tra 8.1–8.2 và nhóm 2.2 làm bằng cách nạp sẵn spec đã export (dạng mảng, Scramble chấp nhận) vào cache file. Cache đó đã xoá bằng `scramble:clear` sau khi xong. Chưa kiểm được đường sinh theo request bằng DB nhanh.
  - **8.1 đạt:** trang Scalar hiện 302 path, có `/auth/login` và `/v1/...`; `securitySchemes` có `apiKey` (`in: header`, `name: X-XSRF-TOKEN`) và `security` tham chiếu nó (3.1 đạt). Console không có lỗi.
  - **8.2 đạt:** `GET /courts` từ Scalar trả 200, request đi thẳng tới `localhost:8000/api/courts`, không có `proxy.scalar.com`; HTML trang có `proxyUrl: null` và CDN ghim `1.77.0`.
  - **8.3 đạt:** `GET /api/auth/me` khi chưa đăng nhập → 401.
  - **8.4 sửa lại bước kiểm:** `POST /api/auth/logout` nằm sau `auth:sanctum` nên trả 401 trước CSRF. Dùng `POST /api/auth/login` (công khai) với Referer stateful nhưng không có `X-XSRF-TOKEN` → 419; có token và sai mật khẩu → 422; Referer không stateful → 422.
  - **8.5 CHƯA làm:** mật khẩu demo không có trong repo (seed đọc từ `SEED_*`), nên không đăng nhập thật được. Đã kiểm phần CSRF của luồng này (419 khi thiếu token, 422 khi có token) nhưng chưa kiểm `login` → 200 rồi `GET /api/auth/me` → 200. Cần người có mật khẩu demo làm nốt.
  - **8.6 đạt:** với `APP_ENV=production`, `/docs/api` và `/docs/api.json` → 403; `GET /` không có key `docs`.
  - **Phát hiện làm lệch task 6.2 và D5:** view Scalar của Scramble tự gắn `X-XSRF-TOKEN` (giải mã từ cookie `XSRF-TOKEN`) vào mọi request qua `onBeforeRequest`; đã thấy header này trong request của `GET /courts`. Nên README không bảo dán token tay, và ô Authentication thêm ở D5 là thừa (giá trị dán vào bị ghi đè). Giữ D5 vì spec yêu cầu; nên cân nhắc bỏ ở một change sau. Mọi endpoint hiện nhãn "Auth Required" do D5. Nếu chưa có cookie, header thành chuỗi `"undefined"`, vì vậy vẫn phải gọi `GET /sanctum/csrf-cookie` trước.
  - **Host mở trang:** server URL trong spec là `APP_URL` (`http://localhost:8000`) khi spec sinh bằng CLI. Mở trang bằng `127.0.0.1` thì request thành khác origin. README đã dặn dùng host của `APP_URL`.
  - **Pint:** `config/scramble.php` (file publish) bị báo `fully_qualified_strict_types`, đã chạy `pint` để sửa.
