## Why

The backend architecture currently lacks consistency and violates Clean Architecture principles in several areas. Specifically, the `AdminController` contains significant business logic and direct database interactions, creating "Fat Controllers" that are hard to test and maintain. Furthermore, there is an over-layering of Services and Actions where Services simply pass through to Actions without adding value, and the Domain layer (Actions) directly accesses Eloquent Models instead of using Dependency Inversion via Repositories. Refactoring this now will unify the call flow, improve testability, and establish a solid architectural foundation before the system grows larger.

## What Changes

- Refactor `AdminController` to delegate business logic to Use Case `Actions`.
- Introduce the **Repository Pattern** (`CourtRepository`, `VenueRepository`, `BookingRepository`, etc.) to decouple the Domain layer from Eloquent.
- Remove redundant pass-through logic in Services (e.g., `BookingService`) by having Controllers call Actions directly where appropriate.
- Move external integrations (like File Storage in `AdminController`) out of controllers and into Infrastructure services.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
None.

## Impact

- **Controllers**: `AdminController`, `BookingController` will be streamlined.
- **Actions**: `CreateBooking`, etc. will be updated to use Repositories instead of Eloquent models directly.
- **Services**: Pass-through methods in `BookingService` will be removed or refactored.
- **New Layer**: A new `Repositories` namespace will be created under `Contracts/Repositories` and implemented in `Infrastructure/Repositories` (or similar depending on the exact design).
