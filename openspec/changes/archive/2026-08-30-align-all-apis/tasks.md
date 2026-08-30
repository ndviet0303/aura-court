## 1. Audit và Dọn Dẹp Mock Data

- [x] 1.1 Quét toàn bộ thư mục `frontend/src/features/*/api/*.ts` để tìm và xoá các mock data fallback dạng `res || []`
- [x] 1.2 Kiểm tra và vô hiệu hoá MSW handler (nếu có) trong code base đối với các endpoint đã có API thật
- [x] 1.3 Audit các file components (ví dụ `AdminKycVerificationView.vue`, `PaymentView.vue`) xem có truyền hardcoded data nào xuống child components hay không

## 2. Đối chiếu Data Mapping (KYC & Finance)

- [x] 2.1 Đối chiếu interface `HostKyc` ở frontend với Resource `HostKycResource.php` trả về từ `AdminKycController`, cập nhật nếu có sai lệch
- [x] 2.2 Đối chiếu `ReconciliationStats` và `ReconciliationRun` với Resource trả về từ `FinanceController`, cập nhật interface tương ứng
- [x] 2.3 Rà soát mapping của `financialExceptionsApi.ts` với `FinanceExceptionItem` để đảm bảo khớp dữ liệu với Backend

## 3. Cập nhật xử lý lỗi (Error Handling)

- [x] 3.1 Bổ sung block `try-catch` trong các composables (như `useAdminKyc.ts`) cho các network call chưa có catch, ném lỗi ra `showNotice` hoặc `Toast`
- [x] 3.2 Đảm bảo interceptor ở `core/api/client.ts` bắt được lỗi 422 và quăng ra field `errors` một cách nhất quán
- [x] 3.3 Đảm bảo UI handle được trường hợp loading failed (có placeholder hoặc thông báo rỗng) thay vì vỡ layout

## 4. Cập nhật Unit Tests

- [x] 4.1 Cập nhật `AdminKycVerificationView.spec.ts` để mock API call (thay vi.mock `apiV2.get`) trả về mock response giả lập backend thay vì inject thẳng array mock data
- [x] 4.2 Chạy toàn bộ test suites frontend để đảm bảo không gãy test do thay đổi API client signature

## 5. Module Tổ chức & Nhân sự (Organization & Staff)

- [x] 5.1 Tạo/chuẩn hoá `organizationApi.ts` & `staffApi.ts` gọi đúng API backend (`/organizations/{org}`, `/members`, `/venues`)
- [x] 5.2 Xoá mock data trong `useOrganizationOwner.ts`, kết nối API lấy profile tổ chức, danh sách venues và members
- [x] 5.3 Xoá mock `staffList` & `auditLogs` trong `useAdminStaff.ts`, lấy danh sách thành viên từ API thực tế

## 6. Module POS Bán hàng & Thu ngân

- [x] 6.1 Chuẩn hoá `posApi.ts` với các hàm lấy reservations đang hoạt động và tìm kiếm khách hàng
- [x] 6.2 Xoá bỏ mảng hardcode `activeReservations` và `customerDatabase` trong `PosView.vue`

## 7. Module Tournament & Schedule

- [x] 7.1 Rà soát và gỡ mock data tĩnh trong `useTournamentBracketAdmin.ts` và `useAdminSchedule.ts`
- [x] 7.2 Đảm bảo các composable có fallback an toàn (empty state) và error notification khi network lỗi

## 8. Xác thực tổng thể

- [x] 8.1 Chạy `vue-tsc --noEmit` và toàn bộ unit test frontend
- [x] 8.2 Đảm bảo không còn dữ liệu hardcoded mock trong toàn bộ thư mục `frontend/src/features/`
