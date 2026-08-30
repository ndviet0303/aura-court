## Why

Hiện tại, nhiều tính năng trên frontend có thể đang sử dụng mock data, hoặc các API Client chưa đồng bộ hoàn toàn với Request Validation và Resource Schema ở backend (như Admin KYC, Finance Reconciliation). Việc "khớp API cho toàn bộ API" là cần thiết để đảm bảo tính nhất quán, ngăn chặn lỗi runtime khi ráp nối giữa frontend và backend, đồng thời chuyển đổi hoàn toàn ứng dụng sang sử dụng dữ liệu thực từ server, từ đó phản ánh chính xác nghiệp vụ hệ thống.

## What Changes

- Rà soát toàn bộ các thư mục `api` trên frontend (như `frontend/src/features/*/api/*.ts`).
- Đối chiếu các payload và response type ở frontend với backend tương ứng (Laravel FormRequests & JsonResources).
- Gỡ bỏ mock data, mock handlers (nếu có trong MSW) hoặc fallback mock data trong logic frontend.
- Bổ sung xử lý lỗi (error handling) cho các HTTP status code phổ biến (422, 401, 500) khi gọi API thật.
- Cập nhật các Unit Test liên quan (ví dụ `AdminKycVerificationView.spec.ts`) để mock client gọi API thay vì mock dữ liệu tĩnh trong component.

## Capabilities

### New Capabilities
- `api-alignment`: Khớp toàn bộ API frontend và backend, gỡ bỏ mock data và chuẩn hoá việc xử lý request/response.

### Modified Capabilities

## Impact

- **Mã nguồn**: Thay đổi lớn trong `frontend/src/features/*/api/*.ts` và các components/composables gọi API.
- **Backend**: Có thể cần điều chỉnh nhẹ ở Laravel Resources nếu phát hiện schema không khớp với nhu cầu của UI.
- **Hệ thống**: Không làm thay đổi nghiệp vụ hệ thống mà chỉ đảm bảo dữ liệu thực chạy thông suốt giữa 2 đầu.
