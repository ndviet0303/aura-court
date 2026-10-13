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

### Requirement: Report-aligned end-to-end contracts
Actual frontend/backend paths, methods, payload fields, upload names/types, enum values, consent versions and response structures SHALL agree for partner drafts/evidence/submission/corrections, subscriptions, venue setup and every affected business journey. Server-side request handlers SHALL resolve. Active organization/venue SHALL be explicit rather than hardcoded. Real reloads SHALL restore persisted state and errors SHALL preserve drafts. Data fixtures, local-only calendars, fake paid states and simulated successful exports MUST NOT appear as completed operational outcomes.

#### Scenario: Partner document submission
- **WHEN** an applicant uploads evidence and submits required consents from the actual UI
- **THEN** valid server requests SHALL persist it, and revision_required/pending/history fields SHALL render correctly

#### Scenario: Subscription journey
- **WHEN** an owner selects a plan, activates trial or initiates payment
- **THEN** the correct endpoints and fields SHALL return usable persisted status/payment instructions

#### Scenario: Failed split collection
- **WHEN** the API rejects a split/share operation or returns no real group
- **THEN** UI SHALL show error/empty state without sample paid members or success messages

#### Scenario: Organization context
- **WHEN** a manager switches organization and invites staff
- **THEN** the selected scope SHALL be sent and enforced instead of organization 1

#### Scenario: Unsupported export
- **WHEN** an export is unavailable or fails
- **THEN** UI SHALL not claim a generated file; supported exports SHALL provide an actual authorized downloadable artifact

### Requirement: Policy-aware changes and truthful finance interfaces
Customer/owner/staff booking pages and Vietnamese/English policies SHALL show captured change/cancellation terms, fees, owner refund responsibilities and actual request/evidence status. Success SHALL require persisted server confirmation; a dispute judgment or owner-reported transfer SHALL not be labelled verified refund completion. Capability availability SHALL combine permissions, subscriptions, provider readiness and rollout state.

#### Scenario: Cancellation request
- **WHEN** an eligible customer previews and submits a cancellation
- **THEN** the UI SHALL show the accepted policy calculation and request status, not a blanket unsupported message or immediate refund claim

#### Scenario: Provider not ready
- **WHEN** an owner has not verified SePay
- **THEN** online payment SHALL be unavailable with setup guidance, without demo success or a QR to Aura Court's account

### Requirement: Administrative venue lists use the supported API contract
Administrative pages that require the existing venue list SHALL obtain it from the supported `/api/admin/venues` contract under its existing authorization. They MUST NOT silently substitute an empty successful result when loading fails.

#### Scenario: Venue and court management load
- **WHEN** an authorized administrator opens `/admin/venues` or `/admin/manage`
- **THEN** the venue list SHALL load through the supported route and render its returned data without a route-not-found error

#### Scenario: Shared venue consumers load
- **WHEN** an authorized user opens staff, schedule, organization, pricing or shift reconciliation functionality that uses the same administrative venue-list contract
- **THEN** that functionality SHALL use the supported route with the existing response shape and authorization

### Requirement: Bilingual actionable error dialogs
Blocking API failures in venue/court management, organization access, POS and customer loyalty checkout SHALL produce one actionable dialog in the selected Vietnamese or English locale. Capability-disabled, unauthorized, unauthenticated, validation/conflict, not-found, network and server failures SHALL be distinguishable through stable codes or explicit status mappings. All dialog titles, bodies, buttons, detail headings and accessibility labels SHALL be localized. Unknown failures MUST NOT expose stack traces, database messages or internal route names. Field validation SHALL retain its inline guidance where applicable; informational notices need not become dialogs.

#### Scenario: Organization capability is disabled
- **WHEN** a guarded request is rejected because the selected organization's capability is disabled
- **THEN** the dialog SHALL explain that the feature has not been enabled for this organization, rather than claiming the route is missing or the user's password is invalid

#### Scenario: Locale changes
- **WHEN** the user selects Vietnamese or English before a failure or while its dialog is open
- **THEN** the dialog's text and controls SHALL use that locale without untranslated keys or fixed text from the other locale

#### Scenario: Concurrent failures
- **WHEN** several requests from the same screen fail for the same underlying capability condition
- **THEN** the interface SHALL show one dismissible dialog rather than stacked duplicates and SHALL preserve entered data or the POS cart

#### Scenario: Keyboard interaction
- **WHEN** a blocking error dialog opens
- **THEN** focus SHALL move into the dialog, remain within it while open, allow keyboard dismissal, and return to a sensible originating control after closing

#### Scenario: Unknown service failure
- **WHEN** an API returns an unrecognized internal error
- **THEN** the interface SHALL show a localized safe fallback and a support/reference code when available without presenting raw infrastructure details

### Requirement: POS loading respects resolved organization capabilities
The POS interface SHALL distinguish loading, enabled, disabled and failed capability resolution. It SHALL resolve capabilities for the active organization before starting dependent POS requests. Capability state SHALL be invalidated on logout and organization changes; a fetch failure MUST NOT be presented as a definitive feature-disabled result or reuse another organization's permissions. Direct API requests SHALL remain guarded independently of the interface.

#### Scenario: Disabled POS on entry
- **WHEN** the selected organization has POS disabled
- **THEN** the screen SHALL explain the disabled capability, avoid dependent POS loads and prevent checkout without fabricating a successful sale

#### Scenario: Capability lookup fails
- **WHEN** the capabilities request fails due to network or server error
- **THEN** the screen SHALL show a localized retryable failure and SHALL NOT conclude that the organization has no POS entitlement

#### Scenario: Organization changes
- **WHEN** a user switches from a POS-enabled organization to a disabled organization
- **THEN** stale capabilities and POS data SHALL NOT authorize operations in the new organization and late responses from the old context SHALL NOT replace the current context
