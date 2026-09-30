## Context

Xem `proposal.md` cho mục tiêu sản phẩm. Thiết kế này cần thiết vì thay đổi đi xuyên layout, theme, toàn bộ feature UI và các luồng có trạng thái tài chính.

- `frontend/src/App.vue` dùng một Navbar chung, RouterView và footer ngoài admin. Không có cây ứng dụng riêng theo phiên bản giao diện.
- `shared/layouts/header/components/NavbarUserProfile.vue` đã có preferences cho cả khách và tài khoản; `NavbarMobileDrawer.vue` có theme/language controls và được render ra `body`.
- `stores/theme.ts` quản lý light/dark bằng `aura_theme`, `html.dark` và `data-theme`. Các thao tác storage hiện chưa được bảo vệ khi trình duyệt từ chối truy cập. `index.html` chưa có bước khôi phục phiên bản/theme trước render.
- `assets/styles/variables.css` có semantic tokens và các alias cũ; nhiều stylesheet/component còn Burgundy/Gold viết trực tiếp, dark overrides và `!important`. Chỉ import `colors.css` không tạo ra V2 hoàn chỉnh.
- `ScheduleView.vue` giữ một instance `useCourtSchedule()` chứa date/court/filter/quote cục bộ; `PaymentView.vue` bắt đầu/dừng timer theo vòng đời màn hình. Thay RouterView theo V1/V2 có nguy cơ reset state và khởi động lại side effects.
- Có sẵn AuraButton, form controls, feedback components, feature components, unit tests và browser tests. `add-playwright-functional-coverage` đang phát triển; không sửa artifacts hoặc ghi đè công việc của change đó.
- `docs/design/erabook/` giữ 1.950 icon, 189 component variants, 119 elements với SVG và metadata. SVG đã outline chữ, thiếu Auto Layout và một phần typography metadata: đây là nguồn tham chiếu, không phải component chạy được hay layout pixel-perfect hoàn chỉnh.
- Codebase đã có API/types/views mang chữ V2 (ví dụ V2PriceQuote và V2PricingCalendarView); “UI V2” trong change này chỉ là presentation, không phải phiên bản API hay payment model.

## Goals / Non-Goals

**Goals:**

- Một nguồn state nghiệp vụ và một cây route đang hoạt động; phiên bản chỉ ảnh hưởng lớp trình bày.
- Thay đổi có thể đảo chiều ngay, cô lập CSS V1/V2 và có kiểm chứng giữ state.
- Hoàn tất từng nhóm route bằng bằng chứng, không coi bảng màu mới là bản redesign đã xong.
- Tận dụng thành phần hiện có, không dựng một framework theme hoặc thư viện UI mới.

**Non-Goals:**

- Không backend preference, cookie/cross-device synchronization, bảng dữ liệu, route `/v2`, engine feature flags hoặc registry component động.
- Không xây nghiệp vụ/màn hình mới chỉ vì chúng xuất hiện trong Erabook; không sửa lỗi business ngoài phạm vi dưới tên redesign.
- Không xóa V1, đổi mặc định sang V2, phê duyệt cohort tài chính, thực hiện giao dịch thật hoặc deploy production.
- Không sao chép nội dung ebook, nguyên board SVG hay font outline vào controls của Aura Court.

## Decisions

### D1. Một ứng dụng, hai presentation trên cùng state

Giữ nguyên router, guards, route names/aliases, URLs, SEO, API và domain composables. Đặt `data-ui-version="v1|v2"` trên `html`, bên cạnh thuộc tính dark/light hiện có. Không dùng phiên bản làm `key` cho App, RouterView, layout hoặc stateful feature components; không render đồng thời hai cây màn hình ẩn/hiện vì vẫn có thể nhân đôi effects.

Ưu tiên CSS/layout và component đang có. Chỉ tách presentation con khi bố cục khác đáng kể; state và side effects ở parent ổn định, con nhận props/phát events. Tránh unmount input để giữ focus, selection, validation và nội dung chưa lưu. CSS reflow không cam kết giữ nguyên tọa độ pixel của scroll, nhưng phải giữ ngữ cảnh thao tác và không đưa người dùng về đầu luồng.

**Alternatives:** route/app V2 riêng dễ tách code nhưng nhân đôi navigation và mất state khi chuyển; đổi toàn bộ page component có cùng rủi ro. Chọn presentation trong cây hiện tại vì phục vụ switch tại chỗ và giữ nghiệp vụ tốt nhất.

### D2. Preference nhỏ, cục bộ, độc lập sáng/tối

Thêm store nhỏ tại `features/appearance/stores/appearanceStore.ts` cho `v1|v2`, lưu khóa `aura_ui_version`; không thêm store vào thư mục legacy `src/stores`. Chỉ chấp nhận hai giá trị hợp lệ; không có/sai giá trị → V1. Login/logout không xóa preference. Các controls trong một ứng dụng dùng cùng state; không yêu cầu đồng bộ live giữa các tab, tab mới hoặc reload đọc giá trị đã lưu.

Khôi phục preference và màu sáng/tối trước mount/first application paint trong startup hiện có. Bootstrap và store phải cùng luật default/validation, không có hai nguồn chọn phiên bản mâu thuẫn. Giữ `aura_theme` và API theme cũ; bảo vệ read/write storage ở cả bootstrap và theme hiện có để chế độ từ chối storage không làm crash trước khi switch hoạt động. Storage lỗi → runtime memory vẫn hoạt động, không báo đã lưu. Không cần toast “đã lưu” cho mỗi lần bật/tắt.

**Alternatives:** gộp thành một enum gồm bốn theme làm lẫn hai lựa chọn; lưu lên tài khoản cần API mới nhưng không có nhu cầu. Dùng hai trục độc lập, browser-local là đủ. Không cần migration dữ liệu nghiệp vụ.

### D3. Tái sử dụng một control nhỏ ở profile và mobile

Thêm `AuraUiVersionSwitch` dưới shared controls, thuần UI qua props/events; binding preference ở các layout entry points. Đặt cạnh theme/language, nhãn VI “Giao diện Erabook V2”, EN tương đương; on=V2, off=V1. Nút không submit form, có checked state và focus rõ; guest cũng được sử dụng.

Khi nằm trong menu phải dùng semantics phù hợp với container (ví dụ menuitemcheckbox trong menu, switch trong preferences group thông thường), không gắn tùy tiện nhiều role xung đột. Giữ control mở và focus sau bật/tắt; Escape đóng menu và trả focus về trigger. Không gọi lại fetch-wallet chỉ vì đổi appearance. Dropdown guest/auth dùng cùng control, không nhân đôi logic.

**Alternatives:** trang settings mới hoặc nút chỉ cho tài khoản làm thêm bước, không đáp ứng entry point được yêu cầu. Chọn entry points sẵn có; không redesign toàn bộ hệ menu ngoài phần cần thiết.

### D4. Theme tokens cô lập và semantic adaptation

Giữ V1 làm mặc định khi không có attribute. Tạo stylesheet V2 tại `assets/styles/erabook.css`, nhập qua `main.css`; chỉ áp dụng dưới `[data-ui-version="v2"]` và biến thể dark tương ứng. Shared tokens (`--bg`, `--surface`, `--ink`, `--primary`, `--on-primary`, border/status/shadow…) được resolve theo phiên bản/mode. Giữ alias cũ đang có theo spec, không thêm lớp compatibility mới. Trong component dùng scoped CSS cho hình dạng/bố cục; chuyển màu V1 viết trực tiếp sang token với default V1 không đổi khi cần, thay vì chồng hàng loạt `!important`.

V2 brand orange #F89300 không đồng nghĩa mọi chữ/link đều màu này. CTA cam ưu tiên chữ tối #212121 và kiểm tra tỷ lệ; token link/focus/border có biến thể đủ tương phản ở từng nền. Màu cảnh báo/thành công từ bản thiết kế cũng cần kiểm tra. Ngày/slot/payment state luôn có nhãn hoặc biểu tượng ngoài màu.

Định nghĩa scale nhỏ cho type/space/radius/elevation dựa trên references cần dùng; những giá trị thiếu trong metadata ghi rõ “adapted” trong bảng mapping, không giả nhận thông số Figma. Bố cục desktop có lưới/cards, mobile gọn; schedule và POS giữ mật độ phù hợp. Không thêm dashboard kiểu ebook, bottom navigation hoặc tính năng mới không có trong phạm vi hiện tại.

`html` làm scope để các overlay ở `body` nhận theme; kiểm tra actual computed style của drawer, dialog và popover. Giữ lưu ý hiện tại về positioning của Teleport: không tùy tiện thêm inline color-scheme trên `html`. Rà cả progress bar, hover/focus/disabled, skeleton, inline errors, icon fills và logo/brand contrast.

**Alternatives:** CSS-only palette swap nhanh nhưng bỏ sót hardcoded styles và bố cục; sao chép toàn bộ component library tăng maintenance. Chọn token + sửa có kiểm soát lớp presentation của component đang dùng.

### D5. Font và asset runtime là tập con có nguồn gốc

V2 dùng Be Vietnam Pro, giữ font V1 hiện tại. Ưu tiên tự lưu WOFF2 có đủ latin/vietnamese và trọng số thực sự dùng; xác minh nguồn/license, lưu license cùng asset, có `font-display: swap` và fallback sans-serif. Không tải font trong bước proposal. Thử dấu tiếng Việt, chuỗi dài, layout khi font lỗi; không vô tình ghi đè font của Material Symbols còn dùng ở V1.

Chọn Iconly cần dùng từ `assets-index.json`, giữ nodeId/path nguồn trong tài liệu mapping. Nhập asset tĩnh cụ thể thay vì glob cả kho. Không inline SVG không kiểm tra: rà script/event/external references và tránh trùng IDs ở gradient/clipPath khi inline nhiều instance. Chỉ thay icon có counterpart phù hợp; icon đặc thù thể thao được giữ/adapt có ghi chú, không dùng icon ebook sai ý nghĩa.

Toàn bộ component SVG chỉ là reference: button/input/menu/modal được xây bằng controls có semantics, text thật và behaviors hiện có. Snapshot gốc trong `docs/design/erabook` không đổi; mapping/bằng chứng implementation đặt trong `docs/design/erabook/ui-v2/` khi apply.

**Alternatives:** runtime import cả 35 MB reference tree tăng tải vô ích; raster/outlined components mất i18n và accessibility. Dùng tập con asset và controls hiện có.

### D6. Route inventory là đơn vị hoàn thành

Bảng dưới đây là baseline từ router tại lúc đề xuất; mỗi dòng được triển khai theo route con và trạng thái, không đánh dấu cả nhóm chỉ vì shell đã đổi. Khi apply, đối chiếu router mới nhất và ghi bảng route → component → source reference/adaptation → role/state → test/ảnh → trạng thái tại `docs/design/erabook/ui-v2/coverage.md`. Không mở thêm routes mới theo suy đoán.

| Đợt | Nhóm / routes hiện có | Trọng tâm thiết kế |
| --- | --- | --- |
| 1 | Shell dùng chung, dropdown profile guest/auth, mobile drawer, admin shell, footer, progress, overlays | Switch, nền typography/tokens/controls; shell admin ở đây, nội dung admin ở đợt 4 |
| 2 | `/`, `/about`, `/courts`, `/courts/:slug`, `/courts/:slug/schedule`, `/courts/:slug/directions` | Discovery, court cards/filter, detail, lịch và summary; giữ thao tác directions |
| 3 | `/login`, `/register`, `/onboarding`, `/forgot-password`, `/reset-password`, `/terms`, `/privacy`, not-found | Auth/forms, văn bản dài và trạng thái lỗi |
| 3 | `/booking` + `/bookings`, `/history`, `/my-bookings`; `/payment/:code?`, `/wallet`, `/profile`, `/notifications` | Đơn đặt, countdown/QR/receiver, lịch sử ví, chỉnh hồ sơ và read states |
| 3 | `/recurring-booking`, `/recurring-booking/cycles/:id`, `/split-bill/:code?`, `/resale` | Lịch định kỳ, chu kỳ tiền, chia tiền, resale; giữ entitlement và trạng thái thật |
| 4 | `/tournaments`, `/tournaments/:id/bracket`, `/tournaments/:id/live`, `/matchmaking` + `/tournaments/social`, `/matchmaking/create`, `/social/sessions/:id/bill` | Bracket/live/realtime, tham gia/tạo trận, bill nhóm |
| 4 | `/partner-registration`, `/partner/application-status`, `/partner/subscription` | Forms, trạng thái duyệt và subscription |
| 4 | `/admin`, `/admin/manage`, `/admin/schedule`, `/admin/venues`, `/admin/venues/publication-queue`, `/admin/pricing`, `/admin/contracts`, `/admin/organization`, `/admin/staff` | Quản lý sân/tổ chức, lịch/giá dày dữ liệu, quyền theo vai trò |
| 4 | `/admin/tournaments`, `/admin/pos` + redirect `/pos`, `/admin/shifts`, `/admin/inventory`, `/admin/membership` | POS cart, ca, tồn kho, hội viên và giải đấu; thao tác nhanh không mất dữ liệu |
| 4 | `/admin/payouts`, `/admin/financial-exceptions`, `/admin/reconciliation`, `/admin/kyc`, `/admin/disputes` | Tài chính, đối soát, duyệt/hồ sơ/tranh chấp; nhận diện pending/error rõ |

Một route hoàn tất phải có nội dung V2 hợp ngữ cảnh, các control/states/overlays nhất quán, actions đúng quyền, responsive và bằng chứng. State không tồn tại cần ghi N/A có lý do; tính năng backend thiếu/khóa phải giữ trạng thái thật, không mock thành hoạt động để đóng task.

**Alternatives:** customer-only cut tự ý thu hẹp plan đã đồng ý; một đợt toàn bộ khó review. Chọn đủ frontend nhưng chia route families; các đợt là thứ tự làm, không tự cấp quyền early production rollout.

### D7. Kiểm thử dùng nền hiện có, không nhân bản mọi tổ hợp

Unit/component tests tập trung vào preference normalization/storage failure, control semantics, bốn tổ hợp version/mode và state-owner không bị tái tạo khi switch. Browser tests dùng runner/fixtures được xác nhận từ change coverage đang phát triển; không sửa/chạy reset môi trường dùng chung hoặc gọi tiền thật. Nếu hạ tầng đó chưa sẵn sàng, component/browser-isolated checks vẫn làm được nhưng evidence E2E liên quan giữ pending, không tự dựng runner thứ hai.

Ma trận tối thiểu:

- Tất cả route families: V2 normal + các states có thực, role hợp lệ và overlays; kiểm tra route aliases/redirects không đổi.
- Shell và màn đại diện home, court list/detail, schedule, payment, profile, admin table và POS: ảnh deterministic cho V1/V2 × light/dark ở mobile 390px và desktop 1440px; stress layout 320px với chuỗi VI dài. Dense grids được cuộn trong vùng có nhãn, không tràn toàn trang che thao tác.
- Critical journeys: guest/auth discovery → chọn slot → reservation → payment status; profile unsaved form; POS cart/checkout; quyền guest/customer/owner/staff/cashier/platform admin phù hợp với fixtures. Chạy V1 và V2, thêm switch cả hai chiều ở điểm state-sensitive thay vì nhân đôi toàn bộ suite.
- VI/EN, keyboard/focus/Escape, modal/drawer/popover, reduced-motion và font-failure trên shared controls cùng màn đại diện. Rà tương phản text/control/focus ở light/dark.
- So URL, field values, selected IDs, deadline, pending flag, request mutation count và kết quả domain trước/sau switch. Payment/realtime vẫn cập nhật đúng, không thêm timers/subscriptions từ đổi version.
- Ghi source revision, môi trường, command/check, kết quả, ảnh/trace và gaps. Dùng checks `type-check`, build, unit run và browser suites có sẵn; không chạy lint auto-fix trên toàn repo để tránh sửa ngoài phạm vi.

**Alternatives:** full Cartesian product mọi route × mọi locale × mọi viewport × mọi role tốn chi phí không cần thiết; chỉ screenshot bỏ sót state loss. Chọn route coverage đầy đủ + tổ hợp đại diện + behavioral checks ở các điểm rủi ro.

## Risks / Trade-offs

- [V1/V2 CSS lẫn nhau, nhất là `html.dark`/`!important`] → cô lập overrides, rà computed styles và chụp baseline V1 trước đổi component; giữ default tokens cũ.
- [Unmount làm mất form/quote hoặc lặp thanh toán] → không version-key route/stateful tree; nếu cần chia view, giữ state/effects ở parent và bắt buộc test lifecycle + mutation count.
- [Font mới làm cắt dấu hoặc tràn nút] → line-height/width linh hoạt, test VI/EN dài, font fail và mobile trước đóng từng nhóm.
- [Thiếu metadata nên không thể cam kết 1:1 Figma] → ghi mapping/adaptation; đối chiếu SVG đúng nhóm, không đoán layout thiếu như dữ liệu gốc.
- [SVG nguồn có reference hoặc ID khó tái sử dụng] → rà subset trước runtime import, test nhiều instances và giữ provenance; không sửa snapshot gốc.
- [Route list/coverage change tiếp tục thay đổi] → đọc lại router và artifacts liên quan khi apply; reconcile bằng context, giữ thay đổi người khác và ghi gap nếu chưa có prerequisite.
- [Scope toàn frontend lớn] → review từng đợt, chỉ công nhận route đã có evidence; chưa xong không bật default V2 hay gắn nhãn hoàn tất.
- [V1 quay lại không chữa lỗi logic dùng chung] → user switch chỉ rollback presentation; lỗi shared logic cần revert patch liên quan và retest, không thay payment/history để “rollback”.

## Migration Plan

1. Chụp baseline V1 và khởi tạo inventory từ router hiện hành; xác nhận source asset mapping, không thay main specs trong giai đoạn implement.
2. Làm nền preference/switch/theme/components ở môi trường local/test; V1 vẫn default và không cần data migration.
3. Hoàn thành từng nhóm route theo đợt 2–4, cùng kiểm thử trạng thái và baseline V1 tại mỗi đợt. Intermediate builds chỉ dành cho review; preview ngoài phạm vi đó cần phê duyệt riêng, nhãn thử nghiệm và danh sách routes/gaps rõ ràng.
4. Trước coi change hoàn tất: đủ route coverage, tests critical V1/V2, ảnh và accessibility checks; rà bundle không chứa kho SVG nguồn; ghi nguồn/license font và assets.
5. Bàn giao opt-in V2 với V1 còn nguyên. Việc deploy production hoặc đổi mặc định là quyết định riêng, không nằm trong apply của proposal này.
6. Rollback presentation: người dùng tắt switch, giữ state/session/URL. Nếu cần rollback bản phát hành do lỗi diện rộng, revert thay đổi UI qua quy trình deploy riêng; lưu trữ `v2` không được làm crash bản chỉ có V1. Không xóa preference, dữ liệu đặt sân, receipts hoặc lịch sử ví để rollback.
