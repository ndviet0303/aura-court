## Purpose

Đảm bảo toàn bộ Frontend API Client gọi dữ liệu thực từ Backend (Laravel) một cách nhất quán, không dùng mock data ở production/staging, và xử lý đúng các HTTP Status Codes phổ biến.

## Requirements

### Requirement: Tích hợp Real API
Tất cả các hàm gọi API ở Frontend (thông qua `api` hoặc `apiV2` client) MUST gửi request tới các endpoint backend đã được định nghĩa trong route, và phản hồi từ server phải được hứng và truyền đúng format tới UI Components.

#### Scenario: Gọi thành công
- **WHEN** Frontend trigger API call (ví dụ KYC Queue list)
- **THEN** Dữ liệu thực từ database được backend trả về, map đúng Interface (e.g. `HostKyc[]`) và hiển thị lên UI

### Requirement: Gỡ bỏ Mock Data
Tất cả mock data cứng, MSW handlers (nếu có) dùng cho business logic MUST được gỡ bỏ khỏi luồng chạy ứng dụng. Trừ trường hợp Unit Tests có thể sử dụng `vi.mock()`.

#### Scenario: Chạy ứng dụng trên local/staging
- **WHEN** Người dùng thao tác ứng dụng
- **THEN** Không có response nào trả về từ hardcoded static mock data

### Requirement: Xử lý Lỗi Cơ Bản (Error Handling)
Ứng dụng SHALL bắt và xử lý các lỗi 401, 422, 500 khi API thật gặp vấn đề, hiện thông báo cho user thông qua Notice/Toast/Alert thay vì throw error trắng trang.

#### Scenario: Validation Error 422
- **WHEN** Backend từ chối dữ liệu (ví dụ reject KYC không có lý do hợp lệ)
- **THEN** UI hiển thị lỗi chi tiết trả về từ Backend (errors payload)

#### Scenario: Unauthorized Error 401
- **WHEN** Token/Session hết hạn hoặc người dùng không có quyền
- **THEN** UI báo lỗi hoặc redirect về trang login

### Requirement: Authoritative mutation outcomes
Business UI SHALL show success, clear drafts or change displayed persisted inventory only after the server confirms the operation. Rejected requests SHALL retain user input and display the error; ambiguous network failures SHALL allow lookup or same-key retry without duplicate effects. Business data placeholders SHALL be absent from operational paths.

#### Scenario: POS request fails
- **WHEN** the order API rejects a sale
- **THEN** no success receipt appears and the cart remains available

#### Scenario: Lost response
- **WHEN** the server commits but the response is lost
- **THEN** retrying the logical operation retrieves its existing outcome

### Requirement: Capability-aware interface
The interface SHALL reflect effective organization permissions and feature availability. Disabled capabilities SHALL be clearly unavailable, not replaced by synthetic successful behavior; direct API access SHALL remain guarded.

#### Scenario: Disabled POS
- **WHEN** POS is disabled for the selected organization
- **THEN** the UI explains unavailability and cannot simulate a completed sale

### Requirement: No cancellation or rescheduling interface
Customer and operational booking interfaces SHALL omit whole-booking cancellation, partial-slot cancellation and court/date/time rescheduling actions, dialogs and success flows. Booking-related customer copy in Vietnamese and English SHALL clearly state that cancellation and rescheduling are unsupported, including court detail and checkout, and MUST NOT advertise free cancellation, flexible rescheduling, automatic cancellation refunds or membership cancellation exemptions. Back-navigation, editing an unsubmitted selection and automatic expiry SHALL remain distinct from cancellation of a persisted booking.

#### Scenario: Customer views booking history or a held reservation
- **WHEN** a customer opens V1 history, V2 reservation detail or a legacy held-reservation banner
- **THEN** no cancellation/rescheduling control SHALL be available, while supported payment, detail and check-in actions remain available where eligible

#### Scenario: Customer reviews checkout policy
- **WHEN** a customer views court detail or checkout in Vietnamese or English
- **THEN** the policy SHALL state that booking cancellation and rescheduling are unsupported without promising a refund or change window

#### Scenario: Historical cancelled booking is displayed
- **WHEN** a customer views an old cancelled, expired or refunded booking
- **THEN** its recorded status SHALL remain readable and SHALL NOT be relabelled as confirmed or made actionable for cancellation/rescheduling

### Requirement: Retired cancellation-refund administration
The platform SHALL remove approval/rejection controls and actionable queue indicators for booking-cancellation refunds. PATCH `/api/v1/admin/refunds/{refund}` SHALL be retired and return HTTP 404 for otherwise valid authenticated requests without updating refunds, bookings, wallets or journals. Existing cancellation-refund records, including pending records, SHALL remain available through authorized read-only history and SHALL NOT be automatically approved, rejected or paid during rollout. Unrelated financial dispute/compensation interfaces SHALL remain governed by their existing contracts and SHALL NOT be relabelled as replacement booking-cancellation actions.

#### Scenario: Administrator attempts old refund approval
- **WHEN** an administrator sends an approval or rejection to the retired cancellation-refund endpoint
- **THEN** the request SHALL return HTTP 404 without changing the refund status, booking or financial state

#### Scenario: Pending historical refund is viewed
- **WHEN** an authorized operator views a pending cancellation-refund record created before retirement
- **THEN** its original status and references SHALL be visible as read-only history without approval/rejection actions or an instruction to process it in the removed workflow

#### Scenario: Finance opens an unrelated dispute
- **WHEN** an authorized operator opens a financial dispute not originating from the retired cancellation/rescheduling workflow
- **THEN** the existing dispute handling capability SHALL remain available under its existing authorization and financial rules
