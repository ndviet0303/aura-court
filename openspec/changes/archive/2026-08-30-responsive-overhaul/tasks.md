## 1. CSS Utilities & Infrastructure

- [x] 1.1 Standardize media query breakpoints in `responsive.css` (Mobile: <= 640px, Tablet: <= 1024px).
- [x] 1.2 Add `.table-responsive` utility class to `components.css` or `responsive.css` (with `overflow-x: auto; -webkit-overflow-scrolling: touch;`).
- [x] 1.3 Add `.grid-stack-mobile` utility class to `responsive.css` (forces `grid-template-columns: 1fr` on mobile).
- [x] 1.4 Add basic hide/show utilities (e.g., `.hide-on-mobile`, `.show-on-mobile`).

## 2. Navigation & App Shell

- [x] 2.1 Update top navigation / header to ensure logo, menu, and user profile stack or use a hamburger menu correctly on mobile.
- [x] 2.2 Update Admin sidebar to collapse into a hamburger menu or bottom navigation bar on mobile devices.

## 3. Public Views (Grids & Hero Sections)

- [x] 3.1 Landing Page (`HomeView.vue`) - fix hero section typography on mobile and convert feature grids to 1 column using `.grid-stack-mobile` or local `@media`.
- [x] 3.2 Courts List (`CourtsView.vue`) - stack the court search/filter sidebar and the court results grid on mobile.
- [x] 3.3 Court Details (`CourtDetailView.vue`) - fix horizontal overflow for images and ensure the booking CTA is accessible.

## 4. Admin Views (Data Tables)

- [x] 4.1 Wrap tables in `.table-responsive` for `AdminManageView.vue` and `AdminInventoryView.vue`.
- [x] 4.2 Wrap tables in `.table-responsive` for `AdminPayoutsView.vue` and `AdminStaffView.vue`.
- [x] 4.3 Wrap tables in `.table-responsive` for `AdminVenuesView.vue` and `AdminShiftReconciliationView.vue`.

## 5. Forms, Settings & Complex Views

- [x] 5.1 Refactor form grids in `ProfileView.vue` and `OrganizationOwnerView.vue` to use `.grid-stack-mobile`.
- [x] 5.2 Refactor `AdvancedBookingView.vue` and `V2ReservationView.vue` (ensure date pickers, time slots, and summary cards stack cleanly).
- [x] 5.3 Review and adjust `ScheduleView.vue` and `AdminScheduleView.vue` (ensure calendar/timeline grids are usable on mobile, potentially via horizontal scroll).
- [x] 5.4 Review and adjust `TournamentBracketView.vue` and `AdminTournamentBracketView.vue` for mobile usability (likely requiring horizontal/vertical scrolling or pinch-to-zoom containers).
