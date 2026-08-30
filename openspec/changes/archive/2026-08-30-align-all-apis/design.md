## Context
Ứng dụng đang trong quá trình chuyển đổi để sử dụng hoàn toàn dữ liệu thực. Hiện tại một số components (như `AdminKycVerificationView.vue`, `KycDetailWorkbench.vue`) và các API client (như `adminKycApi.ts`, `financeApi.ts`) có thể còn sót lại mock data hoặc chưa xử lý triệt để cấu trúc trả về từ Laravel API (e.g. thiếu xử lý Validation Error 422, trả về fallback `{ data: [] }` khi call failed). See `proposal.md` for motivation.

## Goals / Non-Goals

**Goals:**
- Chuẩn hoá toàn bộ việc gọi API qua instance `api` hoặc `apiV2` trong `core/api/client.ts`.
- Gỡ bỏ mọi dữ liệu hardcoded hoặc MSW mock server trong các thư mục `features/*/api` và views.
- Đảm bảo logic xử lý Catch Error hiển thị Toast/Notice thay vì im lặng fail.

**Non-Goals:**
- Không thay đổi thiết kế UI/UX hay thêm mới tính năng.
- Không cấu trúc lại thư mục frontend (chỉ dọn dẹp data và chỉnh sửa nhỏ).
- Không can thiệp sâu vào Database Schema ở backend nếu không bắt buộc.

## Decisions

1. **Giữ nguyên Axios / Native Fetch client (`core/api/client.ts`)**
   - Rationale: Dự án đã có sẵn module cấu hình API call kèm Error Handling cơ bản. Chúng ta chỉ việc sử dụng đúng thay vì đưa thư viện khác (như TanStack Query) vào thời điểm này để tránh phình scope.
   - Alternatives: Áp dụng TanStack Query (bị loại vì vượt quá scope alignment).

2. **Cách tiếp cận rà soát (Audit Approach)**
   - Tìm kiếm `mock`, `res || { data: [] }`, `res || { stats: ... }` trong thư mục `src/features`. Xoá bỏ các fallback che giấu lỗi thực sự. Lỗi cần được quăng lên (throw) để UI hiển thị.

3. **Cập nhật Test Specs**
   - Rationale: Các file `*.spec.ts` đang mock component và mock axios call với data cứng. Cần cập nhật test spy/mock để pass payload tương đồng với Laravel API.

## Risks / Trade-offs

- **[Risk] Lỗi Crash UI do mất Fallback Data**: Khi bỏ `res || []`, nếu backend trả 500 hoặc 404, UI có thể bị vỡ nếu không có nullish coalescing hoặc skeleton loading.
  - **Mitigation**: Kiểm tra các Component tiêu thụ dữ liệu xem đã sử dụng v-if/v-else cho Loading/Error states chưa. Nếu chưa có thể bổ sung v-if guard nhanh.
- **[Risk] Data Schema Backend không khớp**: Backend trả về `snake_case` (e.g., `kyc_code`), Frontend đang xài `camelCase` (e.g., `kycCode`).
  - **Mitigation**: Kiểm tra Resource ở Laravel (e.g. `HostKycResource.php`) xem đã map thành camelCase chưa, hoặc frontend dùng axios transformer / tự map lại. Do project có vẻ đã quy hoạch chuẩn ở `HostKycResource`, ta ưu tiên để Laravel map key.
