## 1. Frontend — Core Infrastructure

- [x] 1.1 Create `src/core/api/client.ts` — move HTTP client from `lib/api.ts`; create re-export shim at `lib/api.ts`
- [x] 1.2 Create `src/core/types/api.ts` — extract `ApiError`, `QueryParams`, `ApiRequestOptions` from `lib/api.ts`
- [x] 1.3 Create `src/core/types/common.ts` — extract shared types from `types.ts` (`User`, `AppNotification`, `Membership`)
- [x] 1.4 Create `src/core/composables/useApiCall.ts` — generic loading/error/execute pattern
- [x] 1.5 Create `src/core/composables/usePagination.ts` — reusable pagination state + API integration
- [x] 1.6 Create `src/core/composables/useConfirmDialog.ts` — reusable confirm modal state
- [x] 1.7 Create `src/core/utils/errorMessage.ts` — move `errorMessage()` from `lib/api.ts`
- [x] 1.8 Verify: `tsc --noEmit` passes, `vitest run` passes

## 2. Frontend — Shared Components Reorganization

- [x] 2.1 Create `src/shared/components/` — move all reusable components from `components/` (AuraButton, AuraLoading, DatePicker, SelectDropdown, SkeletonLoader, ErrorModal, DemoWarningModal, LanguageSwitcher, ThemeToggle, TimePicker, DateTimePicker)
- [x] 2.2 Create `src/shared/layouts/` — move Navbar, SiteFooter, AdminLayout from `components/`
- [x] 2.3 Update all imports across the codebase to reference new paths
- [x] 2.4 Create re-export index files at `shared/components/index.ts` and `shared/layouts/index.ts`
- [x] 2.5 Verify: `tsc --noEmit` passes, app loads in browser

## 3. Frontend — Feature: Auth

- [x] 3.1 Create `src/features/auth/types/auth.ts` — extract `User` type
- [x] 3.2 Create `src/features/auth/api/authApi.ts` — extract auth API calls from `stores/auth.ts` and views
- [x] 3.3 Create `src/features/auth/stores/authStore.ts` — move from `stores/auth.ts`, refactor to use `authApi`
- [x] 3.4 Move views: `LoginView.vue`, `ForgotPasswordView.vue`, `ResetPasswordView.vue` → `features/auth/views/`
- [x] 3.5 Move `OnboardingView.vue` → `features/auth/views/` (or `features/profile/views/`)
- [x] 3.6 Update router imports for auth views
- [x] 3.7 Verify: login, register, forgot/reset password flows work

## 4. Frontend — Feature: Court

- [x] 4.1 Create `src/features/court/types/court.ts` — extract `Court`, `Venue`, `SportZone`, `PriceSlot`, `AvailabilitySlot`, `CourtBlock`
- [x] 4.2 Create `src/features/court/api/courtApi.ts` — extract court listing, availability, venue API calls
- [x] 4.3 Extract components from `CourtsView.vue` — `CourtCard.vue`, `CourtFilters.vue`
- [x] 4.4 Extract components from `CourtDetailView.vue` — `CourtScheduleGrid.vue`, `CourtReviews.vue`, `PriceSlotTable.vue`
- [x] 4.5 Create composables: `useCourtList.ts`, `useAvailability.ts`
- [x] 4.6 Move views to `features/court/views/`: `CourtsView.vue`, `CourtDetailView.vue`, `ScheduleView.vue`, `VenueDirectionsView.vue`
- [x] 4.7 Update router imports for court views
- [x] 4.8 Verify: courts listing, court detail, schedule pages work

## 5. Frontend — Feature: Booking

- [x] 5.1 Create `src/features/booking/types/booking.ts` — extract `Booking`, `PaymentInfo`, `Refund`, `Review`, `Coupon`
- [x] 5.2 Create `src/features/booking/api/bookingApi.ts` — extract all booking API calls from `stores/booking.ts` and views
- [x] 5.3 Create composables: `useBookingCreate.ts`, `useBookingCancel.ts`, `usePaymentFlow.ts`, `useSlotSelection.ts`
- [x] 5.4 Extract components from `BookingView.vue` (~1,200 lines) — `SlotPicker.vue`, `BookingCard.vue`, `BookingSummary.vue`
- [x] 5.5 Extract components from `PaymentView.vue` (~2,400 lines) — `PaymentSummary.vue`, `QrPaymentPanel.vue`, `StripePaymentPanel.vue`, `CouponInput.vue`
- [x] 5.6 Extract components from `RecurringBookingView.vue` (~2,500 lines) — `RecurringSeriesCard.vue`, `InvoiceList.vue`
- [x] 5.7 Refactor `stores/booking.ts` → `features/booking/stores/bookingStore.ts`, use `bookingApi`
- [x] 5.8 Move views to `features/booking/views/`
- [x] 5.9 Update router imports for booking views
- [x] 5.10 Verify: booking flow, payment, recurring booking all work

## 6. Frontend — Feature: Marketplace & Wallet

- [x] 6.1 Create `src/features/marketplace/api/marketplaceApi.ts` and `types/marketplace.ts`
- [x] 6.2 Extract components from `ResaleMarketplaceView.vue` (~1,900 lines) — `ListingCard.vue`, `ListingFilters.vue`
- [x] 6.3 Move `ResaleMarketplaceView.vue` → `features/marketplace/views/`
- [x] 6.4 Create `src/features/wallet/api/walletApi.ts` and `types/wallet.ts`
- [x] 6.5 Extract components from `WalletView.vue` (~1,700 lines) — `WalletBalance.vue`, `TransactionList.vue`
- [x] 6.6 Move `WalletView.vue` → `features/wallet/views/`
- [x] 6.7 Update router imports
- [x] 6.8 Verify: marketplace listing, wallet top-up work

## 7. Frontend — Feature: Social & Tournament

- [x] 7.1 Create `src/features/tournament/types/tournament.ts` — extract all tournament/fixture/participant types from `TournamentsView.vue` inline
- [x] 7.2 Create `src/features/tournament/api/tournamentApi.ts`
- [x] 7.3 Extract from `TournamentsView.vue` (3,050 lines) — `TournamentCard.vue`, `TournamentGrid.vue`, `TournamentFilters.vue`, `TournamentDetail.vue`
- [x] 7.4 Create composables: `useTournamentList.ts`, `useTournamentDetail.ts`
- [x] 7.5 Extract from `TournamentBracketView.vue` (~1,800 lines) — `BracketTree.vue`, `FixtureCard.vue`
- [x] 7.6 Extract from `LiveScoreView.vue` (~1,900 lines) — `LiveMatchCard.vue`, `ScoreBoard.vue`
- [x] 7.7 Extract from `SocialTournamentView.vue` (~2,800 lines) — `MatchPostCard.vue`, `MatchFilters.vue`
- [x] 7.8 Create `src/features/social/api/socialApi.ts` and `types/social.ts`
- [x] 7.9 Move `CreateMatchView.vue` → `features/social/views/`
- [x] 7.10 Move tournament views → `features/tournament/views/`
- [x] 7.11 Update router imports
- [x] 7.12 Verify: tournament listing, bracket, live score, matchmaking all work

## 8. Frontend — Feature: POS

- [x] 8.1 Create `src/features/pos/types/pos.ts` — extract POS types from `PosView.vue` inline
- [x] 8.2 Create `src/features/pos/api/posApi.ts`
- [x] 8.3 Extract from `PosView.vue` (3,191 lines) — `ProductGrid.vue`, `CartPanel.vue`, `OrderSummary.vue`, `ShiftControls.vue`
- [x] 8.4 Create composables: `useCart.ts`, `useShift.ts`, `useInventory.ts`
- [x] 8.5 Move `PosView.vue` → `features/pos/views/`
- [x] 8.6 Verify: POS product listing, cart, order, shift open/close all work

## 9. Frontend — Feature: Finance & Payments Admin

- [x] 9.1 Create `src/features/finance/types/finance.ts` and `api/financeApi.ts`
- [x] 9.2 Extract from `AdminPayoutsView.vue` (~2,300 lines) — `PayoutTable.vue`, `BeneficiaryForm.vue`, `SettlementCard.vue`
- [x] 9.3 Extract from `PaymentCycleDetailView.vue` (~2,000 lines) — `CycleTimeline.vue`, `InvoiceDetail.vue`
- [x] 9.4 Extract from `GroupSplitBillView.vue` (~2,700 lines) — `SplitSummary.vue`, `ShareCard.vue`, `PaySharePanel.vue`
- [x] 9.5 Move views → `features/finance/views/`
- [x] 9.6 Update router imports
- [x] 9.7 Verify: payouts, billing cycles, split payments all work

## 10. Frontend — Feature: Admin

- [x] 10.1 Create `src/features/admin/types/admin.ts` and `api/adminApi.ts`
- [x] 10.2 Extract from `AdminManageView.vue` (3,935 lines) — `BookingTable.vue`, `PaymentTable.vue`, `RefundTable.vue`, `ReportCharts.vue`
- [x] 10.3 Extract from `AdminScheduleView.vue` (~2,300 lines) — `ScheduleCalendar.vue`, `SlotBlockEditor.vue`
- [x] 10.4 Extract from `AdminStaffView.vue` (3,401 lines) — `StaffTable.vue`, `StaffRoleSelector.vue`, `InviteMemberForm.vue`
- [x] 10.5 Extract from `AdminRecurringContractsView.vue` (3,241 lines) — `ContractTable.vue`, `ContractDetail.vue`
- [x] 10.6 Extract from `AdminShiftReconciliationView.vue` (3,358 lines) — `ShiftTable.vue`, `ReconciliationDetail.vue`, `DiscrepancyAlert.vue`
- [x] 10.7 Extract from `AdminInventoryView.vue` (~2,600 lines) — `InventoryTable.vue`, `StockAdjustForm.vue`
- [x] 10.8 Extract from `AdminVenuesView.vue` (~2,300 lines) — `VenueForm.vue`, `CourtForm.vue`, `PriceEditor.vue`
- [x] 10.9 Extract from `AdminTournamentBracketView.vue` (7,555 lines) — `BracketEditor.vue`, `FixtureScoreForm.vue`, `ParticipantManager.vue`, `TournamentSettings.vue`, `RoundNavigator.vue`
- [x] 10.10 Create composables: `useAdminBookings.ts`, `useVenueManagement.ts`, `useStaffManagement.ts`
- [x] 10.11 Move admin views → `features/admin/views/`
- [x] 10.12 Update router imports
- [x] 10.13 Verify: all admin pages work, data loads, CRUD operations succeed

## 11. Frontend — Feature: Profile & Static Pages

- [x] 11.1 Create `src/features/profile/api/profileApi.ts`
- [x] 11.2 Extract from `ProfileView.vue` (~1,700 lines) — `ProfileForm.vue`, `AvatarUploader.vue`, `SecuritySettings.vue`
- [x] 11.3 Move `ProfileView.vue` → `features/profile/views/`
- [x] 11.4 Move remaining standalone views (HomeView, AboutView, NotFoundView, NotificationsView, TermsView, PrivacyView) → `features/static/views/` or keep at `views/`
- [x] 11.5 Update router imports
- [x] 11.6 Verify: profile edit, all static pages render

## 12. Frontend — Cleanup & Final Verification

- [x] 12.1 Remove old `components/` directory (all moved to `shared/components/` or features)
- [x] 12.2 Remove old `stores/` directory (all moved to features)
- [x] 12.3 Remove old `lib/` re-export shims once all imports updated
- [x] 12.4 Remove old `types.ts` once all types moved to feature modules
- [x] 12.5 Remove empty `views/` directory
- [x] 12.6 Run `tsc --noEmit` — zero errors
- [x] 12.7 Run `vitest run` — all tests pass
- [x] 12.8 Run `npm run build` — production build succeeds
- [x] 12.9 Manual smoke test: navigate every route in the browser

## 13. Backend — Domain Structure & First Action

- [x] 13.1 Create directory structure: `app/Domain/Booking/Actions/`, `app/Domain/Booking/DTOs/`, `app/Domain/Booking/Events/`
- [x] 13.2 Create `CreateBookingData` DTO — readonly class from `BookingService::create()` params
- [x] 13.3 Create `CreateBooking` Action — extract from `BookingService::create()`
- [x] 13.4 Update `BookingController::store()` to use `CreateBooking` action + DTO
- [x] 13.5 Verify: booking creation still works end-to-end

## 14. Backend — Form Requests

- [x] 14.1 Create `Http/Requests/Booking/StoreBookingRequest.php` — extract from `BookingController::store()`
- [x] 14.2 Create `Http/Requests/Booking/RecurringBookingRequest.php` — extract from `BookingController::recurring()`
- [x] 14.3 Create `Http/Requests/Booking/CancelSlotsRequest.php` — extract from `BookingController::cancelSlots()`
- [x] 14.4 Create `Http/Requests/Booking/RescheduleBookingRequest.php` — extract from `BookingController::reschedule()`
- [x] 14.5 Create `Http/Requests/Booking/StripPayRequest.php` — extract from `BookingController::stripePay()`
- [x] 14.6 Create `Http/Requests/Admin/StoreVenueRequest.php` — extract from `AdminController`
- [x] 14.7 Create `Http/Requests/Admin/StorePriceRequest.php` — extract from `AdminController`
- [x] 14.8 Create `Http/Requests/Auth/RegisterRequest.php`, `LoginRequest.php`, `ForgotPasswordRequest.php`
- [x] 14.9 Create Form Requests for remaining controllers (Court, POS, Finance, Tournament, Social, Organization)
- [x] 14.10 Update all controllers to type-hint Form Requests instead of `Request`
- [x] 14.11 Verify: `php artisan test` passes

## 15. Backend — Booking Domain Actions

- [x] 15.1 Create `CancelBookingData` DTO and `CancelBooking` Action — extract from `BookingService::cancel()`
- [x] 15.2 Create `CancelBookingSlots` Action — extract from `BookingService::cancelSlots()`
- [x] 15.3 Create `RescheduleBooking` Action — extract from `BookingService::reschedule()`
- [x] 15.4 Create `ExpirePendingBookings` Action — extract from `BookingService::expirePending()`
- [x] 15.5 Create `ApplyBookingCoupon` Action — extract from `BookingService::applyCoupon()`
- [x] 15.6 Create `CreateRecurringBooking` Action — extract from `BookingService::createRecurring()`
- [x] 15.7 Update `BookingController` to inject Actions instead of `BookingService`
- [x] 15.8 Create domain events: `BookingCreated`, `BookingCancelled`, `BookingExpired`
- [x] 15.9 Move notification dispatching to event listeners
- [x] 15.10 Verify: all booking flows work

## 16. Backend — Payment & Reservation Domain Actions

- [x] 16.1 Create `Domain/Payment/Actions/` — `ProcessQrPayment`, `ProcessStripePayment`, `ProcessRefund`, `CreatePayout`
- [x] 16.2 Create `Domain/Payment/DTOs/` — `PaymentIntentData`, `RefundData`
- [x] 16.3 Create `Domain/Reservation/Actions/` — `CreateReservation`, `ConfirmReservation`, `CancelReservation`
- [x] 16.4 Update `V2ReservationController` and `FinanceController` to use Actions
- [x] 16.5 Verify: payment and reservation flows work

## 17. Backend — Remaining Domain Actions

- [x] 17.1 Create `Domain/Tournament/Actions/` — `CreateTournament`, `ScoreFixture`, `CheckInParticipant`
- [x] 17.2 Create `Domain/Social/Actions/` — `CreateMatchPost`, `InviteToMatch`, `JoinMatch`
- [x] 17.3 Create `Domain/POS/Actions/` — `OpenShift`, `CloseShift`, `CompleteOrder`, `AdjustStock`
- [x] 17.4 Create `Domain/Finance/Actions/` — `SnapshotSettlement`, `CreatePayout`, `ApprovePayout`
- [x] 17.5 Create `Domain/Venue/Actions/` — `CreateVenue`, `UpdateVenue`, `ManageSportZones`
- [x] 17.6 Create `Domain/Promotion/Actions/` — `ApplyDiscount`, `CreateCoupon`, `AssignMembership`
- [x] 17.7 Create `Domain/Auth/Actions/` — `RegisterUser`, `LoginUser`, `ResetPassword`
- [x] 17.8 Update all remaining controllers to use Actions
- [x] 17.9 Verify: `php artisan test` passes

## 18. Backend — Controller Splitting

- [x] 18.1 Split `AdminController` → `Admin/VenueController`, `Admin/BookingController`, `Admin/PaymentController`, `Admin/PriceController`, `Admin/CourtBlockController`, `Admin/RefundController`, `Admin/ReportController`, `Admin/UploadController`
- [x] 18.2 Split `AuthController` → smaller controllers if >200 lines remain after Form Request extraction
- [x] 18.3 Update `routes/api.php` to reference new controller classes
- [x] 18.4 Verify: all routes resolve correctly, all admin features work

## 19. Backend — API Resources

- [x] 19.1 Create `Http/Resources/BookingResource.php`, `CourtResource.php`, `VenueResource.php`
- [x] 19.2 Create `Http/Resources/PaymentResource.php`, `RefundResource.php`
- [x] 19.3 Create `Http/Resources/TournamentResource.php`, `MatchPostResource.php`
- [x] 19.4 Create `Http/Resources/PosOrderResource.php`, `ProductResource.php`
- [x] 19.5 Update controllers to return Resources instead of raw models/arrays
- [x] 19.6 Verify: API responses are identical to before (diff JSON output)

## 20. Backend — Interfaces & Cleanup

- [x] 20.1 Create interfaces in `Contracts/` for remaining services (BookingServiceInterface, ReservationServiceInterface, etc.)
- [x] 20.2 Bind implementations in `AppServiceProvider`
- [x] 20.3 Remove deprecated Service classes that are fully replaced by Actions
- [x] 20.4 Run `composer dump-autoload`
- [x] 20.5 Run `php artisan test` — all tests pass
- [x] 20.6 Run `php artisan route:list` — all routes resolve

## 21. Final Verification

- [x] 21.1 Full frontend build: `npm run build` succeeds
- [x] 21.2 Full frontend test suite: `vitest run` passes
- [x] 21.3 Full backend test suite: `php artisan test` passes
- [x] 21.4 Manual end-to-end smoke test of critical flows: login → book court → pay → view booking → cancel
- [x] 21.5 Verify no leftover re-export shims or dead files
