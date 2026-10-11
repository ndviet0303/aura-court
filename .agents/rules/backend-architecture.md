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
- Max lines per class: 300. This covers every file in `app/`, including `app/Services`. Split a large service into smaller services; it does not have to become Actions.
- All money values are integers (cents/VND). Never use float for money.
- Timezone: Storage in UTC. Display in `Asia/Ho_Chi_Minh`.
- Language: Code in English. User-facing messages in Vietnamese (via lang files).

---

## 11. Static analysis

CI (`backend_static`) runs these in check-only mode, with no database:

1. `vendor/bin/pint --test`
2. `vendor/bin/phpstan analyse -c phpstan-level0.neon`: level 0 with no baseline.
3. `vendor/bin/phpstan analyse`: level 5 with `phpstan-baseline.neon`.
4. `vendor/bin/phpunit tests/Unit/Architecture`

**PHPStan baseline.** `phpstan-baseline.neon` records the existing level-5 findings. It only shrinks.

- New findings fail. Fix them; never regenerate the baseline to absorb them.
- Level-0 findings (unknown classes, functions or methods, wrong argument counts, undefined variables) never go into the baseline. The level-0 run ignores the baseline, so it fails on them anyway.
- When you fix baselined findings, PHPStan fails on the unmatched entries. If those are the only failures, run `vendor/bin/phpstan analyse --generate-baseline` and commit the smaller file.
- A justified exception gets an inline `// @phpstan-ignore <identifier> (reason)`. The reason is required.

**Architecture check.** `tests/Unit/Architecture/BackendArchitectureTest.php` checks every file in `app/` against these limits:

| Rule | Limit |
|------|-------|
| Lines per file | 300 |
| Public methods per controller | 7 |
| `$request->validate(` or `Storage::` in a controller | none |
| `<Model>::query()`, `where()`, `find()` and similar in a controller or a `Domain/*/Actions` class | none |
| `Illuminate\Http\Request` import, or `execute()` parameter typed as a request, in an action | none |
| DTO in `Domain/*/DTOs` that is not a `readonly class` | none |
| `Http/Requests` file without `extends FormRequest`, `rules()` or `authorize()` | none |
| `Contracts/Repositories` interface not bound in `RepositoryServiceProvider` | none |

Files that broke a rule when the check landed are listed in `tests/Unit/Architecture/architecture-allowlist.json` as `[rule, file, ceiling]`.

- A listed file may shrink and grow back up to its ceiling, but never past it.
- Lower a ceiling on purpose when a file shrinks but is still over the limit. Never raise one.
- Once a file is within the limit, the check fails until you delete its entry.
- Fix new violations instead of allowlisting them. The ready-to-paste line in the failure message is for existing debt that moves, such as a renamed file.
