# Review implementation: add-playwright-functional-coverage

Ngày review: **01/10/2026**, Asia/Ho_Chi_Minh. Phạm vi: working tree trên HEAD **`6f0e0b832543c58c6a5d971358ae037ebcc3c04a`**, gồm file modified và untracked của change.

## 1. Kết luận

**Chưa đủ điều kiện nghiệm thu change.** Có **21 finding: 6 P1 và 15 P2**. Full regression chạy thực tế có **155/157 lượt pass, 2 lượt fail**, không retry. Các lượt pass vẫn gồm nhiều ca kiểm tra API/service hoặc dữ liệu fixture, chưa chứng minh browser journey được ghi trong tasks/matrix.

Các vấn đề cần xử lý trước khi dùng suite làm acceptance gate:

- PHP helper có thể dùng database từ `DB_URL` kế thừa, vượt khỏi database mà runner đã kiểm tra.
- CI image chưa có runtime backend/database cần thiết; manual web regression vẫn có thể chọn job build/push/deploy.
- Required skip có thể tạo gate xanh; URL browser bị cố định trước khi runner chọn port.
- P0 refund/service-tab/logout và một số biến thể còn bypass thao tác UI hoặc thiếu assertion bắt buộc.
- Ma trận 126 FR đều ghi `Covered`, nhưng 54 dòng trỏ file browser không tồn tại; nhiều dòng không có test/step hoặc execution report tương ứng.

Report này là backlog sửa; review chỉ thêm report và evidence. Không chỉnh implementation hoặc checkbox trong `tasks.md`.

## 2. Phạm vi và cách kiểm chứng

Đã rà **65 file của change, 25.568 dòng tổng nội dung**, gồm planning artifacts, CI/runbook, runner/preflight/teardown/auth/test-data, 4 backend command, thay đổi runtime/UI và toàn bộ browser spec mới/chỉnh sửa. Đối chiếu các business boundary liên quan với current main specs, router/API caller, controller/domain service và regression backend. Số dòng này là kích thước file được rà, không phải số dòng diff.

Danh mục browser gồm **31 spec file, 149 test definition**: 24 P0, 96 P1, 17 P2 và 12 baseline không được chọn. Default run chọn 137 definition, cộng 20 lượt lặp của 5 smoke test trên 4 project phụ thành **157 executions**. Runner Node checks là nhóm riêng, được `testIgnore` khỏi browser suite.

Snapshot SHA-256 của từng file, inventory test và audit từng FR nằm trong [review-evidence.json](/Volumes/SSD/WorkSpace/ziet-project/booking/openspec/changes/add-playwright-functional-coverage/review-evidence.json). Snapshot đã được so lại trước khi viết report, không có source file thay đổi so với input review.

### Cách đọc mức độ và bằng chứng

- **P1**: cần sửa trước khi tin gate hoặc chạy suite trên môi trường có cấu hình kế thừa.
- **P2**: cần sửa để đạt full functional coverage và kết quả có thể tái hiện/đối chiếu.
- **Đã tái hiện**: có kết quả chạy/canary cụ thể. Canary cấu hình không đồng nghĩa đã gây mutation vào database ngoài run.
- **Đã đối chiếu code**: flow/assertion hiện tại chứng minh khoảng trống; chưa chạy live CI hoặc mutation testing cho mọi biến thể.

### Kết quả chạy

| Kiểm tra | Kết quả | Thời gian / giới hạn |
| --- | --- | --- |
| Frontend unit | **381/381 pass**, 75 file | 22,60 giây |
| Frontend build và type-check | **Pass** | Vite build 4,82 giây; type-check trong build |
| Runner Node checks | **21/21 pass** | 39,04 giây; tên check “zero egress” chưa chứng minh network deny, xem R17 |
| Backend regression liên quan | **34 test, 307 assertion pass**, 7 file | PHPUnit 3,608 giây; DB disposable riêng |
| P0 riêng trước full run | **24/24 pass** | Khoảng 2,2 phút; một clean run |
| Full browser regression | **155 pass, 2 fail**, 0 skip, 0 retry | 1 worker; JUnit tổng 1.438,706 giây, khoảng 23 phút 59 giây |
| Hai ca lỗi chạy lại trong run mới | **Cả hai fail** | Feed timeout cùng vị trí; availability timeout khi cleanup, xem bên dưới |
| Partner document upload chạy độc lập | **Fail** | `currentRes.data === null`, R07 |
| CSV export chạy độc lập | **Fail** | 0 data row, assertion line 283; R07 |
| Discovery không có active manifest | **Fail**, exit 1 | Sáu lỗi import; in `Total: 0 tests in 0 files`, R21 |

| Project trong full run | Executions | Pass | Fail/error |
| --- | ---: | ---: | ---: |
| p0 | 24 | 24 | 0 |
| p1 | 96 | 95 | 1 |
| p2 | 17 | 16 | 1 |
| smoke-chromium | 5 | 5 | 0 |
| smoke-firefox | 5 | 5 | 0 |
| smoke-webkit | 5 | 5 | 0 |
| smoke-mobile | 5 | 5 | 0 |

**Điều chỉnh môi trường review:** browser run và backend regression bỏ `DB_URL`/`DATABASE_URL` trong process gọi test để tránh R01; đây là biện pháp cho lần kiểm chứng này, chưa sửa runner. Backend regression dùng PHPUnit config tạm thay database `booking_test` bị force bằng DB riêng và storage riêng. Không chạy toàn bộ backend suite; 7 file đã chạy được liệt kê trong evidence.

### Hai lỗi của full run

1. `p1-community-feed-moderation.spec.ts`, test **9.2.3**, line **146**: timeout 60 giây tại `page.goto('/login')`, chờ `load`. Run riêng lặp lại cùng lỗi. Trace có request font bên ngoài; Material Symbols font chưa hoàn thành khi test kết thúc. Đây là dấu hiệu phụ thuộc mạng ngoài, **chưa chứng minh duy nhất font gây timeout**. Chưa chạy được phần reaction của ca này. Xem R17.
2. `p2-realtime-availability-tournament.spec.ts`, test **10.4.1**, line **64**: A chọn slot bắt đầu `07:00`, B chọn `.slot-cell-btn:has-text("07:00").first()` và lấy slot **06:00–07:00** đang occupied. Xem R15. Run riêng gặp timeout/`contextA.close` tại line 166; không dùng lần đó để khẳng định đã tái hiện lại đúng selector failure.

Log/trace thô được giữ trong thư mục riêng tư `/private/tmp/booking-functional-coverage-review-20261001/`. Không đưa trace, request body, cookie, auth state hoặc manifest chứa credential vào repo. Evidence JSON chỉ lưu kết quả đã rút gọn và metadata không nhạy cảm.

### Lệnh tái hiện trên môi trường local đã provision

Trong `frontend/`, với PHP/PostgreSQL/Node và test config đủ prerequisites:

```sh
# Full run đã review; URL overrides được xóa tạm để tránh R01.
DB_URL='' DATABASE_URL='' npm run test:e2e -- --reporter=line,junit,html

# Hai full-run failures, môi trường mới do global setup quản lý.
DB_URL='' DATABASE_URL='' npm run test:e2e -- --project=p1 --project=p2 --grep '9\.2\.3:|10\.4\.1:'

# Hai ca phụ thuộc dữ liệu trước đó, chạy từng lệnh trên run mới.
DB_URL='' DATABASE_URL='' npm run test:e2e -- e2e/p1-partner-registration.spec.ts --project=p1 --grep '7\.2\.2:'
DB_URL='' DATABASE_URL='' npm run test:e2e -- e2e/p1-revenue-reconciliation-reporting.spec.ts --project=p1 --grep '9\.6\.3:'

# Discovery: dùng TMPDIR riêng để không đọc pointer của một run khác.
review_list_tmp=$(mktemp -d)
TMPDIR="$review_list_tmp" E2E_MANIFEST_PATH='' npm run test:e2e -- --list
```

Gate skip/flaky canary dùng clone config với global setup/teardown tắt và chỉ giữ P0 project; nó kiểm tra verdict của installed runner, không giả rằng đã chạy live CI. UTC canary dùng browser trang trống và `getFuturePlayDate(3, 6, 0)`, không gọi app/provider.

## 3. Danh sách finding

| ID | Mức | Vấn đề | Bằng chứng |
| --- | --- | --- | --- |
| R01 | P1 | PHP subprocess không dùng cùng môi trường DB đã preflight | Canary config + code |
| R02 | P1 | CI image thiếu PHP/Composer/PostgreSQL và backend bootstrap | Image check + code |
| R03 | P1 | Web regression còn chọn build/push/deploy | Rules/DAG |
| R04 | P1 | baseURL giữ giá trị trước khi runner chọn frontend port | Canary config |
| R05 | P1 | Required skip/retry-only pass có thể tạo gate xanh | Canary exit code |
| R06 | P1 | P0 action bị API helper thay thế hoặc thiếu journey bắt buộc | Code/spec |
| R07 | P2 | Test dùng dữ liệu/trạng thái do ca trước tạo | Independent failure + code |
| R08 | P2 | Subscription xác thực tiền bằng receipt được insert sẵn | Code/spec |
| R09 | P2 | Buyer race thực hiện tuần tự, receipt/paid bị ghi trực tiếp | Code/spec |
| R10 | P2 | Direct refund test không thực hiện refund operation | Code |
| R11 | P2 | Dispute assertions chứng minh fixture/biến tự đặt | Code |
| R12 | P2 | Coverage matrix không truy xuất được execution evidence | Audit 126 FR |
| R13 | P2 | i18n/theme/focus checks không chứng minh hành vi ứng dụng | Code |
| R14 | P2 | Realtime/polling test chỉ chứng minh snapshot sau thao tác | Code/spec |
| R15 | P2 | Selector availability chọn nhầm slot trước | Full-run failure + trace |
| R16 | P2 | Fault injection có thể pass mà chưa hit request lỗi | Code |
| R17 | P2 | Chưa chặn egress; browser gọi font ngoài | Trace + code |
| R18 | P2 | Artifact filename excludes không loại secret trong trace | Trace + CI config |
| R19 | P2 | 12 baseline test bị loại khỏi mọi project | Config + inventory |
| R20 | P2 | Browser locale/timezone và weekday không khớp metadata | UTC canary |
| R21 | P2 | Discovery phụ thuộc manifest của môi trường đang chạy | `--list` reproduction |

### R01 — [P1] PHP subprocess có thể vượt khỏi database của run

**Vị trí:** [p1-receiving-connections.spec.ts:54](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-receiving-connections.spec.ts:54), [test-data.ts:116](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/runner/test-data.ts:116). Task 2.2, 2.5, 2.6.

Runner server có override `DB_URL: ''` và `DATABASE_URL: ''`, nhưng worker/helper tự dựng `backendEnv`, rồi merge `{ ...process.env, ...backendEnv }` mà không xóa URL. Laravel PostgreSQL config dùng `url: env('DB_URL')`; URL có thể ghi đè tên/host/user được truyền từ manifest. Nhiều `php -r` helper trực tiếp create/update/delete, không đi qua CLI guard của `e2e:arrange-test-data`.

**Điều kiện/ảnh hưởng:** máy hoặc CI export `DB_URL` trỏ database khác, hoặc `.env.testing` chứa override tương ứng. Preflight của server vẫn kiểm tra đúng DB riêng, còn helper có thể đọc/ghi DB khác bằng quyền URL. Canary chỉ resolve Laravel config, xác nhận URL thắng `DB_DATABASE`; không kết nối hay ghi DB ngoài run.

**Sửa tối thiểu:** một môi trường backend chuẩn từ manifest, dùng cho mọi subprocess; xóa URL override/cached config và xác nhận effective connection/run ownership trước mọi mutation. Gom raw helper về đường thực thi có guard; tránh vá từng spec bằng env map riêng.

**Tiêu chí xác minh:** inherited URL tới target không được sở hữu phải bị từ chối trước mutation; DB/service/helper cùng effective target. Có refusal check dùng target disposable/canary, không thử với production DB.

### R02 — [P1] CI job chưa có runtime để khởi động môi trường E2E

**Vị trí:** [.gitlab-ci.yml:49](/Volumes/SSD/WorkSpace/ziet-project/booking/.gitlab-ci.yml:49), [ci-runner-prerequisites.md:5](/Volumes/SSD/WorkSpace/ziet-project/booking/docs/testing/ci-runner-prerequisites.md:5). Task 6.2–6.3, 10.5.

Image Playwright chỉ được chạy `npm ci` rồi browser test. Runner cần PHP với extensions, Composer vendor và PostgreSQL CLI/server trên target local. Không có image dựng thêm các runtime, database service/bootstrap, Composer install, app key hoặc file test environment trong job. Prerequisites ghi executor là Docker/OrbStack; công cụ macOS host không tự xuất hiện trong container.

**Bằng chứng:** kiểm tra đúng image `mcr.microsoft.com/playwright:v1.62.1-noble` với network disabled: Node có mặt; `php`, `composer`, `createdb`, `psql`, `postgres` không có. Local `.env.testing` và `vendor/` không được track nên local pass không chứng minh fresh checkout CI chạy được.

**Sửa tối thiểu:** chọn một image CI đủ Playwright + backend/PG runtime và bootstrap dependencies/config test, hoặc dùng runner shell đã provision theo hợp đồng được xác minh. DB service phải đáp ứng local target/ownership của runner; ghi rõ prerequisite vận hành còn thiếu.

**Tiêu chí xác minh:** fresh checkout chạy P0 trong chính executor/image chọn, migrate/seed/teardown thành công, không cần vendor/env/PG của máy developer. Live GitLab pipeline chưa được chạy trong review.

### R03 — [P1] Manual web regression vẫn có đường deploy

**Vị trí:** [.gitlab-ci.yml:130](/Volumes/SSD/WorkSpace/ziet-project/booking/.gitlab-ci.yml:130), line 166. Task 6.3, 10.5.

Build job chỉ loại `schedule`; rule branch vẫn chọn `web` pipeline trên `dev` hoặc default branch. `needs` chỉ chờ frontend/P0, không chờ regression/smoke manual jobs. Sau build/push, điều kiện Portainer chỉ loại MR, nên `web` còn đủ điều kiện gọi deployment webhook.

**Điều kiện/ảnh hưởng:** web pipeline trên deployable branch không bị workflow loại, P0/frontend hoàn tất và có deploy variables. Người chỉ định chạy regression có thể làm build/push/deploy được chọn tự động. Đây là kết luận từ rules/DAG, không phải deployment đã được thực hiện trong review.

**Sửa tối thiểu:** build/deploy chỉ nhận pipeline source được chủ ý cho release, hoặc loại rõ cả `schedule` và `web` theo thiết kế hiện tại. Đồng bộ workflow/job rules; không dựa vào manual regression để chặn DAG.

**Tiêu chí xác minh:** CI lint/simulation cho push, MR, schedule, web trên `dev`/default branch. Web và schedule regression không có job deploy-capable.

### R04 — [P1] baseURL không theo frontend port mà runner thực sự sở hữu

**Vị trí:** [playwright.config.ts:25](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/playwright.config.ts:25), [playwright-global-setup.ts:18](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/runner/playwright-global-setup.ts:18), [environment.ts:163](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/runner/environment.ts:163). Task 2.1–2.2.

Config đọc `PLAYWRIGHT_BASE_URL` trước global setup. Setup đổi env sau khi chọn port, nhưng không cập nhật config đã được đọc. Nếu port 4180 bận, runner tạo frontend ở port khác trong khi `page.goto('/...')` vẫn tới 4180; env caller có URL cũ cũng được giữ.

**Bằng chứng:** import config rồi đổi env URL, `config.use.baseURL` vẫn giữ 4180. Port allocator có fallback sang free port khác. Full run hiện tại dùng 4180 nên không kiểm chứng được trường hợp port bận.

**Sửa tối thiểu:** chốt và truyền URL trước khi resolve config, hoặc fixture lấy URL từ manifest cho browser context. Giữ một nguồn URL và kiểm tra browser origin thuộc process của run.

**Tiêu chí xác minh:** chiếm 4180 bằng process disposable; test vẫn chỉ truy cập frontend riêng của run hoặc fail preflight, không dùng process bên ngoài. Caller URL không thể chuyển browser ra khỏi environment đã kiểm tra.

### R05 — [P1] Required skip và retry-only pass chưa làm gate đỏ

**Vị trí:** [.gitlab-ci.yml:54](/Volumes/SSD/WorkSpace/ziet-project/booking/.gitlab-ci.yml:54), [playwright.config.ts:3](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/playwright.config.ts:3), [tasks.md:47](/Volumes/SSD/WorkSpace/ziet-project/booking/openspec/changes/add-playwright-functional-coverage/tasks.md:47). Task 6.4.

Gate chỉ dùng exit code mặc định; chưa đối chiếu required inventory hoặc skipped/flaky outcomes. Canary bằng config tạm cho test `@p0` gọi `test.skip` kết thúc exit 0. Fail lần đầu/pass lần retry với `--retries=1` cũng exit 0 và được phân loại flaky. Defaults hiện tại không retry là đúng, nhưng không có kiểm tra chống retry-only success khi config/CLI được đổi cho diagnostics.

**Sửa tối thiểu:** kiểm tra required IDs/count và không cho critical skip; dùng tùy chọn `failOnFlakyTests` có sẵn ở installed Playwright, kết hợp kiểm tra report outcome. Giữ diagnostics rerun tách khỏi verdict required run.

**Tiêu chí xác minh:** normal pass xanh; zero selection, một required skip, missing required test, fail, missing backend và retry-only success đều đỏ. Canary không chạy live deploy pipeline.

### R06 — [P1] P0 pass chưa chứng minh đủ thao tác người dùng bắt buộc

**Vị trí:** [p0-pos-shift-service.spec.ts:844](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p0-pos-shift-service.spec.ts:844), [p0-cancellation-refund-reschedule.spec.ts:158](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p0-cancellation-refund-reschedule.spec.ts:158), [auth-journey-and-access-matrix.spec.ts:35](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/auth-journey-and-access-matrix.spec.ts:35). Task 3.2, 4.5–4.7, 5.3–5.4, 6.5.

Design quy định helper chỉ arrange/verify, không làm user action đang được claim. Những ví dụ cụ thể:

- **5.4:** check-in, mở/thêm/xóa service tab và settlement đều qua `page.evaluate` gọi API; không thực hiện check-out và không kiểm tra journey sau reload. Xóa item còn nằm trong nhánh tùy chọn. Login UI không biến chuỗi API này thành browser service-tab coverage.
- **4.6:** refund quote/request, owner approval/report execution và customer acknowledgement qua API; booking chỉ có một slot nên chưa có partial-cancellation proof.
- **3.2.1:** logout UI optional; API fallback cho test pass ngay cả khi control logout hỏng/thiếu.
- **4.5:** loyalty control/persisted point debit có kiểm tra thật; coupon preview qua API, coupon được tạo nhưng chưa áp dụng vào checkout để kiểm tra persisted benefit. Member check chỉ đọc status và assert defined, chưa chứng minh member benefit. Không nên suy rộng point proof thành toàn bộ coupon/member/points journey.
- **5.3:** review bằng manager API, còn browser reload/permission journey được task yêu cầu chưa đầy đủ.

**Ảnh hưởng:** control/caller/mapping UI có thể hỏng mà P0 vẫn pass. Một số P1 cũng có mẫu service/API + mở page tương tự, được ghi ở appendix. Những assertion backend thật vẫn có giá trị; phạm vi bằng chứng của chúng phải được ghi đúng.

**Sửa tối thiểu:** giữ helper arrange/read-back, dùng control UI cho claimed action, bỏ fallback/optional assertion trên required flow, bổ sung partial cancellation và check-out/reload. Với operation không có UI, ghi API-only/gap và giữ acceptance task tương ứng chưa hoàn thành; tách product fix khi cần.

**Tiêu chí xác minh:** vô hiệu hóa control/caller UI tương ứng phải làm journey fail; mỗi P0 chạy độc lập, đủ trạng thái tiền/stock/points sau reload và 3 clean full P0 runs không retry ở revision sửa.

### R07 — [P2] Dữ liệu scenario phụ thuộc ca trước

**Vị trí:** [p1-partner-registration.spec.ts:112](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-partner-registration.spec.ts:112), line 160 và 319; [p1-revenue-reconciliation-reporting.spec.ts:215](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-revenue-reconciliation-reporting.spec.ts:215). Task 2.5, 7.2, 9.6.

Partner 7.2.2 đọc current draft nhưng không tạo draft riêng. Chạy ca này độc lập đã fail tại line 119 vì `currentRes.data` null; chạy theo file pass nhờ 7.2.1. Ca 7.2.3 tải ba document, phụ thuộc front document của 7.2.2. Ca 7.2.4 tìm hồ sơ cùng customer rồi tự sửa sang pending thay vì tạo scenario review đủ điều kiện.

CSV 9.6.3 gọi controller export thật nhưng không arrange owner/platform revenue cho chính ca; assertion cần cả hai loại row. 9.6.1 tạo dữ liệu đó. Chạy riêng CSV trên environment mới đã fail tại line 283: `total_rows` bằng **0**. Các shared actor/policy khác cũng bị mutate giữa test, như cashier bị suspend trong staff suite và recurring/resale policy trên venue chung.

**Sửa tối thiểu:** scenario tự tạo actor/application/documents và money rows; dành account riêng cho auth/grant mutations. Namespace court/policy/stock theo test hoặc phục hồi có kiểm tra, không chỉ dựa vào một worker.

**Tiêu chí xác minh:** mỗi ca partner và CSV chạy một mình trên run mới vẫn pass; chạy đổi thứ tự không cần kết quả của ca trước. Không nâng workers trước khi mutable data được cô lập.

### R08 — [P2] Subscription payment bỏ qua webhook xác thực

**Vị trí:** [p1-platform-subscription.spec.ts:245](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-platform-subscription.spec.ts:245), line 282. Task 2.6, 7.3; FR-ACC-14–16, FR-PAY-12–13.

Ca 7.3.3 tạo `PlatformProviderReceipt` với `verified_at` rồi gọi `ConfirmSubscriptionPayment` trực tiếp. Nó chứng minh confirmation action chấp nhận receipt đã được insert, chưa chứng minh authenticated webhook, receiver/reference/amount verification. “Owner receipt không kích hoạt platform” chỉ đọc pending trước khi có event nào; không gửi wrong-class receipt.

**Sửa tối thiểu:** deliver signed synthetic event qua platform webhook thực đã có, chạy processor và đọc entitlement/UI sau reload. Thêm owner receiver/wrong amount/reference/replay cases; không set verified state của operation đang kiểm tra.

**Tiêu chí xác minh:** auth/receiver/amount/reference sai không activate; event platform đúng activate đúng một lần, owner receipt không activate. Phân biệt synthetic evidence với certification của provider thật.

### R09 — [P2] Buyer race chưa có concurrency và tự tạo paid evidence

**Vị trí:** [p1-resale-marketplace.spec.ts:995](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-resale-marketplace.spec.ts:995), line 1100–1202. Task 8.3; FR-BKG-19–21.

8.3.5 tạo receipt/allocation `allocated`, sửa attempts thành `paid`, rồi gọi purchase A xong mới gọi purchase B. B nhận 409 khi listing đã sold chứng minh sequential conflict, không chứng minh hai buyer tranh listing cùng lúc. Tự ghi paid/allocated cũng bỏ qua payment evidence boundary ở scenario này.

**Sửa tối thiểu:** dùng 2 context/actor và request đồng thời tới boundary thật, với synthetic payment được xác thực qua webhook. Kiểm tra exactly-one transfer/holder/check-in authority và obligation cho bên thua theo contract. Đổi tên/disposition hiện tại thành sequential service test nếu giữ nó.

**Tiêu chí xác minh:** có overlap của hai purchase attempts, đúng một winner; không double entitlement/transfer; không phải B chỉ được gọi sau khi A hoàn tất. Giữ deep locking/load proof trong backend suite.

### R10 — [P2] Direct refund test chưa gọi refund operation

**Vị trí:** [p1-legacy-wallet-direct-cohort.spec.ts:296](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-legacy-wallet-direct-cohort.spec.ts:296). Task 8.6.

8.6.3 update obligation trực tiếp sang `executed`, rồi đọc wallet không thay đổi. Không có approval/report-execution/acknowledgement/action của refund. Nếu implementation refund thực tự credit wallet sai, test vẫn pass vì operation đó chưa chạy.

**Sửa tối thiểu:** dùng refund path thật với owner report và recipient acknowledgement hoặc service/action tương ứng cho backend evidence; kiểm tra balance và wallet ledger trước/sau. Reuse scenario P0 refund đã có thay vì thêm một lifecycle tự chế bằng DB update.

**Tiêu chí xác minh:** lỗi credit wallet trong refund operation thật phải bị phát hiện; obligation phải đạt trạng thái đúng bằng operation thật, không qua fixture mutation của trạng thái cuối.

### R11 — [P2] Dispute suite có nhiều proof tự tạo

**Vị trí:** [p1-dispute-lifecycle.spec.ts:62](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-dispute-lifecycle.spec.ts:62), line 93, 163, 241, 339, 427. Task 9.5; FR-COM-17–24.

- Intake gán sẵn severity/status/requested evidence; “duplicate blocked” chỉ đếm record đang tồn tại, chưa submit duplicate.
- Foreign-party denial chỉ so user/organization IDs, chưa gọi HTTP boundary hay policy.
- Evidence transition update trực tiếp evidence/status, chưa upload qua operation thật.
- Financial-completion proof trả literal `payment_completed: false`, rồi assert chính literal đó.
- Close gate có gọi controller thật, nhưng settlement trước close được set ACKNOWLEDGED trực tiếp; không chứng minh owner/recipient settlement journey.
- UI check cuối chủ yếu kiểm tra heading/container, chưa kiểm tra case vừa tạo và actor action.

**Sửa tối thiểu:** gọi intake/upload/read/action boundary thật; submit duplicate thật; HTTP denial với foreign actor; đọc financial-completion field từ response/state thật. Giữ action/controller assertions có giá trị và phân loại chúng đúng là backend evidence.

**Tiêu chí xác minh:** bỏ classification/duplicate guard/authorization/financial-state mapping trong operation thật phải làm ca tương ứng fail. Không dùng giá trị do test tự đặt làm kết luận về chính operation đó.

### R12 — [P2] Coverage matrix chưa thể dùng làm bằng chứng nghiệm thu

**Vị trí:** [playwright-coverage.md:45](/Volumes/SSD/WorkSpace/ziet-project/booking/docs/testing/playwright-coverage.md:45), [tasks.md:84](/Volumes/SSD/WorkSpace/ziet-project/booking/openspec/changes/add-playwright-functional-coverage/tasks.md:84). Task 1.2, 6.1, 10.6–10.7.

Audit có đủ **126 ID riêng biệt**, nhưng tất cả đều ghi `Covered`; không phải trạng thái execution theo design. **54 dòng** trỏ file browser không tồn tại: `critical-flows.spec.ts` 39, `p1-venue-lifecycle-readiness.spec.ts` 12, `p1-split-bill.spec.ts` 2, `p1-staff-access-control.spec.ts` 1. **40 dòng** không có `.spec.ts` ở cột browser test; một số reference lại nằm ở cột backend. Không có execution report link. Nhiều dòng ghi revision `bf0e0b8`, không gắn với input được review hoặc measured run hiện tại.

Ví dụ OAuth FR-ACC-03 trỏ 7.1.2 vốn là verification; partner có reference 7.2.5 trong khi file chỉ có 4 case. SUP-10 đã ghi `Partial (UI Gap)` — gap này có được ghi nhận, nhưng task 10.4/10.7 vẫn checked. Không kết luận mọi supplementary row đều `Covered`.

**Sửa tối thiểu:** map từng FR vào file/title/assertion thật, phân loại UI/API-only/blocked/failed/flaky/skipped/not-run; gắn revision/environment và report. Reconcile checkbox bằng evidence. Không thay tên file trong matrix rồi coi các journey thiếu assertion là đã covered.

**Tiêu chí xác minh:** 126 ID/tên giữ đúng nguồn, test references tồn tại và step thực sự assert FR; mỗi trạng thái có nguồn evidence. Chỉ ghi passed khi đúng journey đã chạy pass trên revision đó.

### R13 — [P2] i18n/theme/a11y tests có thể pass khi tính năng hỏng

**Vị trí:** [p2-representative-experience.spec.ts:33](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p2-representative-experience.spec.ts:33), line 52 và 98. Task 10.2; SUP-09.

Locale chỉ assert `textContent` được defined, không kiểm tra bản dịch hoặc khác nhau. Theme tự thêm/xóa class và attribute trong DOM rồi kiểm tra lại DOM vừa sửa; chưa gọi theme control hay kiểm tra persistence của ứng dụng. Modal optional; có mở thì chỉ Escape, chưa kiểm tra focus entry/trap/restore. Mobile chỉ sample login/tournaments, chưa có booking/payment/POS như task.

**Sửa tối thiểu:** dùng control/state contract thật và assert nội dung cụ thể; reload theme; modal bắt buộc mở, Tab/Shift+Tab và focus restore; sample một money journey trên mobile/locale/theme. Nếu UI thiếu behavior, giữ gap/failed và tách product repair.

**Tiêu chí xác minh:** locale không đổi, theme store không persist hoặc focus thoát khỏi modal đều làm test fail; required modal biến mất không được pass qua nhánh bỏ qua.

### R14 — [P2] Realtime và polling chưa được kiểm tra khi view đang mở

**Vị trí:** [p2-realtime-availability-tournament.spec.ts:122](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p2-realtime-availability-tournament.spec.ts:122), line 204; [environment.ts:279](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/runner/environment.ts:279). Task 10.4; SUP-10.

Availability tạo hold trong PHP, B gọi GET API rồi navigate lại. DOM check cuối optional và chỉ assert class là string. Tournament submit score trước khi spectator mở bracket, nên chỉ chứng minh initial snapshot. Runner dùng broadcast `null`, không sở hữu realtime process. Không có websocket update hoặc mutation sau khi view đang mở để quan sát polling fallback.

**Sửa tối thiểu:** B mở view trước, A thực hiện mutation thật sau đó; B phải thay đổi nội dung đúng mà không reload. Nếu claim websocket thì chạy realtime process cô lập; một ca riêng ngắt transport và chứng minh polling. Giữ snapshot checks với tên/disposition đúng.

**Tiêu chí xác minh:** ngừng subscription/polling làm ca tương ứng fail; có bounded wait và assertion state cụ thể. UI gap hiện tại phải tiếp tục visible, không tính task hoàn thành.

### R15 — [P2] Availability selector nhầm giờ bắt đầu với giờ kết thúc

**Vị trí:** [p2-realtime-availability-tournament.spec.ts:62](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p2-realtime-availability-tournament.spec.ts:62), line 140. Task 10.4.

`:has-text("07:00").first()` khớp cả **06:00–07:00** và **07:00–08:00**. Full run A chọn first available bắt đầu 07:00, B nhận slot trước đang occupied và fail line 64. Trace/snapshot đã xác nhận hai range, không suy ra product availability bug từ lỗi locator này.

**Sửa tối thiểu:** chọn đúng court/date và exact full time range hoặc start timestamp của slot; dùng lại locator đó ở post-mutation assertion. Không dùng `.first()` để che nhiều slot khớp.

**Tiêu chí xác minh:** scenario slot trước occupied/slot sau available chọn đúng slot sau. Sau hold, B assert đúng slot đó đổi state; R14 vẫn cần sửa riêng.

### R16 — [P2] Fault injection chưa buộc request bị lỗi xảy ra

**Vị trí:** [p2-fault-injection-resilience.spec.ts:74](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p2-fault-injection-resilience.spec.ts:74), line 112 và 161. Task 10.1.

401 test chỉ bấm save nếu optional input/button hiện rồi kiểm tra không có success toast; có thể pass khi không gửi mutation. 403 dùng customer vốn đã không có quyền, chỉ assert URL không chứa `/admin/organization`, chưa chứng minh revoked permission handling. 503 chỉ kiểm tra body text defined; không kiểm tra maintenance/error feedback hay request hit. Focus retention chưa được assert.

**Sửa tối thiểu:** required UI action + đếm route hit/`waitForResponse`; actor được cấp quyền trước khi revoke; assert lỗi cụ thể, input/focus được giữ và không có success. 500/login hiện có error/input checks hữu ích, không cần thay cả suite bằng một framework mới.

**Tiêu chí xác minh:** injected request không xảy ra, error UI không hiện, input/focus bị mất hoặc unauthorized data hiện ra phải làm ca fail.

### R17 — [P2] Runner chưa chặn network egress

**Vị trí:** [environment.ts:288](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/runner/environment.ts:288), [environment.spec.ts:1184](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/runner/environment.spec.ts:1184). Task 2.6.

`NO_PROXY` chỉ bypass proxy cho local hosts, không phải network deny. PayOS được chuyển sang local port 9, nhưng SePay API base vẫn có default live; raw PHP helpers bootstrap `.env.testing`/inherited env thay vì cùng provider/mail/queue overrides của server. Check có tên “zero external network egress” chỉ kiểm tra reset/paid endpoints trả 404/405.

**Bằng chứng thực tế:** full/focused traces có request tới `fonts.googleapis.com` và `fonts.gstatic.com`; một số request font chưa hoàn thành khi timeout. Chưa có evidence suite gọi live bank/SMTP/OAuth; review không gửi request tới provider thật.

**Sửa tối thiểu:** deny outbound cho backend/browser, chỉ cho owned local origins; dùng local provider/mail adapters, đưa third-party assets cần thiết về nguồn kiểm soát được. Mọi PHP helper dùng cùng canonical env. Thêm check chứng minh attempt tới external target không thể rời process/environment.

**Tiêu chí xác minh:** suite hoạt động với outbound bị chặn; external call bị fail closed và quan sát được. Sau đó chạy lại feed 9.2.3 để xác định phần timeout còn lại, không chỉ tăng timeout.

### R18 — [P2] Secrets vẫn nằm trong trace được upload

**Vị trí:** [.gitlab-ci.yml:64](/Volumes/SSD/WorkSpace/ziet-project/booking/.gitlab-ci.yml:64), các artifact block tương tự line 90/116. Task 2.7, 6.1, 6.4.

Exclude `*.auth.json`/manifest chỉ lọc tên file. Trace ZIP được retain có login request body chứa password và headers cookie/session; đã kiểm tra cấu trúc trace mà không in giá trị. Failure HTML report có thể mang các attachment đó. YAML chưa khai báo restriction artifact access như design; quyền thực tế còn phụ thuộc project/server settings.

**Ảnh hưởng:** credential/session **được tạo cho run** có thể đi theo diagnostics. Không có bằng chứng public exposure hoặc credential production bị leak; kết luận là chưa đáp ứng yêu cầu loại secret và giới hạn artifact access.

**Sửa tối thiểu:** chỉ upload diagnostics đã loại dữ liệu nhạy cảm, hoặc bỏ raw trace khỏi artifact cho auth journeys theo hợp đồng được chốt; khai báo artifact access giới hạn và retention. Xác minh cả payload nằm trong attachment, không chỉ danh sách file.

**Tiêu chí xác minh:** fixture secret sentinel không xuất hiện trong bất kỳ file/archive được upload; người không thuộc phạm vi cho phép không tải được diagnostics. Không dùng secret thật cho check.

### R19 — [P2] Default suite âm thầm bỏ toàn bộ baseline

**Vị trí:** [playwright.config.ts:32](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/playwright.config.ts:32), [booking-access-loyalty-uat.spec.ts:5](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/booking-access-loyalty-uat.spec.ts:5). Task 1.1, 3.1, 10.6.

Mọi project dùng grep priority/smoke; 12 test trong `home`, `seven-areas-acceptance`, `booking-access-loyalty-uat` không match. Bốn UAT test vừa sửa credentials/expectations vẫn không được chạy. Một số assertion optional/stale trong baseline vẫn tồn tại, nên việc “full regression” không thể chứng minh các edit đó.

**Sửa tối thiểu:** tag/select những baseline còn cần và sửa assertion required, hoặc retire rõ baseline đã được thay thế, map mỗi mục còn cần sang test mới có proof. Tránh giữ file chết nhưng để runbook coi nó là baseline đang chạy.

**Tiêu chí xác minh:** inventory nêu rõ selected/retired/replaced; không còn test modified bị loại khỏi mọi selection mà không có disposition.

### R20 — [P2] Locale/timezone thật không khớp metadata

**Vị trí:** [playwright.config.ts:17](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/playwright.config.ts:17), [test-data.ts:104](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/runner/test-data.ts:104), [p2-realtime-availability-tournament.spec.ts:256](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p2-realtime-availability-tournament.spec.ts:256). Task 2.5, 6.1, 10.4.

Config ghi timezone Asia/Ho_Chi_Minh nhưng không set browser `timezoneId`/`locale`. Canary trên `TZ=UTC` dùng cùng project options trả browser **UTC, en-US**. Metadata browser luôn `chromium` kể cả smoke Firefox/WebKit. `getFuturePlayDate` format date theo VN rồi dùng host `Date.getDay()`; với hour trước 07:00 trên UTC, weekday lệch một ngày.

**Bằng chứng:** `getFuturePlayDate(3, 6, 0)` trả date **2026-10-04** (Chủ nhật) nhưng weekday **6** thay vì ISO **7**. Callers hiện tại chủ yếu dùng giờ từ 10:00 nên không suy ra toàn bộ booking run đã sai. Ca “Midnight Transition” chỉ so today/tomorrow, chưa điều khiển hai phía qua mốc nửa đêm.

**Sửa tối thiểu:** set locale/timezone thực cho context; tính weekday bằng timezone explicit; metadata lấy thông tin thực per project/run. Dùng controlled boundary và clock contract thống nhất cho server/browser trong ca midnight.

**Tiêu chí xác minh:** host UTC và VN cho cùng play date/weekday; browser reporting đúng timezone/locale/browser; ca midnight chứng minh trước/sau boundary thay vì mở page vào thời điểm ngẫu nhiên.

### R21 — [P2] Không thể discovery test trên clean environment

**Vị trí:** [p1-dispute-lifecycle.spec.ts:14](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-dispute-lifecycle.spec.ts:14), [p2-smoke-public-navigation.spec.ts:11](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p2-smoke-public-navigation.spec.ts:11). Task 6.4, 10.5–10.6.

Sáu file gọi `getTestManifest()` trong describe/module body: dispute, revenue, fault injection, realtime, representative experience và smoke. `playwright test --list` không khởi động global setup để tạo manifest. Với `E2E_MANIFEST_PATH` rỗng và TMPDIR riêng, lệnh fail bởi sáu import errors và in `Total: 0 tests in 0 files`.

**Ảnh hưởng:** không kiểm tra được expected inventory trên fresh checkout nếu chưa tạo environment; tag/grep discovery và review selection bị phụ thuộc active pointer của run khác.

**Sửa tối thiểu:** đọc manifest/credentials trong fixture, hook hoặc test runtime; để declaration/title/selection không phụ thuộc tài nguyên được setup.

**Tiêu chí xác minh:** `--list` không có manifest/server/DB vẫn liệt kê đủ 157 default executions; riêng P0 liệt kê 24. Discovery không tạo hay tái sử dụng database/process.

## 4. Rủi ro CI có điều kiện cần xác minh thêm

**C01 — MR pipeline có thể thiếu `frontend_check` dependency.** Job này chưa có `rules`, còn `build_and_deploy` được chọn cho MR và có `needs: frontend_check`. Với default behavior loại job không có rules khỏi MR pipeline, pipeline creation sẽ lỗi missing need. Xem [job rules của GitLab](https://docs.gitlab.com/ci/jobs/job_rules/) và [needs:optional](https://docs.gitlab.com/ci/yaml/#needsoptional). Tuy nhiên [setting Skip branch pipelines for merge requests](https://docs.gitlab.com/ci/pipelines/settings/#skip-branch-pipelines-for-merge-requests) trên phiên bản hỗ trợ có thể thay đổi implicit behavior. Review chưa kiểm tra version/setting server thực nên đây là **risk conditional, không tính vào 21 finding đã chốt**.

Phương án đề nghị: khai báo source rules nhất quán cho validate/P0/build, rồi lint/simulate đúng server. Không thêm `optional` vào required gate chỉ để pipeline tạo được.

## 5. Thứ tự sửa và điều kiện nghiệm thu lại

| Đợt | Finding | Kết quả cần đạt |
| --- | --- | --- |
| 1. Cô lập environment | R01, R04, R17 | Server/browser/helper cùng target, fail closed, offline determinism |
| 2. CI gate | R02, R03, R05, C01 | Fresh executor chạy được; regression không deploy; missing/skip/flaky đều đỏ |
| 3. Required P0 | R06 | Thao tác UI bắt buộc, partial cancel/check-out/reload; independent + 3 clean P0 runs |
| 4. Scenario P1 | R07–R11 | Dữ liệu riêng; operation/evidence boundary thật; concurrency và denial thật |
| 5. P2 và diagnostics | R13–R16, R18, R20–R21 | Experience/realtime/fault assertions thật; discovery độc lập; artifact không chứa secrets |
| 6. Traceability | R12, R19 | Mapping/test inventory chính xác, report link từng disposition, checkbox theo evidence |

Trong mỗi đợt, sửa nguyên nhân tại helper/config chung nếu các caller cùng mắc; không xây thêm framework test. Chạy targeted checks cho finding vừa sửa, rồi full regression sau khi các nguyên nhân đã ổn định.

### Các acceptance task cần reconcile

- **2.2/2.5/2.6/2.7:** còn URL inheritance, dữ liệu phụ thuộc ca trước, egress và artifact secret gaps.
- **3.2/4.5/4.6/5.3/5.4:** có assertion hữu ích nhưng required UI journey/variants chưa đủ.
- **6.2/6.3/6.4:** runtime, source rules và skip/flaky verdict chưa đạt contract.
- **6.5:** review xác minh một P0 riêng và P0 trong một full regression; chưa có bằng chứng kiểm chứng được cho tất cả P0 độc lập + 3 consecutive clean full P0 runs ở input này.
- **7.3/8.3/8.6/9.5:** những proof mô tả ở R08–R11 cần thay bằng operation thật hoặc disposition đúng.
- **10.1/10.2/10.4/10.6/10.7:** còn weak assertions, known realtime gap, hai full-run failures và traceability không đúng.

Không thể dùng all-checked tasks để kết luận completion. Report không tự uncheck task vì user đang yêu cầu review/backlog; lần repair cần cập nhật trạng thái theo kết quả thực.

### Quyết định review và giới hạn

- Chọn chạy môi trường disposable do runner sở hữu; tránh tái dùng backend/database đang chạy. Database riêng cho backend regression được thu hồi sau run.
- Chọn config-only canary cho URL override; không gây write ngoài DB test để “chứng minh” lỗi isolation.
- Chọn giữ read-back/domain assertions có giá trị, phân loại lại evidence; không coi mọi PHP helper là sai. Điểm sai là helper làm operation được claim là UI hoặc tự gán kết quả đang kiểm tra.
- Chọn report/backlog thay vì tự sửa sản phẩm, archive change hoặc thay status matrix. UI/business fix thiếu cần scope riêng hoặc disposition rõ theo OpenSpec.
- Chưa chạy live GitLab pipeline/deployment, chưa kiểm chứng artifact authorization thực trên server, chưa gọi provider/bank/SMTP/OAuth thật. Các operational/provider gates đó không được tính passed.
- Không khẳng định đã tìm mọi product defect. Review bao phủ implementation của change và acceptance evidence; regression backend chỉ gồm 7 file liên quan.

## 6. Appendix — inventory và coverage audit

Inventory chi tiết từng test/FR và hashes nằm trong [review-evidence.json](/Volumes/SSD/WorkSpace/ziet-project/booking/openspec/changes/add-playwright-functional-coverage/review-evidence.json). Các số liệu dưới đây là execution/traceability audit, không phải xác nhận business coverage của mọi ca pass.

### 126 FR theo nhóm

| Nhóm | Số ID |
| --- | ---: |
| ACC | 18 |
| VEN | 14 |
| PRM | 12 |
| BKG | 21 |
| PAY | 20 |
| OPS | 17 |
| COM | 24 |
| **Tổng** | **126** |

### Browser references không tồn tại

| Reference trong matrix | Dòng ảnh hưởng | Hướng reconcile |
| --- | ---: | --- |
| critical-flows.spec.ts | 39 | Map vào auth/p0-domain spec và assertion thật |
| p1-venue-lifecycle-readiness.spec.ts | 12 | Map vào p1-venue-lifecycle và từng readiness/catalog/policy assertion |
| p1-split-bill.spec.ts | 2 | Map vào split-payments; tách service/API evidence với UI |
| p1-staff-access-control.spec.ts | 1 | Map vào staff-management + access matrix đúng scope |

IDs cụ thể của 54 dòng và 40 dòng chưa có browser filename được giữ trong evidence; hai nhóm là các vấn đề traceability, không tự cộng thành số FR không có business coverage.

### Inventory theo file

| Spec | Definition / priority | Full run | Phạm vi/bất cập cần lưu ý |
| --- | --- | --- | --- |
| [auth-journey-and-access-matrix.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/auth-journey-and-access-matrix.spec.ts) | 8 P0 | 8/8 pass | Access matrix/login có proof; logout fallback còn gap (R06). |
| [booking-access-loyalty-uat.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/booking-access-loyalty-uat.spec.ts) | 4 unselected | Không được chọn | 4 ca modified vẫn không được chọn (R19). |
| [home.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/home.spec.ts) | 1 unselected | Không được chọn | Baseline không được chọn (R19). |
| [p0-booking-payment-journey.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p0-booking-payment-journey.spec.ts) | 5 P0 | 5/5 pass | UI hold, webhook và point persistence có proof; coupon/member variants còn thiếu (R06). |
| [p0-cancellation-refund-reschedule.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p0-cancellation-refund-reschedule.spec.ts) | 3 P0 | 3/3 pass | API actions/read-back; thiếu partial cancellation và required UI proof (R06). |
| [p0-court-discovery-and-schedule.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p0-court-discovery-and-schedule.spec.ts) | 4 P0 | 4/4 pass | Discovery/court/date/slot UI checks; mapping FR cần sửa (R12). |
| [p0-pos-shift-service.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p0-pos-shift-service.spec.ts) | 4 P0 | 4/4 pass | Có cash-sale UI; review/tab chủ yếu API, thiếu check-out/reload (R06). |
| [p1-account-lifecycle.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-account-lifecycle.spec.ts) | 6 P1 | 6/6 pass | Mixed UI/API/contracts; tên test trong mapping verification/OAuth sai (R12). |
| [p1-attendance-walkin-waitlist.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-attendance-walkin-waitlist.spec.ts) | 5 P1 | 5/5 pass | Service/API assertions và notification surface; cần ghi phạm vi evidence đúng. |
| [p1-community-feed-moderation.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-community-feed-moderation.spec.ts) | 5 P1 | 4/5 pass | API mutation + UI read-back; 9.2.3 timeout hai run (R17). |
| [p1-dispute-lifecycle.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-dispute-lifecycle.spec.ts) | 6 P1 | 6/6 pass | Nhiều fixture/literal proof (R11); import manifest (R21). |
| [p1-drop-in-session-bill.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-drop-in-session-bill.spec.ts) | 4 P1 | 4/4 pass | Domain service bill checks có giá trị; browser bill snapshot chưa chứng minh UI lifecycle. |
| [p1-inventory-pos.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-inventory-pos.spec.ts) | 5 P1 | 5/5 pass | Stock/POS services + inventory surface; browser action coverage cần đối chiếu từng FR. |
| [p1-legacy-wallet-direct-cohort.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-legacy-wallet-direct-cohort.spec.ts) | 4 P1 | 4/4 pass | Direct-cohort rejection/history có proof; refund operation chưa chạy (R10). |
| [p1-partner-registration.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-partner-registration.spec.ts) | 4 P1 | 4/4 pass | Full file pass; độc lập 7.2.2 fail vì prerequisite của ca trước (R07). |
| [p1-platform-subscription.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-platform-subscription.spec.ts) | 5 P1 | 5/5 pass | Mixed API/service + subscription surface; verified receipt insert (R08). |
| [p1-pricing-promotions-loyalty.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-pricing-promotions-loyalty.spec.ts) | 5 P1 | 5/5 pass | Quote/API/service checks; quản lý/campaign lifecycle chưa phải thao tác UI. |
| [p1-receiving-connections.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-receiving-connections.spec.ts) | 5 P1 | 5/5 pass | Receiver readiness/snapshot checks; raw PHP target inheritance (R01). |
| [p1-recurring-series.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-recurring-series.spec.ts) | 5 P1 | 5/5 pass | Preview/conflict API và period service/webhook checks; mutation policy chung, UI wizard chưa được thực hiện. |
| [p1-resale-marketplace.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-resale-marketplace.spec.ts) | 5 P1 | 5/5 pass | Service/webhook transfer có proof; race tuần tự + fabricated receipt (R09). |
| [p1-revenue-reconciliation-reporting.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-revenue-reconciliation-reporting.spec.ts) | 5 P1 | 5/5 pass | Controller CSV thật; không phải browser download; dữ liệu phụ thuộc (R07), import manifest (R21). |
| [p1-social-matchmaking-activity.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-social-matchmaking-activity.spec.ts) | 5 P1 | 5/5 pass | API/service mutations + UI read-back; cần map assertion cho từng FR. |
| [p1-split-payments.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-split-payments.spec.ts) | 5 P1 | 5/5 pass | Service lifecycle + signed webhook và split UI snapshot; creation/host cover chưa là thao tác UI. |
| [p1-staff-management.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-staff-management.spec.ts) | 7 P1 | 7/7 pass | Có staff control UI, request/grant checks; mutable canonical actor cần cô lập (R07). |
| [p1-tournaments-lifecycle.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-tournaments-lifecycle.spec.ts) | 5 P1 | 5/5 pass | Domain services + tournament UI snapshot; full create/register/score UI chưa được thực hiện. |
| [p1-venue-lifecycle.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p1-venue-lifecycle.spec.ts) | 5 P1 | 5/5 pass | API configuration/readiness + venue UI; matrix filename sai (R12). |
| [p2-fault-injection-resilience.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p2-fault-injection-resilience.spec.ts) | 5 P2 | 5/5 pass | Một số injected action/feedback assertion còn optional/weak (R16); discovery (R21). |
| [p2-realtime-availability-tournament.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p2-realtime-availability-tournament.spec.ts) | 3 P2 | 2/3 pass | Snapshot chưa chứng minh realtime/polling (R14); selector fail (R15), timezone (R20). |
| [p2-representative-experience.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p2-representative-experience.spec.ts) | 4 P2 | 4/4 pass | Locale/theme/modal assertions chưa chứng minh tính năng (R13); discovery (R21). |
| [p2-smoke-public-navigation.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/p2-smoke-public-navigation.spec.ts) | 5 P2 | 25/25 pass | 5 ca x 5 projects đều pass; chỉ public smoke, không money matrix; discovery (R21). |
| [seven-areas-acceptance.spec.ts](/Volumes/SSD/WorkSpace/ziet-project/booking/frontend/e2e/seven-areas-acceptance.spec.ts) | 7 unselected | Không được chọn | 7 baseline không được chọn (R19). |
