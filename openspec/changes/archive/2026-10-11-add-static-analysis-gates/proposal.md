## Why

Repo đã có đủ công cụ static (Pint, ESLint, oxlint, Prettier, vue-tsc, hai checker kiến trúc), nhưng CI chỉ gác `vue-tsc`. Các công cụ còn lại không chạy tự động nên code đã trôi xa khỏi quy ước: 293/767 file PHP lệch Pint, 597 lỗi ESLint, khoảng 500 file frontend chưa format. Hai checker kiến trúc chỉ soi 2–3 feature nên báo "pass", trong khi toàn repo có hàng trăm vi phạm. Backend chưa từng có job kiểm tra nào trong CI.

Phép đo ngày 2026-10-11 cho thấy nợ đang lọt thành lỗi thật:
- Larastan level 0 tìm ra 3 nhánh sẽ crash khi chạy tới: thiếu/sai `use` import trong checkout coupon, xác nhận split payment cho legacy booking, và nhánh fallback tổ chức.
- `V2ReservationQrModal.vue` thiếu `</div>` nên vỡ layout, nhưng build vẫn xanh.
- Refactor `75713bb` đưa thêm `vue/no-mutating-props` vào mà không công cụ nào chặn.

Chủ dự án chọn mục tiêu **dọn sạch codebase**. Dọn mà không có cổng chặn thì nợ sẽ mọc lại, nên change này dựng cổng trước: nợ hiện có được ghi vào baseline và chỉ được phép giảm.

## What Changes

- Sửa trước các lỗi chặn và lỗi có thể crash: đóng thẻ trong `V2ReservationQrModal.vue`; sửa 3 import sai trong `AdvancedBookingController`, `OrdinaryCheckoutService`, `SplitPaymentService`; xoá 4 Domain Action chết trỏ tới model không tồn tại; thay `env()` gọi ngoài config trong 3 lệnh E2E.
- Format toàn repo một lần: Pint (preset laravel) cho backend; Prettier cho `frontend/src`, `frontend/e2e` và file config. Mỗi bên một commit riêng, không đổi hành vi, SHA ghi vào `.git-blame-ignore-revs`.
- Sửa ngay các lỗi lint nhỏ: 23 `vue/no-mutating-props`, 61 + 50 biến/import thừa (ESLint + oxlint), 12 `no-conditional-expect`, 4 `no-constant-condition` và vài lỗi lặt vặt. Tắt rule oxlint `vitest/require-mock-type-parameters` (327 lỗi, chỉ nằm trong test). Sau bước này ESLint chỉ còn `no-explicit-any` (508), được ghi vào suppressions.
- Thêm Larastan level 5. Bật cho Larastan đọc `casts()` của model: không có cờ này, cast `datetime`/`array` bị hiểu thành `string` và sinh 286 false positive (1.343 → 1.057 lỗi). Ghi khoảng 1.030 lỗi còn lại vào baseline. Riêng level 0 bắt buộc bằng 0, không được đưa vào baseline. 3 false positive đã kiểm chứng được ignore ngay tại dòng code, kèm lý do.
- Kiến trúc theo phương án B: áp luật cho toàn repo, vi phạm cũ nằm trong allowlist có trần số dòng theo từng file, entry đã hết vi phạm thì làm fail. Giới hạn component là 400 dòng. Mỗi stack chỉ giữ một checker (backend: PHPUnit; frontend: vitest), bỏ script trùng.
- CI: thêm job `backend_static` không cần database (Pint `--test`, PHPStan level 5 có baseline, PHPStan level 0 không baseline, test kiến trúc); `frontend_check` thêm ESLint, oxlint, Prettier ở chế độ chỉ kiểm, bỏ lần type-check bị chạy lặp, và type-check thêm cả `worker.ts`. Deploy phụ thuộc cả hai job.
- Dọn tooling: gộp `check_i18n.ts` (đang nằm ở root repo, không ai gọi) vào test i18n rồi xoá; thêm `.nvmrc`; sửa doc kiến trúc frontend cho thống nhất (component 300 → 400).

## Capabilities

### New Capabilities

Không có.

### Modified Capabilities

- `platform-readiness`: bổ sung cổng static analysis trong pipeline, baseline nợ một chiều (chỉ giảm) và kiểm tra kiến trúc cho toàn repo. Các yêu cầu readiness/rollout/provider hiện có giữ nguyên.

## Impact

- Backend: thêm require-dev `larastan/larastan` ^3 (kéo theo `phpstan/phpstan` 2.3 và `iamcal/sql-parser`); thêm `phpstan.neon`, `phpstan-level0.neon`, `phpstan-baseline.neon`; khoảng 293 file được format; sửa import ở 3 file; xoá 4 action; sửa 3 lệnh E2E; test kiến trúc toàn repo kèm allowlist; xoá `scripts/check-backend-architecture.php`.
- Frontend: khoảng 500 file được format; 3 component bỏ mutate prop; khoảng 106 file sửa lint nhỏ; sửa `.oxlintrc.json`; thêm `eslint-suppressions.json`; thêm vitest spec kiến trúc kèm allowlist; tsconfig cho `worker.ts`; xoá `scripts/check-frontend-architecture.mjs`.
- CI: sửa `.gitlab-ci.yml`. Bước validate dự kiến chậm thêm khoảng 1–1,5 phút (đo local: Pint ~33s, PHPStan ~10s mỗi lần chạy, ESLint ~11s).
- Repo: thêm `.git-blame-ignore-revs`, `.nvmrc`; sửa `.agents/rules/frontend-architecture.md` và `.agents/rules/backend-architecture.md`; xoá `check_i18n.ts`.
- Không đổi API, dữ liệu hay hành vi nghiệp vụ, ngoại trừ sửa layout modal QR và các nhánh đang crash.
- Non-goals:
  - Dọn dần 508 `any`, allowlist kiến trúc và baseline Larastan: làm ở các change sau, chia theo domain.
  - Nâng Larastan lên level 6 trở lên.
  - Chuyển `app/Services` sang Domain Actions.
  - Đưa PHPUnit (cần PostgreSQL) vào CI: phải quyết định trước change dọn backend đầu tiên.
  - Bật lại e2e gate: đang xung đột với delta spec chưa archive của `add-playwright-functional-coverage`.
  - Nâng cấp các dependency có advisory (composer: 5, npm: 16): làm ở change riêng.
