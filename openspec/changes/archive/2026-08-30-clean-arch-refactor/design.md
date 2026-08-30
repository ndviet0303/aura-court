## Context

See proposal.md for motivation. Current state summary:

**Frontend**: 42 Vue SFC views averaging ~1,800 lines each. Top offenders: `AdminTournamentBracketView` (7,555 lines), `AdminManageView` (3,935), `AdminStaffView` (3,401). Each file mixes inline TypeScript interfaces, direct `api.get()`/`apiV2.post()` calls, template logic, and scoped styles in a single monolith. Only 5 Pinia stores and 17 shared components exist — most logic lives directly in views. All types defined in a single 16-line `types.ts`.

**Backend**: 24 controllers totaling 2,311 lines (largest: `AuthController` 363, `AdminController` 349). 42 services totaling 3,951 lines (largest: `BookingService` 312, `FinancialPostingService` 214). Services mix validation, domain logic, DB transactions, and notification dispatching. Only `Contracts/Payment/` has interfaces (12 files); the remaining 42 services lack contracts.

Router imports 16 views eagerly; remaining 26 use lazy `import()`.

## Goals / Non-Goals

**Goals:**
- Split monolithic frontend views into composable feature modules (< 500 lines per file)
- Extract all direct API calls into domain-specific API modules
- Create reusable composables for cross-cutting concerns
- Restructure backend into domain-driven layers (Actions, DTOs, Form Requests, Resources)
- Maintain 100% behavioral compatibility — zero API, route, or UI changes
- Enable incremental migration — old and new patterns coexist during transition

**Non-Goals:**
- Rewriting any business logic or algorithms
- Changing database schema or migrations
- Adding new features or API endpoints
- Changing the tech stack (no framework swap)
- Refactoring tests (beyond fixing broken imports)
- Optimizing performance (separate concern)

## Decisions

### D1: Frontend — Feature-based module structure over framework-imposed grouping

**Decision**: Organize by domain feature (`features/booking/`, `features/tournament/`) rather than by technical role (`components/`, `stores/`, `views/`).

**Why**: Co-locating related code reduces cognitive overhead. When working on "booking," everything is in one folder. The current structure forces jumping between `views/BookingView.vue`, `stores/booking.ts`, `types.ts`, and `lib/api.ts` for a single feature change.

**Alternative considered**: Keep flat `views/` + `stores/` structure but split large files → rejected because it doesn't solve the coupling problem between layers, and new features will recreate the same monolith pattern.

**Target structure**:
```
src/
├── core/                           # Shared infra (no domain logic)
│   ├── api/
│   │   ├── client.ts               # HTTP client (move from lib/api.ts)
│   │   └── index.ts                # Re-export for backward compat
│   ├── composables/
│   │   ├── useApiCall.ts
│   │   ├── usePagination.ts
│   │   └── useConfirmDialog.ts
│   ├── types/
│   │   ├── api.ts                  # ApiError, QueryParams
│   │   └── common.ts               # Shared enums, utility types
│   └── utils/
│       ├── errorMessage.ts
│       └── format.ts
│
├── features/
│   ├── auth/
│   │   ├── api/authApi.ts
│   │   ├── composables/useAuth.ts
│   │   ├── stores/authStore.ts
│   │   ├── types/auth.ts
│   │   └── views/
│   │       ├── LoginView.vue
│   │       ├── ForgotPasswordView.vue
│   │       └── ResetPasswordView.vue
│   │
│   ├── booking/
│   │   ├── api/bookingApi.ts
│   │   ├── composables/
│   │   │   ├── useBookingCreate.ts
│   │   │   ├── useBookingCancel.ts
│   │   │   ├── usePaymentFlow.ts
│   │   │   └── useSlotSelection.ts
│   │   ├── components/
│   │   │   ├── SlotPicker.vue
│   │   │   ├── BookingCard.vue
│   │   │   ├── PaymentSummary.vue
│   │   │   └── CouponInput.vue
│   │   ├── stores/bookingStore.ts
│   │   ├── types/booking.ts
│   │   └── views/
│   │       ├── BookingView.vue
│   │       ├── PaymentView.vue
│   │       └── RecurringBookingView.vue
│   │
│   ├── court/
│   │   ├── api/courtApi.ts
│   │   ├── components/
│   │   │   ├── CourtCard.vue
│   │   │   ├── CourtScheduleGrid.vue
│   │   │   └── PriceSlotTable.vue
│   │   ├── types/court.ts
│   │   └── views/
│   │       ├── CourtsView.vue
│   │       ├── CourtDetailView.vue
│   │       └── ScheduleView.vue
│   │
│   ├── tournament/
│   │   ├── api/tournamentApi.ts
│   │   ├── composables/
│   │   │   ├── useTournamentList.ts
│   │   │   ├── useBracketData.ts
│   │   │   └── useLiveScore.ts
│   │   ├── components/
│   │   │   ├── TournamentCard.vue
│   │   │   ├── TournamentGrid.vue
│   │   │   ├── TournamentFilters.vue
│   │   │   ├── BracketTree.vue
│   │   │   ├── FixtureCard.vue
│   │   │   └── StandingsTable.vue
│   │   ├── types/tournament.ts
│   │   └── views/
│   │       ├── TournamentsView.vue
│   │       ├── TournamentBracketView.vue
│   │       ├── LiveScoreView.vue
│   │       └── SocialTournamentView.vue
│   │
│   ├── social/
│   │   ├── api/socialApi.ts
│   │   ├── components/
│   │   ├── types/social.ts
│   │   └── views/
│   │       └── CreateMatchView.vue
│   │
│   ├── pos/
│   │   ├── api/posApi.ts
│   │   ├── composables/
│   │   │   ├── useCart.ts
│   │   │   ├── useShift.ts
│   │   │   └── useInventory.ts
│   │   ├── components/
│   │   │   ├── ProductGrid.vue
│   │   │   ├── CartPanel.vue
│   │   │   └── ShiftSummary.vue
│   │   ├── types/pos.ts
│   │   └── views/
│   │       └── PosView.vue
│   │
│   ├── finance/
│   │   ├── api/financeApi.ts
│   │   ├── components/
│   │   ├── types/finance.ts
│   │   └── views/
│   │       ├── AdminPayoutsView.vue
│   │       ├── PaymentCycleDetailView.vue
│   │       └── GroupSplitBillView.vue
│   │
│   ├── admin/
│   │   ├── api/adminApi.ts
│   │   ├── composables/
│   │   │   ├── useAdminBookings.ts
│   │   │   ├── useVenueManagement.ts
│   │   │   └── useStaffManagement.ts
│   │   ├── components/
│   │   │   ├── BookingTable.vue
│   │   │   ├── VenueForm.vue
│   │   │   ├── CourtForm.vue
│   │   │   ├── PriceEditor.vue
│   │   │   └── StaffRoleSelector.vue
│   │   ├── types/admin.ts
│   │   └── views/
│   │       ├── AdminView.vue
│   │       ├── AdminManageView.vue
│   │       ├── AdminScheduleView.vue
│   │       ├── AdminInventoryView.vue
│   │       ├── AdminVenuesView.vue
│   │       ├── AdminStaffView.vue
│   │       ├── AdminRecurringContractsView.vue
│   │       ├── AdminShiftReconciliationView.vue
│   │       └── AdminTournamentBracketView.vue
│   │
│   ├── marketplace/
│   │   ├── api/marketplaceApi.ts
│   │   ├── types/marketplace.ts
│   │   └── views/
│   │       └── ResaleMarketplaceView.vue
│   │
│   ├── profile/
│   │   ├── api/profileApi.ts
│   │   └── views/
│   │       ├── ProfileView.vue
│   │       └── OnboardingView.vue
│   │
│   └── wallet/
│       ├── api/walletApi.ts
│       ├── types/wallet.ts
│       └── views/
│           └── WalletView.vue
│
├── shared/
│   ├── components/                  # Move from current components/
│   │   ├── AuraButton.vue
│   │   ├── AuraLoading.vue
│   │   ├── DatePicker.vue
│   │   ├── SelectDropdown.vue
│   │   ├── SkeletonLoader.vue
│   │   ├── ErrorModal.vue
│   │   ├── DemoWarningModal.vue
│   │   ├── LanguageSwitcher.vue
│   │   ├── ThemeToggle.vue
│   │   └── TimePicker.vue
│   └── layouts/
│       ├── Navbar.vue
│       ├── SiteFooter.vue
│       └── AdminLayout.vue
│
├── router/index.ts                  # Update import paths
├── i18n/
└── main.ts
```

### D2: Frontend — Backward-compatible migration via re-exports

**Decision**: When moving files (e.g., `lib/api.ts` → `core/api/client.ts`), create re-export files at old paths to prevent import breakage during incremental migration.

**Why**: Enables feature-by-feature migration without a "big bang" refactor. Each feature can be migrated independently while others still work.

```ts
// lib/api.ts (re-export shim — remove once all imports updated)
export { api, apiV2, apiUrl, apiV2Url, errorMessage, idempotencyKey } from '@/core/api/client'
export type { ApiError, QueryParams, ApiRequestOptions } from '@/core/api/client'
```

### D3: Frontend — Extract strategy for monolithic views

**Decision**: For each monolithic view, apply this decomposition pattern:

1. **Types** → `features/{domain}/types/{domain}.ts` — extract all inline `interface` / `type` declarations
2. **API calls** → `features/{domain}/api/{domain}Api.ts` — extract all `api.get()` / `apiV2.post()` calls
3. **Reactive logic** → `features/{domain}/composables/use{Feature}.ts` — extract `ref()`, `computed()`, `watch()`, `onMounted()` logic into composables
4. **Template chunks** → `features/{domain}/components/{Name}.vue` — extract repeating or logical template sections into components
5. **View** → thin orchestrator importing composables and components

**Sizing heuristic**: A chunk of template > 100 lines with its own data dependencies becomes a component. A block of reactive logic used by > 1 component becomes a composable.

### D4: Backend — Actions pattern over Service refactoring

**Decision**: Introduce `Domain/{Feature}/Actions/` classes as the primary refactoring target, rather than splitting existing Services into smaller Services.

**Why**: Actions enforce single-responsibility by design (one public `execute()` method). Services have a tendency to accumulate methods over time. Actions are also easier to test in isolation.

**Alternative considered**: Splitting `BookingService` into `BookingCreateService`, `BookingCancelService` → rejected because "Service" naming doesn't communicate single-responsibility as clearly, and the pattern encourages accumulation over time.

**Example mapping**:
| Current Service Method | New Action Class |
|---|---|
| `BookingService::create()` | `Domain/Booking/Actions/CreateBooking` |
| `BookingService::cancel()` | `Domain/Booking/Actions/CancelBooking` |
| `BookingService::reschedule()` | `Domain/Booking/Actions/RescheduleBooking` |
| `BookingService::expirePending()` | `Domain/Booking/Actions/ExpirePendingBookings` |
| `BookingService::cancelSlots()` | `Domain/Booking/Actions/CancelBookingSlots` |
| `BookingService::applyCoupon()` | `Domain/Booking/Actions/ApplyBookingCoupon` |

### D5: Backend — Domain feature grouping

**Decision**: Group by domain feature under `Domain/`:

```
app/Domain/
├── Booking/
│   ├── Actions/
│   │   ├── CreateBooking.php
│   │   ├── CancelBooking.php
│   │   ├── RescheduleBooking.php
│   │   ├── ExpirePendingBookings.php
│   │   ├── CancelBookingSlots.php
│   │   └── ApplyBookingCoupon.php
│   ├── DTOs/
│   │   ├── CreateBookingData.php
│   │   ├── CancelBookingData.php
│   │   └── BookingResult.php
│   └── Events/
│       ├── BookingCreated.php
│       ├── BookingCancelled.php
│       └── BookingExpired.php
│
├── Court/
│   ├── Actions/
│   │   ├── CreateCourt.php
│   │   ├── UpdateCourt.php
│   │   └── ManageCourtBlocks.php
│   └── DTOs/
│       └── CourtData.php
│
├── Payment/
│   ├── Actions/
│   │   ├── ProcessQrPayment.php
│   │   ├── ProcessStripePayment.php
│   │   ├── ProcessRefund.php
│   │   └── CreatePayout.php
│   └── DTOs/
│       ├── PaymentIntentData.php
│       └── RefundData.php
│
├── Reservation/
│   ├── Actions/
│   │   ├── CreateReservation.php
│   │   ├── ConfirmReservation.php
│   │   └── CancelReservation.php
│   └── DTOs/
│
├── Tournament/
│   ├── Actions/
│   │   ├── CreateTournament.php
│   │   ├── ScoreFixture.php
│   │   └── CheckInParticipant.php
│   └── DTOs/
│
├── Social/
│   ├── Actions/
│   │   ├── CreateMatchPost.php
│   │   ├── InviteToMatch.php
│   │   └── JoinMatch.php
│   └── DTOs/
│
├── POS/
│   ├── Actions/
│   │   ├── OpenShift.php
│   │   ├── CloseShift.php
│   │   ├── CompleteOrder.php
│   │   └── AdjustStock.php
│   └── DTOs/
│
├── Finance/
│   ├── Actions/
│   │   ├── SnapshotSettlement.php
│   │   ├── CreatePayout.php
│   │   └── ApprovePayout.php
│   └── DTOs/
│
├── Venue/
│   ├── Actions/
│   │   ├── CreateVenue.php
│   │   ├── UpdateVenue.php
│   │   └── ManageSportZones.php
│   └── DTOs/
│
├── Promotion/
│   ├── Actions/
│   │   ├── ApplyDiscount.php
│   │   ├── CreateCoupon.php
│   │   └── AssignMembership.php
│   └── DTOs/
│
└── Auth/
    ├── Actions/
    │   ├── RegisterUser.php
    │   ├── LoginUser.php
    │   └── ResetPassword.php
    └── DTOs/
```

### D6: Backend — Controller splitting strategy

**Decision**: Split `AdminController` (349 lines, ~15 methods) into domain-focused controllers:

| Current | New Controller |
|---|---|
| `AdminController::venues()`, `storeVenue()`, `updateVenue()`, `destroyVenue()`, `showVenue()` | `Admin/VenueController` |
| `AdminController::bookings()`, `noShow()` | `Admin/BookingController` |
| `AdminController::payments()`, `reviewPayment()` | `Admin/PaymentController` |
| `AdminController::prices()`, `bulkPrices()`, `updatePrice()`, `destroyPrice()` | `Admin/PriceController` |
| `AdminController::blocks()`, `blocksIndex()`, `destroyBlock()` | `Admin/CourtBlockController` |
| `AdminController::refunds()`, `processRefund()` | `Admin/RefundController` |
| `AdminController::report()`, `reportCsv()` | `Admin/ReportController` |
| `AdminController::upload()` | `Admin/UploadController` |

Routes remain identical — only the controller class references change in `routes/api.php`.

### D7: Backend — Form Requests replace inline validation

**Decision**: Extract all `$request->validate([...])` calls into dedicated `FormRequest` classes under `Http/Requests/{Feature}/`.

**Why**: Centralizes validation rules, enables authorization in `authorize()`, makes rules testable, and keeps controllers thin.

**Example**:
```php
// Before (in BookingController::store)
$data = $request->validate([
    'court_id' => 'required|integer|exists:courts,id',
    'slots' => 'required|array|min:1|max:12',
    'slots.*' => 'date',
    'coupon_code' => 'nullable|string|max:40',
]);

// After (dedicated FormRequest)
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'court_id' => 'required|integer|exists:courts,id',
            'slots' => 'required|array|min:1|max:12',
            'slots.*' => 'date',
            'coupon_code' => 'nullable|string|max:40',
        ];
    }
}
```

### D8: Phased execution order

**Decision**: Frontend first, then backend. Within each phase, work by domain feature starting with the smallest to build confidence.

**Why**: Frontend refactoring is lower-risk (no database concerns, no API contract risk) and delivers the biggest visual improvement to DX (splitting 7,500-line files). Backend changes only affect internal structure — the API layer is stable.

**Phase 1 (FE) order**:
1. Core infrastructure (`core/api/`, `core/composables/`, `core/types/`)
2. Shared components reorganization
3. Features by ascending complexity: `auth` → `profile` → `court` → `booking` → `marketplace` → `wallet` → `social` → `tournament` → `pos` → `finance` → `admin`

**Phase 2 (BE) order**:
1. Domain structure scaffolding + first Action class (`Booking/Actions/CreateBooking`)
2. Form Requests for all controllers
3. DTOs for all Actions
4. Actions extraction by domain
5. Controller splitting
6. API Resources
7. Events + Listeners for side effects

## Risks / Trade-offs

| Risk | Mitigation |
|---|---|
| **Import path breakage** across 100+ files | Re-export shims at old paths; run `tsc --noEmit` after each move to verify |
| **Router lazy-import paths change** | Update `router/index.ts` per-feature; verify all routes load in browser |
| **Backend namespace changes break autoloading** | Run `composer dump-autoload` after each Domain/ addition; run tests after each controller split |
| **Large PR size** | Split into ~12 PRs by feature domain; each PR is self-contained |
| **Partial migration confusion** | Both patterns coexist with clear naming; document migration status in PR descriptions |
| **Pinia store references break** | Keep store IDs (`defineStore('booking', ...)`) unchanged; only move files |
| **Test path updates missed** | Run full test suite (`vitest run`, `phpunit`) before each PR merge |
