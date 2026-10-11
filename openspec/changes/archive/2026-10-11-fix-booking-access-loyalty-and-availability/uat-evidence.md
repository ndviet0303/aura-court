# UAT Verification Evidence: Booking Access, Loyalty, and Availability

**Change**: `fix-booking-access-loyalty-and-availability`  
**Execution Timestamp**: 2026-09-22T15:56:00+07:00  
**Verification Method**: Playwright E2E Browser Testing against Local Dev Stack (Vite + Laravel Sanctum + PostgreSQL)

---

## 1. Real Execution Environment

| Property | Measured Value |
| :--- | :--- |
| **Git Commit** | `5ce081d87c4ba75e4b6ecc67a68493dfc1259340` |
| **Node.js Version** | `v24.12.0` |
| **npm Version** | `11.13.0` |
| **PHP Version** | `PHP 8.5.10 (cli) (built: Aug 25 2026 21:09:32) (NTS)` |
| **Playwright Version** | `Version 1.62.1` |
| **Browser Engine & Device** | `Chromium / Desktop Chrome` (Headless) |
| **Operating System & Architecture** | `Darwin 27.0.0 arm64` (macOS, Apple M1) |
| **System Timezone** | `Asia/Ho_Chi_Minh (+07:00)` |
| **Environment URL** | `http://127.0.0.1:4173` (Frontend) / `http://127.0.0.1:8000` (Backend API) |
| **Target Organization** | Org ID `6` (`Aura Sports Marketplace Club`) |
| **Target Test Accounts** | `owner@demo.auracourt.vn` (`organization_owner`), `cashier@demo.auracourt.vn` (`cashier`), `customer@demo.auracourt.vn` (`customer`) |

---

## 2. Journey Execution Summary and Assertion Results

### Journey 1: Guest Navigation & Auth Destination Redirects
- **Status**: **PASSED**
- **File**: `frontend/e2e/booking-access-loyalty-uat.spec.ts:10`
- **Visual Evidence**: `openspec/changes/fix-booking-access-loyalty-and-availability/evidence/journey1_guest_auth.png`
- **Execution Log & Verifications**:
  1. Unauthenticated direct access to `/login` rendered the login form (`#login-email`, `#login-password`, `auth-submit-btn`) without unhandled crashes.
  2. Unauthenticated direct access to `/register` rendered registration mode with confirm-password fields.
  3. Unauthenticated access to protected route `/wallet` was intercepted by `router.beforeEach`, redirecting to `/login?redirect=%2Fwallet`.
  4. Authenticated login as `customer@demo.auracourt.vn` honored the query parameter and successfully redirected to the intended destination (`/wallet`).
  5. Subsequent navigation to `/login` and `/register` while authenticated triggered the `guestOnly` guard, automatically redirecting the user away to their authorized destination.

### Journey 2: Scoped Admin / POS Access and Platform Denial
- **Status**: **PASSED**
- **File**: `frontend/e2e/booking-access-loyalty-uat.spec.ts:44`
- **Visual Evidence**:
  - `openspec/changes/fix-booking-access-loyalty-and-availability/evidence/journey2_owner_pos.png` (Owner POS view)
  - `openspec/changes/fix-booking-access-loyalty-and-availability/evidence/journey2_cashier_pos.png` (Cashier POS view)
- **Execution Log & Verifications**:
  1. **Owner (`owner@demo.auracourt.vn`)**:
     - Direct access to `/admin` redirected to `/admin/organization`.
     - Organization management view (`.organization-owner-view`) rendered successfully with Org 6 scope.
     - Access to `/admin/pos` rendered the POS interface (`.pos-layout-wrapper`).
     - Access to platform-admin-only routes `/admin/manage` and `/admin/venues` was denied, redirecting safely to the root home page (`/`).
  2. **Cashier (`cashier@demo.auracourt.vn`)**:
     - Direct access to `/admin/pos` rendered the POS workspace (`.pos-layout-wrapper`).
     - Access to `/admin` routed directly to `/admin/pos`.
     - Access to `/admin/organization` (owner/manager only) was denied and redirected away to `/`.
     - Access to `/admin/manage` and `/admin/venues` was denied and redirected away to `/`.

### Journey 3: Per-Court Availability Calendar & Slot Clarity
- **Status**: **PASSED**
- **File**: `frontend/e2e/booking-access-loyalty-uat.spec.ts:116`
- **Visual Evidence**: `openspec/changes/fix-booking-access-loyalty-and-availability/evidence/journey3_court_availability.png`
- **Execution Log & Verifications**:
  1. Home page `#availability` rendered the dynamic heading replacing fixed 'today' labels: `Lịch trống — Sân 1 - Thảm BWF Tiêu Chuẩn — 2026-09-22`.
  2. Hourly slot range formatting showed clean boundaries: `19:00 – 20:00` and `20:00 – 21:00`.
  3. On Court 1 (where 19:00-21:00 was booked), both `19:00 – 20:00` and `20:00 – 21:00` slots were rendered in disabled/unavailable state (`slot.unavailable` / `disabled`).
  4. Switching courts to Court 2 via the nearby court chip updated the active chip (`.court-chip.active`), updated the heading to `Sân 2 - Tiêu Chuẩn`, and loaded fresh availability.
  5. On Court 2, `19:00 – 20:00` was enabled and selectable; clicking it selected the slot and opened the floating booking summary dock with 1 slot selected.

### Journey 4: Loyalty Point Selection Preview & Discount Calculation
- **Status**: **PASSED**
- **File**: `frontend/e2e/booking-access-loyalty-uat.spec.ts:166`
- **Visual Evidence**: `openspec/changes/fix-booking-access-loyalty-and-availability/evidence/journey4_loyalty_preview.png`
- **Execution Log & Verifications**:
  1. Logged in as `customer@demo.auracourt.vn` with 500 loyalty points in Org 6.
  2. Selected slot on Court 2 (Venue 1, Org 6), mounting `LoyaltyPointSelector` in the floating booking bar.
  3. Verified authoritative balance badge displayed `500` available points.
  4. Entered `100` points into `.loyalty-input`.
  5. Backend `/api/v2/loyalty/preview` calculation responded and rendered the breakdown:
     - Discount value displayed: `-5.000 ₫` (matching 100 points = 5,000 VND policy ratio).
     - Remaining payable amount displayed updated net figure (`120.000 ₫`).
  6. Clicking the clear button (`.btn-quick.clear`) reset point selection and removed the discount breakdown without unexpected errors.

---

## 3. Captured Visual Evidence (Screenshots)

| Screenshot File | Persistent Tracked Path | Size | Verification Notes |
| :--- | :--- | :--- | :--- |
| `journey1_guest_auth.png` | `openspec/changes/fix-booking-access-loyalty-and-availability/evidence/journey1_guest_auth.png` | 367,696 bytes (359 KB) | Captured after guest auth flow and redirection verification; confirms authenticated user is automatically redirected away from `/register` and `/login` guest routes. |
| `journey2_owner_pos.png` | `openspec/changes/fix-booking-access-loyalty-and-availability/evidence/journey2_owner_pos.png` | 126,508 bytes (124 KB) | Confirms Org Owner (`owner@demo.auracourt.vn`) successfully accesses and renders `/admin/pos` interface (`.pos-layout-wrapper`) under Org 6 scope. |
| `journey2_cashier_pos.png` | `openspec/changes/fix-booking-access-loyalty-and-availability/evidence/journey2_cashier_pos.png` | 121,987 bytes (120 KB) | Confirms Cashier (`cashier@demo.auracourt.vn`) successfully accesses and renders `/admin/pos` interface (`.pos-layout-wrapper`) under Org 6 scope. |
| `journey3_court_availability.png` | `openspec/changes/fix-booking-access-loyalty-and-availability/evidence/journey3_court_availability.png` | 461,444 bytes (451 KB) | Confirms per-court availability calendar: Court 2 (`Sân 2 - Tiêu Chuẩn`) active chip, slot clarity showing `19:00 – 20:00` selected, and floating booking summary bar with 1 slot. |
| `journey4_loyalty_preview.png` | `openspec/changes/fix-booking-access-loyalty-and-availability/evidence/journey4_loyalty_preview.png` | 355,129 bytes (347 KB) | Confirms authoritative loyalty preview calculation in floating dock: balance badge `500`, 100 points entered, discount value `-5.000 ₫`, and remaining payable amount `120.000 ₫`. |

---

## 4. Stability and Client Rendering Confirmation
- All 4 journeys executed cleanly without unhandled JavaScript exceptions or console errors.
- Both backend API and frontend Vite servers handled authentication cookies, route guards, and live calculations under real headless Chromium browser interaction.
- All 5 visual evidence screenshots captured and verified on disk.
