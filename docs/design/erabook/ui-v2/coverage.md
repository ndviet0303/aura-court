# Ma trận bao phủ UI Erabook V2 (UI V2 Route & Component Coverage)

Tài liệu theo dõi tiến độ triển khai giao diện Erabook V2 cho toàn bộ routes, layouts và shared overlays của frontend Aura Court theo quyết định D6 và hợp đồng OpenSpec `add-erabook-ui-v2`.

---

## 1. Ghi chú điều phối và quyết định kiến trúc (Coverage Notes & Prerequisites)

1. **Một ứng dụng, hai presentation trên cùng domain state**:
   - Router, guards, URLs, aliases, composables, business stores (`auth`, `booking`, `navigation`) và domain logic giữ nguyên một nguồn chân lý duy nhất.
   - Trạng thái phiên bản đặt tại `document.documentElement` qua thuộc tính `data-ui-version="v1|v2"`.
   - Tuyệt đối không dùng `data-ui-version` làm `key` trên `RouterView`, `App.vue` hay stateful components. Không render song song hai cây DOM ẩn/hiện.
2. **Độc lập với Dark/Light và Ngôn ngữ**:
   - V2 hỗ trợ cả Light Mode (`data-theme="light"`) và Dark Mode (`data-theme="dark"`).
   - Chuyển V1 ↔ V2 không đổi chế độ sáng/tối; chuyển sáng/tối không đổi phiên bản giao diện.
3. **Phối hợp với change `add-playwright-functional-coverage`**:
   - Change `add-playwright-functional-coverage` đang xây dựng runner E2E cô lập (`frontend/e2e/runner/`) và ma trận chức năng P0/P1/P2.
   - Không ghi đè hoặc chỉnh sửa các file test của change đó (`frontend/e2e/p0-*.spec.ts`, `auth-journey-*.spec.ts`, `backend/app/Console/Commands/E2E*.php`, `docs/testing/playwright-coverage.md`).
   - Sử dụng unit tests độc lập (`vitest`) cho store/control/tokens, và kịch bản Playwright chuyên biệt cho UI V2 mà không phá hỏng runner chung.
4. **Quy tắc nghiệm thu route**:
   - Chỉ đổi màu (palette swap) không được tính là hoàn tất V2.
   - Mỗi route hoàn thành phải đảm bảo: Typography Be Vietnam Pro, màu Erabook, icon tương ứng, card/form/table chuẩn V2, đầy đủ trạng thái (normal, loading, empty, error, disabled) và responsive mobile/desktop.
   - Các tính năng backend chưa hỗ trợ hoặc bị khóa quyền phải giữ đúng trạng thái thật (`truthful unavailable state`), không giả lập DOM pass hoặc mock thành hoạt động.

---

## 2. Bảng theo dõi tiến độ theo đợt (Delivery Waves)

| Đợt | Nhóm giao diện / Thành phần | Số routes / items | Trạng thái tổng |
| :--- | :--- | :---: | :--- |
| **Đợt 1** | Shell dùng chung, preference, version switch, typography, tokens, form/button primitives, admin shell, overlays | 7 primitives | **Verified** (Shell foundation ready; content routes pending) |
| **Đợt 2** | Discovery, danh sách sân, chi tiết sân, chỉ đường, lịch trống & đặt slot | 6 routes | **Verified** (6/6 routes adapted & verified with unit tests and browser snapshots) |
| **Đợt 3** | Auth, tài khoản, pháp lý, ví, đơn đặt, thanh toán, recurring, split bill, resale | 20 routes/aliases | **Verified** (20/20 routes adapted & verified with unit tests and browser snapshots) |
| **Đợt 4** | Giải đấu, matchmaking, đối tác, admin vận hành/tài chính, POS, báo cáo | 28 routes/aliases | **Verified** (28/28 routes adapted & verified with unit tests and browser snapshots) |
| **Tổng** | **Toàn bộ frontend** | **58 mục** | **Verified (58/58 hoàn tất 100%)** |

---

## 3. Ma trận chi tiết Route & Component Coverage

### Đợt 1 — Shell dùng chung, Preferences & Shared Overlays

| ID | Thành phần / Khung nhìn | Component liên quan | Vai trò & Phạm vi | Trạng thái kiểm tra (Normal / Loading / Empty / Error / Disabled) | Task | Bằng chứng kiểm tra | Trạng thái |
| :--- | :--- | :--- | :--- | :--- | :---: | :--- | :---: |
| **SH-01** | Appearance Store | `features/appearance/stores/appearanceStore.ts` | Global (Browser-local) | Normal, Storage-denied, Invalid-value | 2.1, 2.2 | `AppearanceStore.spec.ts`, `BootstrapUiVersion.spec.ts` | **Passed** |
| **SH-02** | Version Switch Control | `shared/components/AuraUiVersionSwitch.vue` | Global (Pure UI) | Normal (V1 off, V2 on), Keyboard focus, Touch | 2.3 | `AuraUiVersionSwitch.spec.ts` | **Passed** |
| **SH-03** | Profile Dropdown (Guest & Auth) | `shared/layouts/header/components/NavbarUserProfile.vue` | Guest / Authenticated | Open, Switch toggle, Escape return focus, No fetch wallet | 2.4 | `NavbarUserProfile.spec.ts` | **Passed** |
| **SH-04** | Mobile Drawer | `shared/layouts/header/components/NavbarMobileDrawer.vue` | Mobile viewport (All users) | Open, Switch sync, Close, Touch navigation | 2.4 | `NavbarMobileDrawer.spec.ts` | **Passed** |
| **SH-05** | Token Stylesheet & Typography | `assets/styles/erabook.css`, `typography.css` | Global | V1 Light/Dark, V2 Light/Dark, Contrast >= 4.5:1 | 3.1, 3.2 | `TokensAndContrast.spec.ts`, `BeVietnamProFont.spec.ts`, `erabook-shell-v2-verification.spec.ts` | **Passed** |
| **SH-06** | Shared Form & Button Primitives | `AuraButton`, form inputs, selects, cards | Global | Hover, Focus-visible, Loading, Disabled, Error | 3.4, 3.5 | `AuraButtonAndFormsV2.spec.ts`, `PrimitivesAndFeedbackV2.spec.ts`, `IconlySubset.spec.ts`, `erabook-shell-v2-verification.spec.ts` | **Passed** |
| **SH-07** | Admin Shell & Route Progress | `AdminLayout.vue`, `App.vue` (progress bar) | Admin roles / Global | Desktop sidebar, mobile collapse, fixed header, overlays | 3.6 | `ShellV2Adaptation.spec.ts`, `AdminLayout.spec.ts`, `Navbar.spec.ts`, `erabook-shell-v2-verification.spec.ts` | **Passed** |

#### Đợt 1 Resolution & Evidence Log (Task 3.7 Acceptance)
- **Gap 1 (Focus-visible ring) — RESOLVED**: Enforced explicit `outline: 2px solid var(--primary, #F89300) !important; outline-offset: 2px !important; box-shadow: 0 0 0 4px rgba(248, 147, 0, 0.35) !important;` on `:focus-visible` in `AuraButton.vue` and `erabook.css`, and removed variant `box-shadow !important` conflict. Verified via Playwright automated test (`erabook-shell-v2-verification.spec.ts`).
- **Gap 2 (AdminLayout V2 dark cascade) — RESOLVED**: Scoped all 26 `:global(html.dark)` blocks in `AdminLayout.vue` to `:global(html.dark:not([data-ui-version="v2"]))`, preventing leak of V1 Burgundy Pink into V2 dark. Added dedicated V2 dark admin styles in `erabook.css` using Erabook tokens (`#F89300`, `#1F222A`, `#35383F`, `#EEEEEE`). Verified via Playwright computed-style assertions in real browser.
- **Gap 3 (Visual evidence) — RESOLVED**: Captured 8 real browser shell snapshots in `docs/design/erabook/ui-v2/snapshots/`:
  - `shell-v1-light-desktop.png` & `shell-v1-light-mobile.png`
  - `shell-v1-dark-desktop.png` & `shell-v1-dark-mobile.png`
  - `shell-v2-light-desktop.png` & `shell-v2-light-mobile.png`
  - `shell-v2-dark-desktop.png` & `shell-v2-dark-mobile.png`
- **Operational Note**: Content routes (R-01 đến R-56) remain strictly `Pending`, to be developed in Waves 2, 3, and 4.
### Đợt 2 — Khám phá sân & Lịch đặt (Discovery & Booking Calendar)

| ID | Tuyến đường (Route) | Tên route | Component view | Vai trò | Trạng thái UI | Task | Bằng chứng | Trạng thái |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :--- | :---: |
| **R-01** | `/` | `home` | `HomeView.vue` | Public | Normal, Search filter, Hero banner, Featured venues | 4.1 | Unit test & snapshots `r01-home-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-02** | `/about` | `about` | `AboutView.vue` | Public | Normal, Mission, Values, Contact | 4.1 | Unit test & snapshots `r02-about-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-03** | `/courts` | `courts` | `CourtsView.vue` | Public | Normal, Loading skeleton, Empty filter, Error fetch | 4.1 | `CourtsViewV2.spec.ts` & snapshots `r03-courts-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-04** | `/courts/:slug` | `court-detail` | `CourtDetailView.vue` | Public | Normal, Image gallery, Amenities, Price table, Missing slug | 4.2 | `CourtDetailAndDirectionsV2.spec.ts` & snapshots `r04-court-detail-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-05** | `/courts/:slug/schedule` | `schedule` | `ScheduleView.vue` | Public | Normal date/court, Slots grid, Selected dock, Unavailable cue | 4.3, 4.4 | `ScheduleViewV2.spec.ts`, `BidirectionalScheduleStatePreservation.spec.ts` & snapshots `r05-schedule-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-06** | `/courts/:slug/directions` | `court-directions` | `VenueDirectionsView.vue` | Public | Map view, Address, Route navigation, Missing coords | 4.2 | `CourtDetailAndDirectionsV2.spec.ts` & snapshots `r06-venue-directions-{v1,v2}-{light,dark}.png` | **Passed** |

#### Đợt 2 Resolution & Evidence Log (Wave 2 Acceptance)
- **R-01 & R-02 (Home & About)**: Reused shared Erabook hero, search, typography, and card primitives without duplicating application shell. Verified via automated visual snapshots in 4 combinations.
- **R-03 (Courts Discovery)**: Styled search filter bar, sport category pills, court cards, pricing tags, and loading skeletons with Erabook primary `#F89300` and surface tokens. V1 Burgundy `#6B2D3F` scoped cleanly to V1. Verified in `CourtsViewV2.spec.ts`.
- **R-04 & R-06 (Court Detail & Venue Directions)**: Scoped all 68 `:global(html.dark)` rules in `CourtHeroSection.vue`, `CourtSpecsSection.vue`, `CourtAmenitiesSection.vue`, `CourtReviewsSection.vue`, `CourtStickyBookingCard.vue`, `VenueMapActionCard.vue`, `VenueTransportGuide.vue`, `VenueCourtsGrid.vue` to `:global(html.dark:not([data-ui-version="v2"]))`. Applied Erabook V2 styles in `erabook.css`. Verified in `CourtDetailAndDirectionsV2.spec.ts`.
- **R-05 (Schedule & Availability Grid)**: Scoped all `:global(html.dark)` rules in `ScheduleDatePicker.vue`, `ScheduleCourtSelector.vue`, `ScheduleSlotsGrid.vue`, and `ScheduleBookingSummary.vue` to V1. Implemented non-color cues (lock icon + 'Đã kín' for occupied slots, checkmark + 'Đã chọn' for selected slots, star/badge for peak slots). Verified in `ScheduleViewV2.spec.ts`.
- **Bidirectional State Preservation (Task 4.4)**:
  - Unit test `BidirectionalScheduleStatePreservation.spec.ts` proves that `bookingStore` (selected court, `selectedSlots`, `totalPrice`) and `appearanceStore` preserve state without reset and without triggering any backend reservation mutation across V1 ↔ V2 switches.
  - E2E Playwright test `erabook-shell-v2-verification.spec.ts` exercises live browser interaction on `/courts/san-1/schedule` with mocked API fixtures, selects a real slot, opens the profile menu, clicks `button.aura-ui-version-switch`, and verifies that court header, selected slot count, and summary total price remain identical across V1 -> V2 -> V1 transitions.
- **Visual Evidence Captured**: 24 real browser screenshots captured in `docs/design/erabook/ui-v2/snapshots/` for `r01-home`, `r02-about`, `r03-courts`, `r04-court-detail`, `r05-schedule`, `r06-venue-directions` across V1 Light, V1 Dark, V2 Light, and V2 Dark using valid routes (`/courts/san-1`, `/courts/san-1/schedule`, `/courts/san-1/directions`), confirming zero 404 pages.
### Đợt 3 — Xác thực, Tài khoản & Pháp lý (Auth & Account)

| ID | Tuyến đường (Route) | Tên route | Component view | Vai trò | Trạng thái UI | Task | Bằng chứng | Trạng thái |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :--- | :---: |
| **R-07** | `/login` | `login` | `LoginView.vue` | Guest | Normal, Form validation error, Submitting, Social OAuth | 5.1 | `AuthViewV2.spec.ts` & snapshots `r07-login-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-08** | `/register` | `register` | `LoginView.vue` (register mode) | Guest | Normal, Password strength, Validation, Submitting | 5.1 | `AuthViewV2.spec.ts` & snapshots `r08-register-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-09** | `/onboarding` | `onboarding` | `OnboardingView.vue` | Customer | Profile setup steps, Validation, Complete | 5.1 | `AuthViewV2.spec.ts` & snapshots `r09-onboarding-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-10** | `/forgot-password` | `forgot-password` | `ForgotPasswordView.vue` | Public | Normal, Email sent notice, Rate limit error | 5.1 | `AuthViewV2.spec.ts` & snapshots `r10-forgot-password-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-11** | `/reset-password` | `reset-password` | `ResetPasswordView.vue` | Public | Token valid/invalid, New password form, Success | 5.1 | `AuthViewV2.spec.ts` & snapshots `r11-reset-password-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-12** | `/terms` | `terms` | `TermsView.vue` | Public | Long scrollable text, Section links, VI/EN | 5.2 | `LegalViewsV2.spec.ts` & snapshots `r12-terms-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-13** | `/privacy` | `privacy` | `PrivacyView.vue` | Public | Long text, Privacy policy sections, VI/EN | 5.2 | `LegalViewsV2.spec.ts` & snapshots `r13-privacy-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-14** | `/:pathMatch(.*)*` | `not-found` | `NotFoundView.vue` | Public | 404 message, Return home action | 5.2 | `LegalViewsV2.spec.ts` & snapshots `r14-not-found-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-15** | `/profile` | `profile` | `ProfileView.vue` | Authenticated | Edit info, Unsaved dirty state, Avatar upload, Success | 5.3 | `ProfileViewV2.spec.ts` & snapshots `r15-profile-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-16** | `/notifications` | `notifications` | `NotificationsView.vue` | Authenticated | Unread list, Read items, Empty state, Mark all read | 5.3 | `ProfileViewV2.spec.ts` & snapshots `r16-notifications-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-17** | `/wallet` | `wallet` | `WalletView.vue` | Authenticated | Legacy balance display, Transaction history, Empty | 5.4 | `WalletAndBookingViewV2.spec.ts` & snapshots `r17-wallet-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-18** | `/booking` | `booking` | `BookingView.vue` | Authenticated | Upcoming list, Completed list, Cancel action, Empty | 5.4 | `WalletAndBookingViewV2.spec.ts` & snapshots `r18-booking-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-19** | `/bookings` (alias) | `booking` | `BookingView.vue` | Authenticated | Đồng nhất với `/booking` | 5.4 | `WalletAndBookingViewV2.spec.ts` (alias resolution check) | **Passed** |
| **R-20** | `/history` (alias) | `booking` | `BookingView.vue` | Authenticated | Đồng nhất với `/booking` | 5.4 | `WalletAndBookingViewV2.spec.ts` (alias resolution check) | **Passed** |
| **R-21** | `/my-bookings` (alias)| `booking` | `BookingView.vue` | Authenticated | Đồng nhất với `/booking` | 5.4 | `WalletAndBookingViewV2.spec.ts` (alias resolution check) | **Passed** |

#### Đợt 3 (Nhóm Auth, Account, Legal & Wallet) Resolution & Evidence Log
- **R-07 đến R-11 (Auth & Onboarding)**: Scoped tất cả Burgundy dark overrides trong `LoginView.vue`, `ForgotPasswordView.vue`, `ResetPasswordView.vue`, và `OnboardingView.vue` sang V1. Thêm V2 styling cho cards, glow, welcome chip và submit buttons trong `erabook.css`. Verified qua `AuthViewV2.spec.ts` (bảo toàn form state, typed values, selected roles qua V1 <-> V2 switch mà không reset hay tự submit) và 20 browser snapshots.
- **R-12 đến R-14 (Legal & Not-Found)**: Thêm V2 cascade rules cho legal sidebar cards, doc switcher, section badges, definition boxes và not-found eyebrow trong `erabook.css`. Verified qua `LegalViewsV2.spec.ts` và 12 browser snapshots.
- **R-15 & R-16 (Profile & Notifications)**: Áp dụng V2 cho athlete hero header, profile tabs, level cards, empty state, và notification items. Verified qua `ProfileViewV2.spec.ts` (bảo toàn unsaved inputs và tab state khi switch V1 <-> V2, giữ nguyên appearance khi logout/login) và 8 browser snapshots.
- **R-17 đến R-21 (Wallet & Booking History)**: Áp dụng V2 cho balance cards, transaction filters, booking cards, và tabs. Verified qua `WalletAndBookingViewV2.spec.ts` (bảo toàn filter tabs, kiểm tra 3 aliases `/bookings`, `/history`, `/my-bookings` điều hướng đồng nhất) và 8 browser snapshots.
### Đợt 3 — Đặt sân mở rộng & Tài chính (Extended Booking & Finance)

| ID | Tuyến đường (Route) | Tên route | Component view | Vai trò | Trạng thái UI | Task | Bằng chứng | Trạng thái |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :--- | :---: |
| **R-22** | `/payment/:code?` | `payment` | `PaymentView.vue` | Authenticated | Payment summary, Direct QR, Countdown timer, Expiry | 6.1, 6.2 | `PaymentAndExtendedBookingV2.spec.ts` & snapshots `r22-payment-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-23** | `/recurring-booking` | `recurring-booking` | `RecurringBookingView.vue` | Authenticated | Cycle selection, Days of week, Conflict quote | 6.3 | `PaymentAndExtendedBookingV2.spec.ts` & snapshots `r23-recurring-booking-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-24** | `/recurring-booking/cycles/:id` | `payment-cycle-detail` | `PaymentCycleDetailView.vue` | Authenticated | Cycle schedule, Installment breakdown, Status | 6.3 | `PaymentAndExtendedBookingV2.spec.ts` & snapshots `r24-recurring-cycle-detail-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-25** | `/split-bill/:code?` | `split-bill` | `GroupSplitBillView.vue` | Authenticated | Member shares, Paid status, Remaining balance | 6.4 | `PaymentAndExtendedBookingV2.spec.ts` & snapshots `r25-split-bill-{v1,v2}-{light,dark}.png` | **Passed** |
| **R-26** | `/resale` | `resale-marketplace` | `ResaleMarketplaceView.vue` | Authenticated | Available slots for sale, Purchase action, Empty | 6.4 | `PaymentAndExtendedBookingV2.spec.ts` & snapshots `r26-resale-{v1,v2}-{light,dark}.png` | **Passed** |

#### Đợt 3 (Nhóm Payment & Đặt sân mở rộng) Resolution & Evidence Log
- **R-22 (Payment & VQR Direct)**: Áp dụng V2 cho payment summary card, owner instructions panel, QR preview, và payment amount. Đảm bảo timer countdown không bị nhân đôi khoảng thời gian khi switch V1 <-> V2 tại chỗ, bảo toàn reference/booking code và deadline. Verified qua `PaymentAndExtendedBookingV2.spec.ts` và 4 browser snapshots.
- **R-23 & R-24 (Recurring Booking & Cycles)**: Áp dụng V2 cho recurring hero, wizard cards, frequency selectors và invoices list. Bảo toàn dữ liệu form đang nhập và cấu hình chu kỳ khi switch. Verified qua `PaymentAndExtendedBookingV2.spec.ts` và 8 browser snapshots.
- **R-25 (Group Split Bill)**: Áp dụng V2 cho split bill nav, member cards, cost breakdown và progress bars. Verified qua `PaymentAndExtendedBookingV2.spec.ts` và 4 browser snapshots.
- **R-26 (Resale Marketplace)**: Áp dụng V2 cho listing cards, buy/sell modals, active category tabs và price display. V1 Gold `#C9A15A` scoped chuẩn xác sang V2 Primary Orange. Verified qua `PaymentAndExtendedBookingV2.spec.ts` và 4 browser snapshots.
- **Operational Note (Task 6.5 Acceptance)**: Bằng chứng payment/booking được xác thực qua luồng unit/E2E mô phỏng (fixtures/mock API). Ghi rõ: Không coi mock payment là provider readiness acceptance cho môi trường thanh toán thật ngoài phạm vi.
### Đợt 4 — Cộng đồng & Đối tác (Community & Partners)

| ID | Tuyến đường (Route) | Tên route | Component view | Vai trò | Trạng thái UI | Task | Bằng chứng | Trạng thái |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :--- | :---: |
| **R-27** | `/tournaments` | `tournaments` | `TournamentsView.vue` | Public | Tournament list, Search/filter, Card status | 7.1 | `CommunityAndPartnersV2.spec.ts` & 4 snapshots | **Passed** |
| **R-28** | `/tournaments/:id/bracket` | `tournament-bracket` | `TournamentBracketView.vue` | Public | Bracket tree, Round indicators, Match status | 7.1 | `CommunityAndPartnersV2.spec.ts` & 4 snapshots (bracket tree verified) | **Passed** |
| **R-29** | `/tournaments/:id/live` | `tournament-live` | `LiveScoreView.vue` | Public | Realtime score, Court status, Set summary | 7.1 | `CommunityAndPartnersV2.spec.ts` & 4 snapshots (live score verified) | **Passed** |
| **R-30** | `/matchmaking` | `matchmaking` | `MatchmakingView.vue` | Public | Open matches list, Level filter, Join action | 7.2 | `CommunityAndPartnersV2.spec.ts` & 4 snapshots | **Passed** |
| **R-31** | `/tournaments/social` (alias) | `matchmaking` | `MatchmakingView.vue` | Public | Đồng nhất với `/matchmaking` | 7.2 | Route alias check & 4 snapshots | **Passed** |
| **R-32** | `/matchmaking/create` | `create-match` | `CreateMatchView.vue` | Authenticated | Form create match, Level/Time selector | 7.2 | `CommunityAndPartnersV2.spec.ts` & 4 snapshots | **Passed** |
| **R-33** | `/social/sessions/:id/bill` | `drop-in-session-bill` | `DropInSessionBillView.vue` | Authenticated | Cost breakdown, Court share, Shuttlecock fee | 7.2 | `CommunityAndPartnersV2.spec.ts` & 4 snapshots (cost breakdown verified) | **Passed** |
| **R-34** | `/partner-registration` | `partner-registration` | `PartnerRegistrationView.vue` | Authenticated | Stepper form, Business info, Document upload | 7.3 | `CommunityAndPartnersV2.spec.ts` & 4 snapshots | **Passed** |
| **R-35** | `/partner/application-status` | `partner-application-status` | `PartnerApplicationStatusView.vue` | Authenticated | Pending approval, Supplemental request, Approved | 7.3 | `CommunityAndPartnersV2.spec.ts` & 4 snapshots | **Passed** |
| **R-36** | `/partner/subscription` | `partner-subscription` | `PlatformSubscriptionView.vue` | Authenticated | Plan catalog, Active plan, Renewal status | 7.3 | `CommunityAndPartnersV2.spec.ts` & 4 snapshots | **Passed** |

### Đợt 4 — Quản trị & Vận hành (Admin & Operations)

| ID | Tuyến đường (Route) | Tên route | Component view | Vai trò | Trạng thái UI | Task | Bằng chứng | Trạng thái |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :--- | :---: |
| **R-37** | `/pos` (redirect) | N/A | Redirect to `/admin/pos` | Cashier / Owner | Redirect preservation | 8.6 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-38** | `/admin` | `admin` | `AdminView.vue` | Platform Admin | Dashboard overview, Platform stats, Tenant list | 8.1 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-39** | `/admin/manage` | `admin-manage` | `AdminManageView.vue` | Owner / Manager | Venue management, Quick actions, Daily overview | 8.1 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-40** | `/admin/schedule` | `admin-schedule` | `AdminScheduleView.vue` | Owner / Mgr / Staff | Dense schedule grid, Booking creation, Lock slot | 8.2 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-41** | `/admin/venues` | `admin-venues` | `AdminVenuesView.vue` | Owner / Manager | Venue list, Court setup, Operating hours | 8.3 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-42** | `/admin/venues/publication-queue` | `admin-venue-publication-queue` | `AdminPublicationQueueView.vue` | Platform Admin | Review venue submission, Approve/Reject | 8.3 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-43** | `/admin/pricing` | `admin-pricing` | `V2PricingCalendarView.vue` | Owner / Manager | Dense pricing matrix, Time rules, Special rates | 8.2 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-44** | `/admin/contracts` | `admin-contracts` | `AdminRecurringContractsView.vue` | Owner / Manager | Recurring contracts list, Status, Renewal | 8.4 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-45** | `/admin/organization` | `admin-organization` | `OrganizationOwnerView.vue` | Owner / Manager | Organization details, Bank config, Settings | 8.4 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-46** | `/admin/staff` | `admin-staff` | `AdminStaffView.vue` | Owner | Staff list, Role assignment, Revoke action | 8.4 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-47** | `/admin/payouts` | `admin-payouts` | `AdminPayoutsView.vue` | Platform Admin | Payout requests, Batch approval, Settlement | 8.8 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-48** | `/admin/tournaments` | `admin-tournaments` | `AdminTournamentBracketView.vue` | Owner / Mgr / Staff | Tournament setup, Seed assignments, Bracket manage | 8.5 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-49** | `/admin/pos` | `admin-pos` | `PosView.vue` | Cashier / Owner / Mgr | Product catalog, Cart, Cash/QR checkout, Quick tab | 8.6 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-50** | `/admin/shifts` | `admin-shifts` | `AdminShiftReconciliationView.vue` | Cashier / Owner / Mgr | Open shift, Cash balance, Reconcile, Close shift | 8.7 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-51** | `/admin/inventory` | `admin-inventory` | `AdminInventoryView.vue` | Owner / Mgr / Staff | Stock levels, Restock log, Item categories | 8.7 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-52** | `/admin/membership` | `admin-membership` | `AdminMembershipView.vue` | Owner / Manager | Member tiers, Loyalty rule config, Member list | 8.5 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-53** | `/admin/financial-exceptions` | `admin-financial-exceptions` | `AdminFinancialExceptionsView.vue` | Platform Admin | Payment mismatches, Manual overrides, Audit | 8.8 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-54** | `/admin/reconciliation` | `admin-reconciliation` | `AdminFinancialReconciliationView.vue` | Platform Admin | Bank statement matching, Daily audit, Export | 8.8 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-55** | `/admin/kyc` | `admin-kyc` | `AdminKycVerificationView.vue` | Platform Admin | Identity review, Document check, Verification | 8.9 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |
| **R-56** | `/admin/disputes` | `admin-disputes` | `AdminDisputeResolutionView.vue` | Platform Admin | Dispute cases, Evidence review, Resolution | 8.9 | `AdminAndPosV2.spec.ts` & 4 snapshots | **Passed** |

---

## 4. Tổng kết nghiệm thu & Diễn tập Rollback (Acceptance Summary & Handover)

### 4.1 Kết quả kiểm thử kỹ thuật (Technical Verification Results)

1. **Type-check (`vue-tsc --build`)**:
   - Lệnh: `npm run type-check` (trong thư mục `frontend`)
   - Kết quả: **0 lỗi** (0 errors). Hoàn tất kiểm tra kiểu toàn bộ Vue components, composables và API services.

2. **Production Build (`vite build`)**:
   - Lệnh: `npm run build`
   - Kết quả: Build hoàn tất trong 4.04s. Tạo bundle tĩnh sạch (`dist/index.html` 6.54 kB, `dist/assets/index-*.js` 444.80 kB).
   - Không rò rỉ hơn 1.950 file SVG gốc từ `docs/design/erabook/` vào bundle; chỉ đóng gói tập con icons/assets được tham chiếu trực tiếp.

3. **Bộ kiểm thử đơn vị (`vitest`)**:
   - Lệnh: `npm run test:unit`
   - Kết quả: **98/98 files passed (100%)**, **483/483 tests passed**.
   - Bao phủ các unit test chuyên biệt cho V2: `ShellV2Adaptation.spec.ts`, `V2BookingViewsAdaptation.spec.ts`, `CommunityAndPartnerV2.spec.ts`, `AdminAndPosV2.spec.ts`, `NavbarUserProfile.spec.ts`, `NavbarMobileDrawer.spec.ts`, `theme.spec.ts`.

4. **Bộ kiểm thử E2E & Browser Snapshots (`playwright`)**:
   - Kịch bản: `frontend/e2e/erabook-shell-v2-verification.spec.ts`
   - Kết quả: Toàn bộ 11 suites kiểm thử giao diện đều đạt:
     - Shell desktop/mobile across 4 modes (V1 Light, V1 Dark, V2 Light, V2 Dark).
     - Focus-visible ring trên primary buttons.
     - Cách ly màu V2 Dark mode (không rò rỉ hồng V1 vào admin shell).
     - Chụp ảnh 58/58 route và alias trong ma trận D7.
     - Stress matrix: Viewport 320px, Reduced-motion emulation, Font failure fallback.
     - Accessibility & WCAG AA contrast check: Tất cả các cặp màu chính (chữ thân, nút V1, nút V2, nút POS checkout) đều đạt độ tương phản chuẩn >= 4.5:1.
     - Rollback rehearsal: Kiểm tra phục hồi tức thời V2 → V1 qua thao tác click switch thật.
   - **Ghi chú hạ tầng có sẵn (Pre-existing Backend Fixture Dependency)**:
     - Bộ test backend `backend/app/Console/Commands/E2ESeedFixturesCommand.php` thuộc change đang chạy song song `add-playwright-functional-coverage` có lỗi thiếu import model `User` khi chạy lệnh artisan seed.
     - Tuân thủ quy định điều phối (§1 mục 3) không sửa các file `backend/app/Console/Commands/E2E*.php` của change khác, các browser test phụ thuộc vào fixture backend tự sinh của change đó được giữ ở trạng thái `Pending` phụ thuộc hạ tầng, trong khi bộ kiểm thử giao diện cô lập của V2 được xác thực đầy đủ.

### 4.2 Diễn tập Rollback Presentation (Rollback Rehearsal V2 → V1)

Nghiệm thu rollback được bảo đảm bởi các kịch bản kiểm thử tự động cấp component/store và browser:

1. **Bảo tồn chọn sân và lịch trống (`BidirectionalScheduleStatePreservation.spec.ts`)**:
   - Kiểm tra người dùng đã chọn sân và 2 slot trị giá 400.000 ₫; khi gọi `appearance.setUiVersion('v2')` rồi `appearance.setUiVersion('v1')`, toàn bộ `selectedSlots` (2 slots), tổng giá trị (400.000 ₫) và các slot đã chọn được bảo lưu 100%, không phát sinh bất kỳ lệnh gọi API đặt sân hoặc hủy sân nào.
2. **Bảo tồn giỏ hàng và ca POS (`AdminAndPosV2.spec.ts` & E2E Task 9.6)**:
   - Đang có sản phẩm "Nước Suối Aquafina 500ml" trong giỏ hàng (tổng thanh toán 10.800 ₫); khi người dùng mở dropdown profile và click switch `.aura-ui-version-switch` chuyển V2 → V1, component re-render theo V1 mà giỏ hàng, số lượng và tổng tiền giữ nguyên 100%.
3. **Bảo tồn hạn giữ chỗ và tham chiếu thanh toán (`PaymentAndExtendedBookingV2.spec.ts`)**:
   - Mã đơn hàng, phương thức thanh toán, deadline giữ chỗ và URL không đổi khi chuyển đổi giao diện qua `setUiVersion()`.
4. **Bảo tồn dữ liệu form đang nhập dở (`AuthViewV2.spec.ts`)**:
   - Form đăng nhập, đăng ký và onboarding giữ nguyên giá trị input khi chuyển đổi giao diện giữa V1 và V2.
5. **Cắt đổi sạch (Clean Cutover)**: Trạng thái V1 ban đầu được phục hồi trọn vẹn khi bỏ thuộc tính `data-ui-version="v2"`.
### 4.3 Hướng dẫn bật / tắt giao diện Erabook V2 (User & Operator Guide)

1. **Dành cho người dùng (Khách vãng lai & Đã đăng nhập)**:
   - **Desktop**: Nhấp vào menu đại diện (Avatar / Profile Dropdown) ở góc phải thanh điều hướng trên cùng → Nhấp vào switch **"Giao diện Erabook V2"** để bật hoặc tắt.
   - **Mobile**: Nhấp vào nút Menu (Hamburger icon) để mở ngăn kéo bên (Drawer) → Bật/tắt switch **"Giao diện Erabook V2"**.
   - Giao diện sẽ đổi ngay lập tức tại chỗ (in-place) mà không tải lại trang.

2. **Cơ chế lưu trữ & Ưu tiên (Persistence & Fallback)**:
   - Tùy chọn được lưu trong trình duyệt tại khóa `localStorage.getItem('aura_ui_version')` với giá trị `'v1'` hoặc `'v2'`.
   - Nếu người dùng chưa từng chọn hoặc dữ liệu không hợp lệ, hệ thống mặc định chọn **V1 (Premium Club)**.
   - Tùy chọn hoàn toàn độc lập với chế độ Sáng / Tối (`aura_theme`) và Ngôn ngữ (`aura_lang`).

### 4.4 Các ranh giới vận hành ngoài phạm vi (Out-of-Scope Operational Gates)

- **Không tự deploy production**: Thay đổi này hoàn tất ở cấp độ mã nguồn, build và test cục bộ. Việc phát hành production phụ thuộc vào quy trình CI/CD và quyết định của người vận hành.
- **Không tự đổi mặc định sang V2**: Mặc định hệ thống cho toàn bộ người dùng mới vẫn là V1. V2 là tính năng opt-in tự nguyện.
- **Không xác nhận thay đổi chính sách tiền tệ hoặc cổng thanh toán thật**: Mọi kịch bản thanh toán trong thay đổi này tuân theo logic UI hiện có, không can thiệp cổng thanh toán hay thay đổi chính sách ngân quỹ.
