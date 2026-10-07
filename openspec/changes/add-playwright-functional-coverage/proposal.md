## Why

Bộ Playwright hiện có 12 test trong 3 file nhưng chủ yếu kiểm tra mở trang/redirect, còn kỳ vọng quyền owner đã lệch router hiện tại và dữ liệu phụ thuộc tài khoản demo, slot, số dư cố định. Cần kiểm chứng hành trình người dùng xuyên frontend–API–database và đưa các luồng quan trọng vào cổng kiểm tra CI, thay vì coi bằng chứng backend hoặc toast thành công là browser acceptance.

## What Changes

- Lập ma trận cho 126 FR thuộc 7 nhóm chức năng, nối yêu cầu với màn hình/API, vai trò, test, mức ưu tiên và bằng chứng; bổ sung các capability hiện hành ngoài danh mục FR như waitlist, thông báo, service tabs, drop-in bills, localization và realtime.
- Chuẩn bị môi trường E2E cô lập, database riêng, tài khoản không-demo, dữ liệu riêng cho từng test và kiểm tra an toàn trước mọi reset/mutation. Không gọi ngân hàng, SMTP hay OAuth thật trong bộ CI mặc định.
- Sửa/thay thế baseline lỗi thời; mở rộng Playwright sẵn có theo thứ tự: nền test → P0 → CI bắt buộc → P1 → P2. Không tạo framework test thứ hai.
- P0 bao phủ auth/phân quyền/cách ly tenant, availability, booking–direct-owner payment, xung đột/hết hạn giữ chỗ, coupon/loyalty, đổi/hủy, POS/ca và check-in/service-tab/check-out; xác minh trạng thái sau reload và chống tác dụng trùng.
- P1 bao phủ đối tác, subscription, công khai cơ sở, nhân viên, giá/ưu đãi, định kỳ, split/resale, kho/POS mở rộng, cộng đồng, giải đấu, tranh chấp và báo cáo.
- P2 bao phủ lỗi/phục hồi, capability tắt, Việt–Anh, mobile, sáng–tối, bàn phím/focus và realtime; smoke bổ sung Firefox/WebKit.
- Bổ sung job E2E vào GitLab CI: P0 chặn merge/deploy khi thất bại; regression đầy đủ chạy theo lịch CI hoặc thủ công trước release; lưu report/trace/ảnh lỗi với giới hạn truy cập và thời gian lưu.
- Tách bằng chứng browser, backend và provider sandbox. Chức năng thiếu UI hoặc thiếu bằng chứng bên ngoài phải ghi gap/blocked, không được tính là browser pass.

## Capabilities

### New Capabilities

Không thêm capability nghiệp vụ mới.

### Modified Capabilities

- `platform-readiness`: bổ sung hợp đồng browser acceptance tái lập được, an toàn dữ liệu, traceability theo từng FR, kiểm chứng thanh toán trung thực và cổng CI theo ưu tiên. Giữ nguyên các yêu cầu readiness/rollout/provider hiện có.

## Impact

- Phạm vi triển khai dự kiến: `frontend/e2e/`, `frontend/playwright.config.ts`, scripts E2E trong `frontend/package.json`; hỗ trợ dữ liệu/test-only ở backend; `.gitlab-ci.yml`; tài liệu chạy test và ma trận coverage.
- Tái sử dụng `@playwright/test`, PostgreSQL, API/domain services và test patterns hiện có. Không thêm dependency mặc định, không dựng Page Object cho mọi trang.
- Thay đổi production UI chỉ giới hạn semantic labels/test IDs thực sự cần cho test; không sửa quyền, chính sách tiền, API contract hoặc xây màn hình nghiệp vụ thiếu trong change này. Lỗi sản phẩm được ghi bằng chứng và chuyển sang change sửa lỗi riêng.
- Chuẩn nghiệp vụ là main specs hiện tại, đặc biệt direct-owner payments, refund obligations và bảo toàn lịch sử ví; không lấy các delta wallet-only/no-cancellation cũ làm chuẩn.
- Không triển khai production, cấp quyền tài khoản thật, chuyển tiền thật, reset database dùng chung hoặc bật cohort thật. Bộ test cô lập không thay thế chứng nhận provider sandbox hay phê duyệt rollout.
