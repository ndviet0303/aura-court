## 1. Sửa lỗi chặn và lỗi crash

- [x] 1.1 Đóng thẻ `<div class="qr-box">` trong `V2ReservationQrModal.vue`.
  - Kiểm tra lại modal QR: phần tóm tắt lịch và footer nằm đúng vị trí.
  - Cả 323 SFC parse không lỗi bằng `@vue/compiler-sfc`.
- [x] 1.2 Sửa import trong 3 file:
  - `AdvancedBookingController`: thêm `Organization`.
  - `OrdinaryCheckoutService`: thêm `Coupon`.
  - `SplitPaymentService`: `LegacyBookingBackfillService` phải trỏ tới `App\Services\Reservation`.
  - Với nhánh nào chưa có test đi qua thì viết test hồi quy cho nhánh đó.
  - Đã làm: test cho fallback tổ chức của `listings` và cho split payment xác nhận legacy booking; cả hai fail trước khi sửa. Nhánh `Coupon::GROUP_ALL` không chạy tới được, vì `eligibility_group` của coupon và campaign đều NOT NULL, default `all_customers`. Vì vậy không viết test cho nhánh này; PHPStan level 0 chặn lỗi tái phát.
- [x] 1.3 Xoá 4 action chết: `SnapshotSettlement`, `OpenShift`, `CloseShift`, `JoinMatch`. Trước khi xoá, grep xác nhận chúng có 0 tham chiếu.
- [x] 1.4 Thay `env()` trong 3 lệnh E2E (`E2EArrangeTestDataCommand`, `E2EExpireHoldsCommand`, `E2EInspectTargetCommand`) bằng `config()` theo design §4. Hành vi guard phải giữ nguyên khi chưa cache config.

## 2. Format toàn repo

- [x] 2.1 Chạy `vendor/bin/pint` trong `backend/` và commit riêng, commit chỉ chứa output của Pint. Sau đó `vendor/bin/pint --test` pass.
  - Đã làm: commit `897118f`. Bộ PHPUnit trước và sau Pint có cùng 21 test fail, đều fail sẵn trên `main`.
- [x] 2.2 Sửa script Prettier:
  - mở rộng `format` sang `src/`, `e2e/` và các file config ở gốc `frontend/`;
  - thêm `format:check` với cùng phạm vi.
  - Đã làm: cả `format` cũng chạy `--no-cache`. Cache của experimental CLI bỏ qua lượt thứ hai, nên file cần hai lượt format không bao giờ hội tụ.
- [x] 2.3 Chạy `npm run format` và commit riêng. Sau đó `npm run format:check` pass.
  - Đã làm: commit `d426dfc`, gồm 502 file.
  - Trước đó tách 8 handler inline nhiều câu lệnh thành hàm, ở commit riêng `8eb81dc`. Với `semi: false`, Prettier xuống dòng các handler này mà không có `;`, và compiler của Vue báo lỗi.
- [x] 2.4 Sau commit format, xác nhận:
  - `npm run type-check`, `npm run test:unit -- --run` và `npm run build-only` đều pass;
  - smoke các màn booking, admin và partner không lệch layout.
  - Đã làm:
    - type-check, 386 unit test và build-only đều pass.
    - Spec kiến trúc scoped cũ fail trên code đã format, nên phần frontend của nhóm 5 được làm sớm (commit `c71fe28`), xem design §6.
    - Thay smoke tay bằng so sánh toàn bộ build trước/sau format (design §2): CSS giống hệt, 22 chunk JS chỉ khác khoảng trắng ở mép text, không màn nào đổi hiển thị.
- [x] 2.5 Tạo `.git-blame-ignore-revs`:
  - chứa SHA của hai commit format;
  - có comment hướng dẫn `git config blame.ignoreRevsFile .git-blame-ignore-revs`.
  - Lưu ý: nếu MR được squash hoặc rebase thì SHA đổi, phải cập nhật file này sau khi merge.

## 3. Sửa lint frontend

- [x] 3.1 Tắt `vitest/require-mock-type-parameters` trong `.oxlintrc.json`.
- [ ] 3.2 Bỏ mutate prop trong `CourtFormModal.vue`, `BusinessAddressSection.vue`, `BusinessBankSection.vue`:
  - mỗi field được sửa dùng một `defineModel`;
  - parent (`AdminManageView.vue`, `BusinessInfoStep.vue`) bind bằng `v-model:<field>`;
  - giữ nguyên hành vi lưu/huỷ;
  - thêm hoặc cập nhật unit test.
  - Đã làm: hai field dùng tuỳ chọn `set` của `defineModel`:
    - `bank_account_holder` viết hoa trong `set`, thay cho handler `@input`. Handler cũ đọc model trước khi parent kịp cập nhật, nên sẽ làm mất ký tự vừa gõ.
    - `capacity` đổi ô trống (`''`) thành `null`, để prop luôn đúng kiểu `number | null`. Payload lưu không đổi, vì `saveCourt` vốn đổi giá trị falsy thành `null`.
  - Test mới:
    - form sân qua `AdminManageView`: huỷ, mở lại, rồi lưu;
    - field địa chỉ và ngân hàng qua `BusinessInfoStep`.
    - Bỏ một trong hai `set` thì test tương ứng fail.
  - Ceiling: mỗi field thêm một dòng `v-model` ở parent, nên 3 file vượt ceiling và được đo lại trong cùng commit (design §6, Timing):
    - `AdminManageView` từ 322 lên 329;
    - `BusinessInfoStep` từ 459 lên 469;
    - `CourtFormModal` từ 457 lên 463.
- [x] 3.3 Sửa các lỗi nhỏ còn lại:
  - 61 ESLint `@typescript-eslint/no-unused-vars` và 50 oxlint `no-unused-vars`;
  - 2 `prefer-const`, 2 `@typescript-eslint/no-empty-object-type`;
  - 1 `vue/no-deprecated-slot-attribute`, 1 `unicorn/no-useless-fallback-in-spread`.
  - Đã làm (commit `18ae500`):
    - Hai tool báo trùng nhau, còn 104 chỗ unused-var phải sửa.
    - Prop `slot` của `MarketplaceSellModal` đổi thành `bookingSlot`. Lý do: `:slot` trên component bị hiểu là slot attribute đã deprecated.
    - Xoá code chết lộ ra khi sửa, ví dụ `persistPayment` (lời gọi đã bị bỏ từ refactor `f6fa328`).
    - Bỏ thêm một `eslint-disable` thừa trong `e2e/runner/playwright-global-setup.ts`.
    - Một callback test được gõ kiểu, nên `no-explicit-any` còn 507.
- [x] 3.4 Sửa test có điều kiện và biểu thức hằng:
  - 12 `vitest/no-conditional-expect`;
  - 4 `no-constant-condition` trong `e2e/` (dạng `('post') === 'post' ? ...`).
  - Đã làm:
    - Còn 9 `no-conditional-expect`, vì 3 cái kia nằm trong spec scoped cũ đã xoá ở `c71fe28`.
    - Bỏ guard `if (...exists())` quanh các expect. Có 3 test xanh giả: phần tử không tồn tại nên expect chưa từng chạy.
      - `ProfileView` (2 test): selector cũ `#prof-name` và `.save-profile-btn` không còn. Đổi sang `#profile-name` và submit form. Thêm kiểm tra thông báo thành công, và kiểm tra không gọi API khi thiếu tên.
      - `SocialConnectionsPanel`: ô nhập ID chỉ có ở tab Bạn bè, nên test quay lại tab này trước khi gửi lời mời.
    - 4 biểu thức hằng: chỉ giữ nhánh luôn chạy (`/social/feed/{id}/react` và `/report`).
- [x] 3.5 Kết quả mong đợi:
  - `npx oxlint .` báo 0 lỗi;
  - `npx eslint .` chỉ còn `@typescript-eslint/no-explicit-any` (507; lúc đề xuất là 508, xem 3.3).

## 4. Baseline cho nợ còn lại

- [x] 4.1 Chạy `composer require --dev larastan/larastan:^3` trong `backend/`. Commit `composer.json` và `composer.lock`.
  - Đã làm: Larastan v3.13.0 và PHPStan 2.3.1, kèm `iamcal/sql-parser`.
  - `composer audit` báo 5 advisory ở 4 package có sẵn (`laravel/framework`, `league/commonmark`, `league/flysystem`, `phpseclib/phpseclib`). Larastan không gây ra advisory nào; xử lý chúng nằm ngoài change này.
- [x] 4.2 Tạo `phpstan-level0.neon`:
  - extension Larastan và Carbon;
  - `paths: [app/]`;
  - `parseModelCastsMethod: true`;
  - `reportIgnoresWithoutComments: true`;
  - `level: 0`.
- [x] 4.3 Tạo `phpstan.neon`: include `phpstan-level0.neon` và `phpstan-baseline.neon`, đặt `level: 5`.
- [x] 4.4 Thêm 3 inline `@phpstan-ignore <identifier> (<lý do>)` theo design §4:
  - `SplitGroup` và `SplitShare`: `rules.modelAppends`;
  - `E2ESeedFixturesCommand`: `larastan.console.undefinedOption`.
- [x] 4.5 Chạy `vendor/bin/phpstan analyse -c phpstan-level0.neon --memory-limit=2G`: phải báo 0 lỗi.
- [x] 4.6 Sinh `phpstan-baseline.neon` bằng `vendor/bin/phpstan analyse --memory-limit=2G --generate-baseline`:
  - khoảng 1.030 entry;
  - không có entry nào trong 4 file action đã xoá;
  - chạy `vendor/bin/phpstan analyse --memory-limit=2G` sau đó pass.
  - Đã làm: 769 entry, gộp 1.021 lỗi. Con số khoảng 1.030 trong design được đo trước nhóm 1.
- [x] 4.7 Sinh `frontend/eslint-suppressions.json` bằng `npx eslint . --suppress-rule @typescript-eslint/no-explicit-any`. Sau đó `npx eslint .` pass.
  - Đã làm: 507 `any` trong 112 file.
  - ESLint ghi file này không có newline cuối, mà `format:check` lại quét mọi `*.json` ở gốc `frontend/`. Vì vậy thêm `frontend/.prettierignore` để Prettier bỏ qua file này.
- [x] 4.8 Kiểm chứng baseline chỉ giảm:
  - sửa thử một lỗi đã ghi thì PHPStan và ESLint đều fail vì entry thừa;
  - thêm một `any` mới vào file đã có suppression thì ESLint fail;
  - hoàn tác các thay đổi thử.
  - Đã kiểm chứng:
    - Bỏ một `?->` đã ghi baseline: PHPStan exit 1 (`was not matched in reported errors`).
    - Gõ kiểu một `any` đã suppress: ESLint exit 2, vì còn suppression thừa.
    - Thêm `any` mới vào file đã có suppression: ESLint exit 1.
    - Đã hoàn tác cả ba.

## 5. Kiến trúc toàn repo

- [ ] 5.1 Viết lại test kiến trúc backend thành `tests/Unit/Architecture/BackendArchitectureTest.php`, quét toàn bộ `app/`:
  - giữ đúng các luật hiện có, đo theo cặp (luật, file);
  - luật "query model trực tiếp" dùng mọi class trong `app/Models`;
  - gom tất cả vi phạm rồi fail một lần, mỗi dòng báo lỗi dán thẳng được vào allowlist.
- [ ] 5.2 Thêm allowlist backend dạng `(luật, file, ceiling)`:
  - vượt ceiling thì fail;
  - entry đã về dưới giới hạn (stale) thì fail.
- [ ] 5.3 Sinh allowlist backend từ lần chạy đầu sau commit Pint. Sau đó `vendor/bin/phpunit tests/Unit/Architecture` pass mà không cần database.
- [x] 5.4 Tạo `frontend/src/__tests__/architecture.spec.ts` và allowlist cùng thư mục, quét toàn `src/` với các luật:
  - view ≤ 250 dòng, phần script ≤ 200 dòng;
  - component ≤ 400 dòng;
  - không import raw API client hoặc `@/stores/` từ `.vue`;
  - không thêm store mới vào `src/stores/`;
  - route phải lazy-load.
  - Đã làm: cách đo từng luật ghi ở design §6. Spec scoped cũ `src/features/__tests__/architecture.spec.ts` đã bị xoá cùng script.
- [x] 5.5 Sinh allowlist frontend từ lần chạy đầu sau commit Prettier. Sau đó `npm run test:unit -- --run` pass.
  - Đã làm: 172 entry. Gồm 101 component, 36 view, 2 phần script của view, 21 file import legacy store, 6 file import raw API, 5 legacy store và 1 router có 17 route eager.
- [ ] 5.6 Kiểm chứng ratchet trên cả hai stack:
  - thêm một dòng vào file đang ở đúng ceiling thì fail;
  - một component mới dài 401 dòng thì fail;
  - một file đã đạt chuẩn mà vẫn còn entry thì fail;
  - hoàn tác các thay đổi thử.
  - Frontend đã kiểm chứng: cả ba trường hợp trên đều fail; thêm store mới hoặc route eager mới cũng fail. Còn backend.
- [ ] 5.7 Xoá `backend/scripts/check-backend-architecture.php` và `frontend/scripts/check-frontend-architecture.mjs`. Grep xác nhận không còn tham chiếu.
  - Frontend đã xoá cả script lẫn spec scoped cũ (commit `c71fe28`). Còn script backend.

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
