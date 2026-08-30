## 1. Setup Infrastructure and Interfaces

- [x] 1.1 Create `Contracts/Repositories` directory and define Repository Interfaces for `Venue`, `Court`, `CourtPriceSlot`, `CourtBlock`, `Booking`, `Payment`, `Refund`.
- [x] 1.2 Create `Infrastructure/Repositories/Eloquent` directory and implement the Repository Interfaces using Eloquent Models.
- [x] 1.3 Create `Infrastructure/Storage/FileStorageService` and its Interface to handle file uploads.
- [x] 1.4 Register all Repositories and Services in a `RepositoryServiceProvider` (or `AppServiceProvider`).


## 2. Refactor Booking Domain Actions

- [x] 2.1 Update `CreateBooking` Action to use Repositories instead of calling Eloquent `Court::query()` and `Booking::create()`.
- [x] 2.2 Update other Booking Actions (`ApplyBookingCoupon`, `CancelBooking`, `RescheduleBooking`, etc.) to use Repositories.
- [x] 2.3 Refactor `BookingService` to remove redundant pass-through methods (controllers will call actions directly) and extract `notifyExpiring` logic to a new Action if appropriate.
- [x] 2.4 Update `BookingController` to reflect the service/action changes.


## 3. Refactor Admin Domain (Venues & Courts)

- [x] 3.1 Create `Domain/Admin/Actions/CreateVenue` and move logic from `AdminController@storeVenue`.
- [x] 3.2 Create `Domain/Admin/Actions/UpdateVenue` and move logic from `AdminController@updateVenue`.
- [x] 3.3 Create `Domain/Admin/Actions/ManageCourtPrices` for creating, updating, and bulk updating prices.
- [x] 3.4 Create `Domain/Admin/Actions/ManageCourtBlocks` for creating and deleting blocks.
- [x] 3.5 Update `AdminController` to use these Actions and Repositories instead of Eloquent models directly. Use the new `FileStorageService` for the `upload` method.

## 4. Refactor Admin Domain (Finance & Reviews)

- [x] 4.1 Create `Domain/Admin/Actions/ReviewPayment` and move logic from `AdminController@reviewPayment`.
- [x] 4.2 Create `Domain/Admin/Actions/ProcessRefund` and move logic from `AdminController@processRefund`.
- [x] 4.3 Update `AdminController` to use these new Actions.

## 5. Verification

- [x] 5.1 Run all existing automated tests to ensure no regressions.
- [x] 5.2 Manually verify the Admin flows (Create Venue, Update Prices, Review Payment).
- [x] 5.3 Manually verify the Booking flow (Create Booking, Cancel Booking).

