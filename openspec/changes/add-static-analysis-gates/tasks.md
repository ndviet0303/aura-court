## 1. Sửa lỗi chặn và lỗi crash

- [ ] 1.1 Đóng thẻ `<div class="qr-box">` trong `V2ReservationQrModal.vue`.
  - Kiểm tra lại modal QR: phần tóm tắt lịch và footer nằm đúng vị trí.
  - Cả 323 SFC parse không lỗi bằng `@vue/compiler-sfc`.
- [ ] 1.2 Sửa import trong 3 file:
  - `AdvancedBookingController`: thêm `Organization`.
  - `OrdinaryCheckoutService`: thêm `Coupon`.
  - `SplitPaymentService`: `LegacyBookingBackfillService` phải trỏ tới `App\Services\Reservation`.
  - Với nhánh nào chưa có test đi qua thì viết test hồi quy cho nhánh đó.
- [ ] 1.3 Xoá 4 action chết: `SnapshotSettlement`, `OpenShift`, `CloseShift`, `JoinMatch`. Trước khi xoá, grep xác nhận chúng có 0 tham chiếu.
- [ ] 1.4 Thay `env()` trong 3 lệnh E2E (`E2EArrangeTestDataCommand`, `E2EExpireHoldsCommand`, `E2EInspectTargetCommand`) bằng `config()` theo design §4. Hành vi guard phải giữ nguyên khi chưa cache config.

## 2. Format toàn repo

- [ ] 2.1 Chạy `vendor/bin/pint` trong `backend/` và commit riêng, commit chỉ chứa output của Pint. Sau đó `vendor/bin/pint --test` pass.
- [ ] 2.2 Sửa script Prettier:
  - mở rộng `format` sang `src/`, `e2e/` và các file config ở gốc `frontend/`;
  - thêm `format:check` với cùng phạm vi.
- [ ] 2.3 Chạy `npm run format` và commit riêng. Sau đó `npm run format:check` pass.
- [ ] 2.4 Sau commit format, xác nhận:
  - `npm run type-check`, `npm run test:unit -- --run` và `npm run build-only` đều pass;
  - smoke các màn booking, admin và partner không lệch layout.
- [ ] 2.5 Tạo `.git-blame-ignore-revs`:
  - chứa SHA của hai commit format;
  - có comment hướng dẫn `git config blame.ignoreRevsFile .git-blame-ignore-revs`.

## 3. Sửa lint frontend

- [ ] 3.1 Tắt `vitest/require-mock-type-parameters` trong `.oxlintrc.json`.
- [ ] 3.2 Bỏ mutate prop trong `CourtFormModal.vue`, `BusinessAddressSection.vue`, `BusinessBankSection.vue`:
  - mỗi field được sửa dùng một `defineModel`;
  - parent (`AdminManageView.vue`, `BusinessInfoStep.vue`) bind bằng `v-model:<field>`;
  - giữ nguyên hành vi lưu/huỷ;
  - thêm hoặc cập nhật unit test.
- [ ] 3.3 Sửa các lỗi nhỏ còn lại:
  - 61 ESLint `@typescript-eslint/no-unused-vars` và 50 oxlint `no-unused-vars`;
  - 2 `prefer-const`, 2 `@typescript-eslint/no-empty-object-type`;
  - 1 `vue/no-deprecated-slot-attribute`, 1 `unicorn/no-useless-fallback-in-spread`.
- [ ] 3.4 Sửa test có điều kiện và biểu thức hằng:
  - 12 `vitest/no-conditional-expect`;
  - 4 `no-constant-condition` trong `e2e/` (dạng `('post') === 'post' ? ...`).
- [ ] 3.5 Kết quả mong đợi:
  - `npx oxlint .` báo 0 lỗi;
  - `npx eslint .` chỉ còn `@typescript-eslint/no-explicit-any` (508).

## 4. Baseline cho nợ còn lại

- [ ] 4.1 Chạy `composer require --dev larastan/larastan:^3` trong `backend/`. Commit `composer.json` và `composer.lock`.
- [ ] 4.2 Tạo `phpstan-level0.neon`:
  - extension Larastan và Carbon;
  - `paths: [app/]`;
  - `parseModelCastsMethod: true`;
  - `reportIgnoresWithoutComments: true`;
  - `level: 0`.
- [ ] 4.3 Tạo `phpstan.neon`: include `phpstan-level0.neon` và `phpstan-baseline.neon`, đặt `level: 5`.
- [ ] 4.4 Thêm 3 inline `@phpstan-ignore <identifier> (<lý do>)` theo design §4:
  - `SplitGroup` và `SplitShare`: `rules.modelAppends`;
  - `E2ESeedFixturesCommand`: `larastan.console.undefinedOption`.
- [ ] 4.5 Chạy `vendor/bin/phpstan analyse -c phpstan-level0.neon --memory-limit=2G`: phải báo 0 lỗi.
- [ ] 4.6 Sinh `phpstan-baseline.neon` bằng `vendor/bin/phpstan analyse --memory-limit=2G --generate-baseline`:
  - khoảng 1.030 entry;
  - không có entry nào trong 4 file action đã xoá;
  - chạy `vendor/bin/phpstan analyse --memory-limit=2G` sau đó pass.
- [ ] 4.7 Sinh `frontend/eslint-suppressions.json` bằng `npx eslint . --suppress-rule @typescript-eslint/no-explicit-any`. Sau đó `npx eslint .` pass.
- [ ] 4.8 Kiểm chứng baseline chỉ giảm:
  - sửa thử một lỗi đã ghi thì PHPStan và ESLint đều fail vì entry thừa;
  - thêm một `any` mới vào file đã có suppression thì ESLint fail;
  - hoàn tác các thay đổi thử.

## 5. Kiến trúc toàn repo

- [ ] 5.1 Viết lại test kiến trúc backend thành `tests/Unit/Architecture/BackendArchitectureTest.php`, quét toàn bộ `app/`:
  - giữ đúng các luật hiện có, đo theo cặp (luật, file);
  - luật "query model trực tiếp" dùng mọi class trong `app/Models`;
  - gom tất cả vi phạm rồi fail một lần, mỗi dòng báo lỗi dán thẳng được vào allowlist.
- [ ] 5.2 Thêm allowlist backend dạng `(luật, file, ceiling)`:
  - vượt ceiling thì fail;
  - entry đã về dưới giới hạn (stale) thì fail.
- [ ] 5.3 Sinh allowlist backend từ lần chạy đầu sau commit Pint. Sau đó `vendor/bin/phpunit tests/Unit/Architecture` pass mà không cần database.
- [ ] 5.4 Tạo `frontend/src/__tests__/architecture.spec.ts` và allowlist cùng thư mục, quét toàn `src/` với các luật:
  - view ≤ 250 dòng, phần script ≤ 200 dòng;
  - component ≤ 400 dòng;
  - không import raw API client hoặc `@/stores/` từ `.vue`;
  - không thêm store mới vào `src/stores/`;
  - route phải lazy-load.
- [ ] 5.5 Sinh allowlist frontend từ lần chạy đầu sau commit Prettier. Sau đó `npm run test:unit -- --run` pass.
- [ ] 5.6 Kiểm chứng ratchet trên cả hai stack:
  - thêm một dòng vào file đang ở đúng ceiling thì fail;
  - một component mới dài 401 dòng thì fail;
  - một file đã đạt chuẩn mà vẫn còn entry thì fail;
  - hoàn tác các thay đổi thử.
- [ ] 5.7 Xoá `backend/scripts/check-backend-architecture.php` và `frontend/scripts/check-frontend-architecture.mjs`. Grep xác nhận không còn tham chiếu.

## 6. Tooling và tài liệu

- [ ] 6.1 Tạo `frontend/tsconfig.worker.json` cho `worker.ts` (types `@cloudflare/workers-types`) và thêm vào `references` của `tsconfig.json`.
  - Kiểm tra: thêm thử một lỗi type vào `worker.ts` thì `npm run type-check` fail; sau đó hoàn tác.
- [ ] 6.2 Thêm test parity key hai chiều vi/en vào `src/i18n/__tests__/bilingual.spec.ts`.
  - Kiểm tra: xoá thử một key thì test fail và báo đúng tên key.
  - Sau đó xoá `check_i18n.ts` ở root repo.
- [ ] 6.3 Thêm `.nvmrc` (`22`) ở root repo.
- [ ] 6.4 Sửa `.agents/rules/frontend-architecture.md`:
  - đổi giới hạn component từ 300 thành 400 dòng, cho khớp với dòng 400 ở cuối file;
  - thêm mục "Static analysis": allowlist, cách hạ ceiling, `--prune-suppressions`.
- [ ] 6.5 Sửa `.agents/rules/backend-architecture.md`:
  - ghi rõ luật 300 dòng áp dụng cho cả `app/Services`, nhưng không bắt chuyển chúng sang Actions;
  - thêm mục "Static analysis": allowlist, lệnh `--generate-baseline`, quy tắc baseline chỉ giảm, level 0 không bao giờ vào baseline.

## 7. Cổng CI và kiểm chứng

- [ ] 7.1 Thêm job `backend_static` vào `.gitlab-ci.yml`:
  - stage `validate`, image Composer chính thức, cùng `rules` với `frontend_check`;
  - chuẩn bị: copy `.env.example` sang `.env`, chạy `composer install --no-interaction --prefer-dist --ignore-platform-reqs`;
  - chạy lần lượt: `vendor/bin/pint --test`, PHPStan với `-c phpstan-level0.neon`, PHPStan với config mặc định, rồi `vendor/bin/phpunit tests/Unit/Architecture`.
- [ ] 7.2 Sửa `frontend_check`:
  - thêm `npx oxlint .`, `npx eslint .` và `npm run format:check` (không `--fix`, không cache), chạy trước bước test;
  - đổi `npm run build` thành `npm run build-only`.
- [ ] 7.3 Thêm `backend_static` vào `needs` của `build_and_deploy`.
- [ ] 7.4 Pipeline đầu tiên trên merge request:
  - cả hai job xanh;
  - số lỗi PHPStan trong CI khớp local (nếu lệch thì pin image theo PHP minor của production);
  - ghi lại thời gian validate tăng thêm.
- [ ] 7.5 Chạy `openspec validate add-static-analysis-gates --strict`, rồi cập nhật số liệu cuối vào `design.md`: số entry baseline, suppressions và allowlist.
