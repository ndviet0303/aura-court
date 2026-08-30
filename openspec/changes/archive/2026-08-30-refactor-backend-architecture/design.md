## Context

The backend codebase mixes Domain-Driven Design (Actions, DTOs) and typical Laravel Rapid Application Development patterns (direct Eloquent model usage, Fat Controllers like `AdminController`). See `proposal.md` for the motivation to decouple these layers and unify the call flow.

## Goals / Non-Goals

**Goals:**
- Implement Repository interfaces in the Domain layer (`Contracts/Repositories`) and their concrete implementations in `Infrastructure/Repositories`.
- Refactor `AdminController` to delegate core logic to newly created Actions.
- Ensure all Domain Actions interact with Repositories rather than Eloquent models directly.
- Remove simple pass-through methods from Services.

**Non-Goals:**
- Completely rewriting the payment/financial service integrations (they are already well-separated).
- Changing any frontend code or external API contracts.

## Decisions

1. **Repository Pattern:**
   - **Decision:** Introduce a `Contracts/Repositories` directory for interfaces (e.g., `CourtRepositoryInterface`, `BookingRepositoryInterface`). The implementations will reside in `Infrastructure/Repositories/Eloquent`.
   - **Rationale:** The Domain layer should not depend on Eloquent. Abstracting database access into Repositories allows the Domain to remain agnostic of the persistence framework, making it easier to test Actions in isolation.
   - **Alternatives:** Continue using Eloquent (Active Record) but mock it in tests. Rejected because it still tightly couples the Domain to the framework.

2. **Refactoring AdminController:**
   - **Decision:** Move all business logic from `AdminController` to specific Actions (e.g., `CreateVenue`, `UpdateVenue`, `BulkUpdateCourtPrices`).
   - **Rationale:** `AdminController` is currently a "Fat Controller" doing too much (file uploads, financial posting, complex domain logic). Moving this logic into Actions ensures code reusability and testability.

3. **Action-Service Relationship:**
   - **Decision:** Controllers will call Actions directly for distinct use cases. Services will only be used for cross-cutting concerns (like `PaymentService`, `AuditLogger`) or when orchestrating multiple actions.
   - **Rationale:** Removes redundant pass-through layers, reducing boilerplate.

4. **File Storage Infrastructure:**
   - **Decision:** Move the file upload logic (`Storage::disk()->putFileAs`) out of `AdminController` into a dedicated `Infrastructure/Storage/FileStorageService`.
   - **Rationale:** Decouples the controller from the filesystem configuration.

## Risks / Trade-offs

- **Risk:** Increased complexity due to more classes (Interfaces + Concrete Repositories + Actions).
  - **Mitigation:** Rely on Laravel's dependency injection container to automatically resolve interfaces, reducing wiring overhead. The long-term maintainability benefits outweigh the initial boilerplate.
- **Risk:** Breaking existing API functionality during refactoring.
  - **Mitigation:** Ensure all current tests pass before merging, and manually verify the Admin and Booking flows on local environments.
