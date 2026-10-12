# Runbook: End-to-End Browser Acceptance & CI Gates

Tài liệu hướng dẫn vận hành, kiểm thử cục bộ (Local), cấu hình CI/CD và quy trình xử lý sự cố cho bộ kiểm thử chấp nhận trình duyệt Playwright của hệ thống Aura Booking.

---

## 1. Kiến trúc môi trường kiểm thử cô lập (Isolated Test Architecture)

Bộ kiểm thử Playwright hoạt động trên cơ chế **Ephemeral Target Isolation** nhằm đảm bảo tính lặp lại (determinism), an toàn dữ liệu và độc lập tuyệt đối giữa các lần chạy:

1. **Định danh phiên chạy (`runId`)**:
   Mỗi môi trường cô lập sinh một `runId` duy nhất dạng timestamp + random hex (ví dụ: `1790760007212_52871052`).
2. **Cơ sở dữ liệu PostgreSQL riêng biệt**:
   Runner tự động khởi tạo database chuyên biệt: `booking_e2e_<runId>_test`. Toàn bộ migration Laravel được thực thi trên database này. Database mặc định (`booking`, `booking_test`) được bảo vệ nghiêm ngặt: bất kỳ hành vi ghi nào vào database được bảo vệ sẽ bị chặn đứng (fail-closed).
3. **Tiến trình Backend & Frontend chuyên dụng**:
   - Backend PHP Artisan chạy trên cổng ngẫu nhiên khả dụng (`127.0.0.1:<backendPort>`), trong process group riêng để teardown dừng cả các worker của PHP built-in server.
   - Frontend được build riêng cho backend đó (`vite build --mode development` với `VITE_API_URL=<backendUrl>`) rồi phục vụ bằng `vite preview` trên cổng ngẫu nhiên khả dụng (`127.0.0.1:<frontendPort>`). Bản build tải trang nhanh hơn dev server nhiều lần.
   - Session driver: `database` (lưu trữ session bền vững qua HTTP, không dùng `array` session).
4. **Dữ liệu mẫu phi demo (Non-Demo Deterministic Fixtures)**:
   Không dùng tài khoản demo hoặc số dư cố định. Dữ liệu được seed tự động qua command `php artisan e2e:seed-fixtures --run-id=<runId>` bao gồm:
   - 2 Tổ chức (Org 1 và Org 2).
   - 3 Cơ sở (Venue 1-A, Venue 1-B, Venue 2).
   - Các tài khoản người dùng đầy đủ vai trò: `owner1`, `owner2` (Foreign Owner), `manager1`, `cashier1`, `staff1`, `customer`, `customer2`, `unverifiedCustomer`, `restrictedCustomer`, và `admin`.
5. **Thu hồi tài nguyên an toàn (Teardown & Signal Handling)**:
   Khi suite kết thúc (thành công, thất bại, hoặc bị ngắt qua `SIGINT`/`SIGTERM`), runner giải phóng tiến trình frontend/backend và drop database `booking_e2e_<runId>_test` của mọi môi trường.
6. **Chạy song song (một môi trường cho mỗi worker)**:
   Local mặc định chạy 4 worker. Global setup dựng đồng thời một môi trường cô lập đầy đủ (database, backend, frontend) cho mỗi worker; mỗi worker dùng môi trường theo `TEST_PARALLEL_INDEX` nên không chia sẻ dữ liệu. Đổi số worker bằng `--workers=N` (ví dụ `--workers=1` để chạy tuần tự). `E2E_TARGET=prod` luôn chạy 1 worker.

---

## 2. Yêu cầu tiên quyết (Prerequisites)

### 2.1. Yêu cầu hệ thống cục bộ (Local Machine)
- **Node.js**: Phiên bản 22.x LTS trở lên.
- **PHP**: Phiên bản 8.2 trở lên với các extension: `pdo_pgsql`, `pgsql`, `mbstring`, `bcmath`, `intl`, `curl`.
- **PostgreSQL**: Phiên bản 16 trở lên đang chạy tại `127.0.0.1:5432`.
- **Playwright Browsers**: Cài đặt đủ Chromium, Firefox và WebKit:
  ```bash
  cd frontend
  npx playwright install chromium firefox webkit
  ```

### 2.2. Yêu cầu môi trường CI Runner (GitLab CI)
- **Docker Executor**: GitLab Runner hỗ trợ socket Docker hoặc native macOS shell runner.
- **Image chạy Playwright**: `mcr.microsoft.com/playwright:v1.62.1-noble` (glibc-based).
  *Lưu ý an toàn*: Tuyệt đối không cài đặt Playwright vào container Alpine Linux (`musl` libc) của job deploy.

---

## 3. Hướng dẫn chạy kiểm thử cục bộ (Local Runbook)

### 3.1. Chạy cổng P0 bắt buộc (P0 Critical Acceptance Gate)
Bao phủ 10 hành trình cốt lõi: Auth, Role matrix, Sân & Lịch, Đặt sân & Webhook QR, Xung đột concurrency, Hết hạn hold, Coupon & Điểm thưởng, Đổi lịch/Hủy hoàn tiền, POS ca thu ngân, và Điểm danh/Tab dịch vụ:
```bash
cd frontend
npx playwright test --project p0
```

### 3.2. Chạy bộ chức năng mở rộng P1 (P1 Functional Journeys)
Bao phủ Vòng đời tài khoản, Đăng ký đối tác & KYC, Gói dịch vụ Platform, Cổng thu tiền trực tiếp, Hạng thành viên & Khuyến mãi, Lịch định kỳ, Chợ nhượng vé, Check-in/Hàng chờ, Hóa đơn giao lưu tại sân, Giải đấu, Khiếu nại tranh chấp, và Báo cáo đối soát:
```bash
cd frontend
npx playwright test --project p1
```

### 3.3. Chạy bộ P2 & Khả năng chịu lỗi (P2 & Fault Injection)
Bao phủ Chịu lỗi mạng / session hết hạn / capability tắt / thu hồi quyền, Trải nghiệm đa ngôn ngữ / Theme sáng-tối / Modal a11y focus trap, Tính nhất quán refetch đa phiên, và Phân định ranh giới múi giờ `Asia/Ho_Chi_Minh`:
```bash
cd frontend
npx playwright test --project p2
```

### 3.4. Chạy kiểm tra nhanh đa trình duyệt (Secondary Browser & Mobile Smoke)
Kiểm tra điều hướng công khai, trang giới thiệu, điều khoản, chính sách bảo mật, trang 404 tùy biến và chỉ đường cơ sở trên Chromium, Firefox, WebKit và Mobile:
```bash
cd frontend
npx playwright test --project smoke-chromium --project smoke-firefox --project smoke-webkit --project smoke-mobile
```

### 3.5. Chạy toàn bộ kiểm thử hồi quy (Full Regression Suite)
Chạy mỗi test đúng một lần: 9 Khu trên Chromium cộng smoke trên Firefox, WebKit và Mobile:
```bash
cd frontend
npm run test:e2e
```
Không chạy `npx playwright test` trần (không kèm `--project`): các project phân tầng `p0`, `p1`, `p2`, `smoke-chromium` chọn lại đúng các test của 9 Khu nên mọi test Chromium bị chạy hai lần (350 thay vì 192 test).

---

## 4. Cấu trúc cổng CI/CD (.gitlab-ci.yml)

Quy trình CI được phân tầng rành mạch giữa Cổng chất lượng bắt buộc và Đợt kiểm thử hồi quy:

```
[Stage: validate]        [Stage: test-e2e]                         [Stage: build]
frontend_check      -->  e2e_p0_gate (BẮT BUỘC)              -->   build_and_deploy
                         e2e_regression_scheduled (Tùy chọn)        (Phụ thuộc e2e_p0_gate)
                         e2e_smoke_cross_browser (Tùy chọn)
```

### 4.1. Cổng P0 bắt buộc (`e2e_p0_gate`)
- **Điều kiện kích hoạt**: Mọi Merge Request và các commit đẩy lên nhánh triển khai (`dev`, `main`).
- **Lệnh thực thi**: `npx playwright test --project p0`
- **Chính sách chặn**: Bất kỳ lỗi P0 nào hoặc việc không tìm thấy test (zero-test selection) sẽ đánh fail pipeline ngay lập tức; job `build_and_deploy` bị chặn không thể thực thi.

### 4.2. Cổng hồi quy toàn diện định kỳ (`e2e_regression_scheduled`)
- **Điều kiện kích hoạt**: Pipeline chạy theo lịch (Schedule) hoặc kích hoạt thủ công qua giao diện Web (`CI_PIPELINE_SOURCE == "web"`).
- **Lệnh thực thi**: `npx playwright test`
- **Tính chất**: Độc lập hoàn toàn, không liên kết và không kích hoạt triển khai tự động.

### 4.3. Cổng smoke đa trình duyệt (`e2e_smoke_cross_browser`)
- **Điều kiện kích hoạt**: Pipeline lịch (Schedule) hoặc kích hoạt thủ công qua Web.
- **Lệnh thực thi**: `npx playwright test --project smoke-chromium --project smoke-firefox --project smoke-webkit --project smoke-mobile`

---

## 5. Đọc Báo cáo, Trace và Xử lý Lỗi (Diagnostics & Troubleshooting)

### 5.1. Xem báo cáo HTML (HTML Report)
Sau khi chạy kiểm thử, báo cáo HTML được tạo tại thư mục `frontend/playwright-report`:
```bash
cd frontend
npx playwright show-report
```

### 5.2. Đọc Playwright Trace khi test thất bại
Chạy cục bộ mặc định không ghi trace vì ghi trace cho mọi test làm cả suite chậm gấp đôi. Test thất bại vẫn lưu ảnh chụp màn hình và `error-context.md` trong `frontend/test-results/<tên-test>/`. CI (khi biến `CI` được đặt) vẫn tự lưu `trace.zip` cho mọi test thất bại. Cần trace khi chạy cục bộ thì chạy lại riêng test đó với `--trace on`, rồi mở trace để xem toàn bộ thao tác DOM, network calls, console logs và ảnh chụp từng khung hình:
```bash
cd frontend
npx playwright test e2e/<tên-file>.spec.ts:<dòng> --project="Khu*" --workers=1 --trace on
npx playwright show-trace test-results/<tên-thư-mục-test>/trace.zip
```

### 5.3. Bảo mật dữ liệu chẩn đoán (Sanitization)
Quy tắc trong `.gitlab-ci.yml` và Playwright config đảm bảo:
- Thư mục `auth-states/**` và file `*.auth.json` (chứa cookie/storage state) bị loại trừ khỏi artifact tải lên.
- File `manifest.json` chứa cấu hình kết nối database nội bộ được loại trừ khỏi artifact công khai.
- Dữ liệu người dùng và thanh toán hoàn toàn là dữ liệu giả lập được mã hóa an toàn.

### 5.4. Xử lý các sự cố thường gặp (Troubleshooting)

| Sự cố | Nguyên nhân | Biện pháp xử lý |
| :--- | :--- | :--- |
| **Preflight rejects target** | Database cấu hình không khớp tiền tố `booking_e2e_` hoặc biến `APP_ENV != testing`. | Kiểm tra file `.env` hoặc tham số môi trường; runner từ chối thao tác trên môi trường non-testing để bảo vệ dữ liệu. |
| **Port conflict** | Cổng PHP CLI hoặc Vite bị chiếm dụng bởi tiến trình cũ. | Runner tự động tìm cổng trống ngẫu nhiên; nếu xung đột, chạy script giải phóng port hoặc kiểm tra tiến trình `php` / `vite` nền. |
| **Cơ sở dữ liệu sót lại (Orphaned DB)** | Phiên chạy trước bị dừng đột ngột bằng lệnh `kill -9` khiến hook teardown không kịp chạy. | Kết nối PostgreSQL và dọn dẹp các database rác: `DROP DATABASE IF EXISTS booking_e2e_<id>_test;` |
| **Realtime WebSocket vs Polling** | WebSocket (Laravel Reverb) yêu cầu daemon nền `reverb:start` và Redis. Trong môi trường test cô lập, kiểm thử sử dụng cơ chế refetch / polling fallback (xem chi tiết tại SUP-10). | Tính năng cập nhật tức thì không cần thao tác đang là UI Gap (D14/D52); suite kiểm tra tính nhất quán dữ liệu khi người dùng kích hoạt refetch. |

---

## 6. Quyền Hạn Vận Hành & Lịch CI Thực Tế (Operational Authority Notice)

- **Quyền hạn cấu hình mã nguồn (Repository Scope)**:
  Tất cả định nghĩa pipeline (`.gitlab-ci.yml`), Playwright configuration (`playwright.config.ts`), bộ test runner, kịch bản test và ma trận bao phủ đã được tích hợp đầy đủ và xác thực trong kho mã nguồn.
- **Quyền hạn quản trị CI/CD bên ngoài (Administrative Authority)**:
  Việc bật lịch chạy tự động thực tế (GitLab Pipeline Schedule), cấu hình Runner Tag chuyên dụng trên hệ thống GitLab Server, hoặc cấp quyền Runner privileged yêu cầu thẩm quyền quản trị của DevOps / Quản trị viên dự án GitLab. Đây là tiền đề vận hành bên ngoài, không tự động thay đổi từ phía runner nội bộ.
