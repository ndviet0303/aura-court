## Why

Codebase hiện tại có nhiều anti-patterns nghiêm trọng cản trở khả năng bảo trì và mở rộng:

- **Frontend**: 42 view files monolithic (2,000-7,500 lines mỗi file) chứa trộn lẫn template, business logic, API calls, inline types, và styles — không thể test, review, hoặc reuse.
- **Backend**: Fat controllers (350+ lines), god services (300+ lines) trộn validation, business logic, DB queries, và notifications trong cùng một method. Thiếu interfaces, DTOs, Form Requests.

Refactor theo Clean Architecture để tách concerns, giảm coupling, và tăng testability mà **không thay đổi behavior hay API contracts**.

## What Changes

### Frontend (Phase 1 — thực hiện trước)
- Tách 42 monolithic views thành thin view orchestrators + domain components + composables
- Tạo feature-based module structure: `features/{domain}/` với `api/`, `composables/`, `components/`, `types/`, `views/`
- Extract inline types từ views vào `features/{domain}/types/`
- Extract API calls từ views/stores vào `features/{domain}/api/{domain}Api.ts`
- Tạo shared composables: `useApiCall`, `usePagination`, `useFormValidation`
- Di chuyển shared components vào `shared/components/`
- Di chuyển `lib/api.ts` → `core/api/client.ts` (re-export cho backward compat)

### Backend (Phase 2 — sau khi FE xong)
- Tạo `Domain/{Feature}/Actions/` — extract business logic từ god services thành single-responsibility Action classes
- Tạo `Domain/{Feature}/DTOs/` — typed readonly data objects thay cho raw arrays
- Tạo `Http/Requests/{Feature}/` — Form Request classes thay cho inline `$request->validate()`
- Tạo `Http/Resources/` — API Resource transformers thay cho raw model returns
- Tách fat controllers theo domain: `AdminController` → `Admin/VenueController`, `Admin/CourtController`, `Admin/ReportController`
- Tạo `Domain/{Feature}/Events/` — domain events cho side effects (notifications, audit)
- Thêm interfaces trong `Contracts/` cho tất cả services

## Capabilities

### New Capabilities

_None — this is a pure structural refactor with no new behavior._

### Modified Capabilities

_None — no spec-level behavior changes. All existing API contracts, routes, and user-visible behavior remain identical._

This change is a pure refactor (structural reorganization only). Setting `skip_specs: true` in `.openspec.yaml`.

## Impact

- **Frontend code**: All 42 views, 17 components, 5 stores, `lib/` directory will be restructured. Import paths change but functionality is preserved.
- **Backend code**: All 24 controllers, 42 services reorganized into domain modules. Namespace paths change but API routes and responses remain identical.
- **No API changes**: All HTTP endpoints, request/response shapes, and authentication flows are preserved.
- **No DB changes**: No migrations, no model schema changes.
- **Tests**: Existing tests may need import path updates. New structure enables better unit testing.
- **Build/Deploy**: No changes to Docker, CI/CD, or Cloudflare Workers config.
