# Backend Architecture Rules (Laravel / PHP)

## Layered Architecture (Clean Architecture / ADR)

All backend code follows a **Domain-driven Clean Layered Architecture** inside `app/`:

```
app/
├── Domain/{Feature}/        # Pure business logic & Use cases
│   ├── Actions/             # Single-responsibility use cases (Injected with Repositories)
│   ├── DTOs/                # Data Transfer Objects (readonly)
│   ├── Events/              # Domain events
│   ├── Exceptions/          # Domain-specific exceptions
│   ├── Rules/               # Business rule Value Objects
│   └── Policies/            # Authorization policies
├── Http/                    # Presentation layer (thin)
│   ├── Controllers/{Feature}/
│   ├── Requests/{Feature}/  # Form Request validation
│   └── Resources/           # API Resource transformers
├── Infrastructure/          # External service adapters & Concrete DB Implementations
│   ├── Repositories/Eloquent/ # Concrete Eloquent Repository implementations
│   └── Storage/             # Storage service implementations
├── Models/                  # Eloquent models (Active Record, entity state methods)
├── Contracts/               # Interfaces for all services & repositories
│   ├── Repositories/        # Repository Interfaces (e.g. VenueRepositoryInterface)
│   └── Storage/             # Storage Interfaces
└── Providers/               # Service container bindings (RepositoryServiceProvider)
```

---

## 1. Unified Call Flow
Whenever a new feature is added, strictly follow this flow:
`Controller -> (FormRequest -> DTO) -> Action (Domain Use Case) -> Repository Interface -> Database/Storage`

1. **Controller**: Validates HTTP requests via FormRequest, instantiates DTO, calls Action or Repository for read operations, and transforms output via API Resource.
2. **Action**: Contains the business rules, calculations, cross-repository orchestrations, and transactions. Never handles HTTP requests directly.
3. **Repository**: Abstracts all Eloquent queries. Domain Actions must inject Repository Interfaces (`Contracts/Repositories/*`), never call Eloquent queries directly.
4. **Service Provider**: All Repository Interfaces must be bound to concrete implementations in `app/Providers/RepositoryServiceProvider.php`.

---

## 2. Controllers (Thin Orchestrators)

- Never put business logic, raw SQL/Eloquent queries, file storage manipulations, or notifications in controllers.
- Group controllers by domain feature: `Controllers/Booking/`, `Controllers/Admin/`, etc.
- Use single-action or RESTful controllers — never > 7 public methods.
- Inject Actions and Repository Interfaces via constructor injection.

```php
// ✅ Good
public function store(StoreBookingRequest $request, CreateBooking $action): JsonResponse
{
    $booking = $action->execute(CreateBookingData::from($request));
    return response()->json(BookingResource::make($booking), 201);
}

// ❌ Bad — logic / DB operations directly in controller
public function store(Request $request)
{
    $data = $request->validate([...]);
    $booking = DB::transaction(function () use ($data) { ... });
    return $booking;
}
```

---

## 3. Repositories & Dependency Inversion

- **Directory**: Interfaces in `app/Contracts/Repositories/`, Implementations in `app/Infrastructure/Repositories/Eloquent/`.
- Repositories encapsulate query logic (filtering, eager loading, pagination, locking, create, update, delete).
- Do not bypass repositories by doing `Model::query()->where(...)` inside Domain Actions.

```php
// ✅ Good: Action uses Repository Interface
class CreateBooking
{
    public function __construct(
        private readonly CourtRepositoryInterface $courts,
        private readonly BookingRepositoryInterface $bookings,
    ) {}

    public function execute(CreateBookingData $data): Booking
    {
        $court = $this->courts->findActiveWithActiveVenue($data->courtId);
        // ...
        return $this->bookings->create([...]);
    }
}

// ❌ Bad: Action calls Eloquent Model directly
class CreateBooking
{
    public function execute(...)
    {
        $court = Court::where('id', $id)->firstOrFail();
        return Booking::create([...]);
    }
}
```

---

## 4. Validation

- **Always** use Form Request classes (`app/Http/Requests/`) for input validation.
- Never use `$request->validate()` inline in controllers.
- Form Requests handle authorization via `authorize()`.

---

## 5. Actions (Use Cases)

- One Action = one distinct use case. Name clearly with Verb + Noun: `CreateBooking`, `CancelBooking`, `CreateVenue`, `ManageCourtPrices`.
- Actions live in `Domain/{Feature}/Actions/`.
- Accept a DTO or primitive types as input, return a Model or DTO.
- DB transactions (`DB::transaction`) belong in Actions when multiple mutations occur.

---

## 6. DTOs (Data Transfer Objects)

- Use `readonly class` for all DTOs (PHP 8.2+).
- DTOs carry immutable data between layers.
- Input DTOs have a `static from(FormRequest $request)` factory method.

---

## 7. Storage & Infrastructure Adapters

- Never interact with `Storage::disk(...)` directly in Controllers or Actions.
- Use `Contracts/Storage/FileStorageServiceInterface` and inject `FileStorageService`.
- External payment/notification services must implement Contracts defined in `Contracts/`.

---

## 8. Models

- Models are entity definitions with relationships, attribute casts, accessors, and entity-specific state helper methods (`isExpired()`, `hasActiveSlots()`).
- Never put cross-entity orchestration logic in Models.

---

## 9. Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| Action class | Verb + Noun | `CreateBooking`, `UpdateVenue` |
| DTO | Noun + "Data" | `CreateBookingData`, `VenueData` |
| Repository Interface | Noun + "RepositoryInterface" | `CourtRepositoryInterface` |
| Eloquent Repository | "Eloquent" + Noun + "Repository" | `EloquentCourtRepository` |
| Form Request | Verb + Noun + "Request" | `StoreBookingRequest` |
| Resource | Noun + "Resource" | `BookingResource` |
| Event | Noun + PastTense | `BookingCreated` |
| Exception | Noun + "Exception" | `BookingException` |

---

## 10. General Rules

- Prefer composition over inheritance.
- Max lines per class: 300.
- All money values are integers (cents/VND). Never use float for money.
- Timezone: Storage in UTC. Display in `Asia/Ho_Chi_Minh`.
- Language: Code in English. User-facing messages in Vietnamese (via lang files).
