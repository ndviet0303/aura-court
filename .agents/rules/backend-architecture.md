# Backend Architecture Rules (Laravel / PHP)

## Layered Architecture

All backend code follows a **Domain-driven Layered Architecture** inside `app/`:

```
app/
├── Domain/{Feature}/        # Pure business logic (no framework deps)
│   ├── Actions/             # Single-responsibility use cases
│   ├── DTOs/                # Data Transfer Objects (readonly)
│   ├── Events/              # Domain events
│   ├── Exceptions/          # Domain-specific exceptions
│   ├── Rules/               # Business rule Value Objects
│   └── Policies/            # Authorization policies
├── Http/                    # Presentation layer (thin)
│   ├── Controllers/{Feature}/
│   ├── Requests/{Feature}/  # Form Request validation
│   └── Resources/           # API Resource transformers
├── Infrastructure/          # External service adapters
├── Models/                  # Eloquent models (rich, with domain methods)
├── Contracts/               # Interfaces for all services
└── Providers/               # Service container bindings
```

## Controllers

- Controllers are **thin orchestrators**. They receive a validated request, call an Action, and return a Resource.
- Never put business logic, raw queries, or notifications in controllers.
- Group controllers by domain feature: `Controllers/Booking/`, `Controllers/Admin/Venue/`, etc.
- Use single-action or RESTful controllers — never > 7 public methods.
- Inject Actions and Repositories, never raw Models or DB facade.

```php
// ✅ Good
public function store(StoreBookingRequest $request, CreateBooking $action): JsonResponse
{
    return BookingResource::make($action->execute(CreateBookingData::from($request)));
}

// ❌ Bad — logic in controller
public function store(Request $request)
{
    $data = $request->validate([...]);
    $booking = DB::transaction(function () use ($data) { ... });
    return $booking;
}
```

## Validation

- **Always** use Form Request classes (`app/Http/Requests/`) for input validation.
- Never use `$request->validate()` inline in controllers.
- Never use `ValidationException::withMessages()` for input validation — reserve it for domain rule violations in Actions only.
- Form Requests handle authorization via `authorize()`.

## Actions (Use Cases)

- One Action = one use case. Name clearly: `CreateBooking`, `CancelBooking`, `ExpirePendingBookings`.
- Actions live in `Domain/{Feature}/Actions/`.
- Accept a DTO as input, return a DTO or Model.
- Actions can call other Actions and domain services, but never call Controllers.
- DB transactions belong in Actions, not Controllers.

## DTOs (Data Transfer Objects)

- Use `readonly class` for all DTOs (PHP 8.2+).
- DTOs carry data between layers — they are immutable.
- Input DTOs have a `static from(FormRequest $request)` factory.
- Output DTOs are separate from API Resources.

```php
readonly class CreateBookingData
{
    public function __construct(
        public int $userId,
        public int $courtId,
        public array $slots,
        public ?string $couponCode = null,
    ) {}

    public static function from(StoreBookingRequest $request): self
    {
        return new self(
            userId: $request->user()->id,
            courtId: $request->validated('court_id'),
            slots: $request->validated('slots'),
            couponCode: $request->validated('coupon_code'),
        );
    }
}
```

## Models

- Models are **rich** — they contain domain methods, scopes, accessors, relationships.
- Never put business orchestration in Models (that belongs in Actions).
- Domain logic that is purely about the entity's own state belongs on the Model.
- Use Eloquent scopes for reusable query patterns.

```php
// ✅ Model owns its own state logic
public function isExpired(): bool
{
    return $this->status === 'pending_payment'
        && $this->payment_expires_at?->isPast();
}

// ❌ Don't put cross-entity orchestration on Models
public function cancelAndRefund(): void { ... } // belongs in CancelBooking Action
```

## API Resources

- All API responses go through `JsonResource` / `ResourceCollection`.
- Never return raw Models or arrays from controllers.
- Resources handle transformation, hiding internal fields, and formatting.

## Interfaces / Contracts

- Every external service integration must have an interface in `app/Contracts/`.
- Bind implementations in service providers.
- The `Contracts/Payment/` pattern is the gold standard — extend this to all domains.

## Events & Listeners

- Use domain Events for side effects (notifications, audit logs, cache invalidation).
- Actions dispatch events; Listeners handle cross-cutting concerns.
- Never put notification logic inside business methods.

## Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| Action class | Verb + Noun | `CreateBooking`, `CancelSlots` |
| DTO | Noun + "Data" | `CreateBookingData`, `BookingResult` |
| Form Request | Verb + Noun + "Request" | `StoreBookingRequest` |
| Resource | Noun + "Resource" | `BookingResource` |
| Event | Noun + PastTense | `BookingCreated`, `PaymentReceived` |
| Exception | Noun + "Exception" | `BookingException` |
| Interface | Noun + "Interface" or "Contract" | `PaymentProviderInterface` |

## Testing

- Unit test Actions with mocked dependencies.
- Feature test Controllers via HTTP with `actingAs()`.
- Never test internal service methods directly — test through Actions.

## General Rules

- Prefer composition over inheritance.
- Max cyclomatic complexity per method: 10.
- Max lines per class: 300. If exceeding, split into smaller Actions.
- Use PHP 8.2+ features: readonly classes, enums, named arguments, match expressions.
- All money values are integers (cents/VND). Never use float for money.
- Timezone: All storage in UTC. Display in `Asia/Ho_Chi_Minh`. Use Carbon.
- Language: Code in English. User-facing messages in Vietnamese (via lang files).
