## 1. Baseline và traceability

- [x] 1.1 Đọc lại router, theme/layout và trạng thái change `add-playwright-functional-coverage`; xác nhận các thay đổi đang có, không ghi đè công việc khác, ghi quyết định/prerequisite vào coverage notes.
- [x] 1.2 Tạo `docs/design/erabook/ui-v2/coverage.md` từ route inventory trong design D6, tách từng route/alias, nối role/state → component → nhiệm vụ → bằng chứng; tất cả khởi tạo pending, N/A phải có lý do.
- [x] 1.3 Chụp baseline V1 light/dark ở các màn đại diện theo D7 với dữ liệu ổn định; ghi revision/environment và các lỗi có sẵn để không nhầm với hồi quy V2.
- [x] 1.4 Lập mapping các token/component/icon cần dùng tới nodeId/source trong Erabook; đánh dấu thông số adapted và kiểm tra source/license/font availability, không sửa snapshot gốc.

## 2. Đợt 1 — preference và switch

- [x] 2.1 Thêm appearance state trong feature phù hợp với `v1|v2`, default/validation và storage `aura_ui_version`; test thiếu/sai giá trị, đọc/ghi bị từ chối, chuyển hai chiều và persistence khi reload.
- [x] 2.2 Khôi phục version + theme trước first application paint, đồng nhất bootstrap/store; bảo vệ storage ở theme cũ trong phạm vi cần thiết, kiểm tra saved V2-dark deep link không nháy V1 và app vẫn mở được khi storage lỗi.
- [x] 2.3 Tạo control `AuraUiVersionSwitch` thuần UI với tên/checked state, touch/keyboard và không submit; thêm nhãn VI/EN và unit/component checks.
- [x] 2.4 Tích hợp control vào profile dropdown guest/auth và mobile drawer bằng cùng appearance state; kiểm tra giữ focus khi đổi, Escape/return focus, login/logout không reset và không fetch wallet do switch.
- [x] 2.5 Thêm checks cho bốn tổ hợp V1/V2 × light/dark, locale độc lập, URL query/hash giữ nguyên; xác nhận không version-key hoặc nhân đôi App/RouterView/stateful tree.

## 3. Đợt 1 — foundation, shared controls và shell

- [x] 3.1 Tạo token stylesheet V2 có light/dark, semantic foreground/status/focus và alias hiện hữu; scope theo root version, giữ defaults V1, có computed-style/contrast checks cho tổ hợp đại diện.
- [x] 3.2 Thêm font Be Vietnam Pro đúng nguồn/license và glyph VI/EN, chỉ áp dụng V2; kiểm tra weights, dấu tiếng Việt, fallback/font-failure và không ảnh hưởng icon font V1.
- [x] 3.3 Chọn/import tập con Iconly cần dùng, kiểm tra SVG references/IDs/an toàn và nhiều instances; ghi provenance, bảo đảm không bundle toàn kho thiết kế.
- [x] 3.4 Áp dụng shape/type/spacing/elevation V2 cho AuraButton và nhóm form controls hiện có (input/select/date/time), đủ hover/focus/loading/disabled/error; test semantics và snapshot V1 không hồi quy.
- [x] 3.5 Áp dụng V2 cho card/list/table primitives đang dùng, skeleton/loading/error/empty và modal/popover; không tạo abstraction mới nếu component/CSS hiện có đã đủ, kiểm tra text thật/i18n.
- [x] 3.6 Hoàn thiện Navbar/profile/mobile drawer/footer/progress và admin shell; kiểm tra overlays ở body nhận V2 light/dark, focus không mất và layout fixed không lệch.
- [x] 3.7 Nghiệm thu đợt 1 bằng bộ unit/component tests liên quan và ảnh shell V1/V2 mobile/desktop, ghi gaps và chưa đánh dấu các route nội dung là hoàn tất.

## 4. Đợt 2 — discovery và chọn lịch

- [x] 4.1 Thiết kế V2 home/about và danh sách sân: navigation, search/filter, court cards và responsive; giữ dữ liệu/filter hiện tại, kiểm tra normal/loading/empty/error.
- [x] 4.2 Thiết kế V2 chi tiết sân và directions, giữ toàn bộ thông tin/hành động được phép; kiểm tra mobile, chuỗi dài, trạng thái thiếu dữ liệu.
- [x] 4.3 Thiết kế V2 date/court selectors, slots grid và booking summary; giữ state owner ổn định, có cue không chỉ màu cho giờ trống/chọn/bận và summary dễ dùng trên mobile.
- [x] 4.4 Kiểm tra chuyển cả hai chiều khi đã chọn venue/court/date/filter/nhiều slot: không reset, không đổi quote context, không phát sinh reservation do switch; chạy discovery/schedule regression V1/V2 và cập nhật coverage/ảnh.

## 5. Đợt 3 — auth và tài khoản

- [x] 5.1 Áp dụng V2 cho login/register/onboarding/forgot/reset password; giữ validation, redirect và form state khi switch; kiểm tra guest-only/auth behavior không đổi.
- [x] 5.2 Áp dụng V2 cho terms/privacy/not-found, kiểm tra văn bản dài, links, VI/EN và route không hợp lệ.
- [x] 5.3 Áp dụng V2 cho profile/notifications, giữ unsaved fields, errors và read states; kiểm tra switch không submit, logout/login giữ appearance.
- [x] 5.4 Áp dụng V2 cho wallet và lịch sử booking cùng aliases; giữ legacy balances/history, quyền đổi/hủy và trạng thái tiền thật, kiểm tra empty/error/long lists theo dữ liệu hiện có.

## 6. Đợt 3 — payment và booking mở rộng

- [x] 6.1 Áp dụng V2 cho payment summary, owner instructions/QR/receiver, coupon/loyalty và các nhánh hiện có, gồm legacy/tournament khi được hỗ trợ; giữ nguyên API/receiver/amount/business validation.
- [x] 6.2 Kiểm chứng bidirectional switch trong hold/pending request/payment success/error/expiry: giữ reference/deadline, timer/subscription không nhân đôi, mutation count không tăng do switch; ghi rõ bằng chứng mô phỏng so với integrated test.
- [x] 6.3 Áp dụng V2 cho recurring booking và cycle detail, giữ lịch/quote/entitlement; kiểm tra chọn dữ liệu và form đang nhập vẫn còn sau switch.
- [x] 6.4 Áp dụng V2 cho split bill và resale, giữ actions theo quyền và trạng thái thật; kiểm tra pending/error/disabled và deep links.
- [x] 6.5 Chạy các hành trình khách hàng/auth/booking/payment liên quan ở V1 và V2 bằng runner an toàn hiện có; cập nhật coverage từng route, ảnh và gaps, không coi mock payment là provider acceptance.

## 7. Đợt 4 — cộng đồng và đối tác

- [x] 7.1 Áp dụng V2 cho tournaments/list/bracket/live; giữ cấu trúc bracket, đăng ký và realtime, kiểm tra cập nhật không nhân đôi khi đổi giao diện.
- [x] 7.2 Áp dụng V2 cho matchmaking/list/create và drop-in session bill, gồm alias social; giữ form/actions/bill state và quyền hiện có.
- [x] 7.3 Áp dụng V2 cho partner registration/application status/subscription; kiểm tra forms dài, trạng thái duyệt/thanh toán và disabled capability trung thực.
- [x] 7.4 Ghi nghiệm thu nhóm cộng đồng/đối tác theo route/state/role, kiểm tra ảnh mobile/desktop và regression V1 trên các điểm đã chỉnh.

## 8. Đợt 4 — quản trị và vận hành

- [x] 8.1 Áp dụng V2 cho admin overview/manage, giữ navigation và dữ liệu theo role/tenant; kiểm tra deep links và redirect `/admin` không đổi.
- [x] 8.2 Áp dụng V2 cho admin schedule/pricing với bảng dày dữ liệu và thao tác chọn giờ/giá; kiểm tra vùng scroll mobile, state khi switch và trạng thái loading/error.
- [x] 8.3 Áp dụng V2 cho venues/publication queue, giữ forms/approval actions và quyền tương ứng; kiểm tra draft form khi đổi phiên bản.
- [x] 8.4 Áp dụng V2 cho organization/staff/contracts, giữ unsaved data, validation và role-specific actions; ghi evidence từng route.
- [x] 8.5 Áp dụng V2 cho admin tournaments và membership, giữ actions/entitlements và trạng thái thật; kiểm tra mobile/dark.
- [x] 8.6 Áp dụng V2 cho POS và alias `/pos`, kiểm tra cart/quantities/pending checkout tồn tại qua switch, không lặp sale/check-in/check-out hoặc thao tác service-tab hiện có.
- [x] 8.7 Áp dụng V2 cho shifts/inventory, giữ trạng thái ca, form/cart liên quan và quyền; kiểm tra dense tables, pending/error và thao tác nhanh.
- [x] 8.8 Áp dụng V2 cho payouts/financial exceptions/reconciliation, giữ money/receiver/status/actions thật; kiểm tra bảng, filters và dialogs không mất context khi switch.
- [x] 8.9 Áp dụng V2 cho KYC/disputes, giữ validation/approval/access; kiểm tra file/form state và đối chiếu quyền không đổi.
- [x] 8.10 Chạy operational/POS/access regressions V1/V2 với các vai trò có fixture, ghi evidence/ảnh/gaps cho toàn bộ admin routes; không cấp thêm quyền để làm test pass.

## 9. Nghiệm thu và bàn giao

- [x] 9.1 Đối chiếu lại toàn bộ router với coverage, kể cả aliases, redirects, not-found và overlays; không đóng route chỉ vì đã đổi màu, giải thích từng N/A và xử lý mọi gap trong phạm vi.
- [x] 9.2 Hoàn thành ma trận ảnh đại diện D7: V1/V2 × light/dark, 390px/1440px; stress 320px, VI/EN dài, reduced-motion/font failure; xác nhận không che controls hay mất dấu tiếng Việt.
- [x] 9.3 Kiểm tra keyboard/focus/Escape và tương phản text/control/status/focus theo theme spec; sửa sai lệch trong phạm vi và ghi riêng thay đổi accessibility dùng chung ảnh hưởng V1.
- [x] 9.4 Chạy type-check, build, unit run và các browser suites liên quan ở môi trường cô lập; ghi command/revision/environment/results, phân biệt lỗi có sẵn với regression; thiếu hạ tầng/evidence phải giữ pending.
- [x] 9.5 Kiểm tra runtime assets/bundle chỉ chứa subset cần dùng, font/source license và mapping đầy đủ, snapshot Erabook gốc không bị sửa.
- [x] 9.6 Diễn tập rollback presentation V2 → V1 khi đang có unsaved form, booking/payment và POS state; xác nhận history/reference/deadline/URL không đổi và V1 baseline còn đúng.
- [x] 9.7 Hoàn thiện acceptance notes và hướng dẫn bật/tắt V2, tổng kết route/state evidence cùng operational gates ngoài phạm vi; bàn giao opt-in V2, không tự deploy, đổi mặc định hoặc đánh dấu provider readiness.
