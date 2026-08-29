# Frontend Architecture Rules (Vue 3 / TypeScript)

## Feature-based Architecture

All frontend code follows a **feature-based modular architecture** inside `src/`:

```
src/
├── core/                        # Shared infrastructure (no UI)
│   ├── api/
│   │   ├── client.ts            # HTTP client (fetch wrapper)
│   │   └── interceptors.ts      # Auth, CSRF, error handling
│   ├── composables/             # Shared composables
│   │   ├── useApiCall.ts        # Generic loading/error state
│   │   ├── usePagination.ts
│   │   └── useFormValidation.ts
│   ├── types/                   # Shared TypeScript types
│   │   ├── common.ts
│   │   └── api.ts               # ApiError, QueryParams, etc.
│   └── utils/                   # Pure utility functions
│
├── features/{domain}/           # Domain feature modules
│   ├── api/                     # Domain-specific API calls
│   │   └── {domain}Api.ts
│   ├── composables/             # Domain-specific composables
│   ├── components/              # Domain-specific components
│   ├── stores/                  # Domain Pinia stores
│   ├── types/                   # Domain types
│   └── views/                   # View pages (thin orchestrators)
│
├── shared/                      # Shared UI components
│   ├── components/              # Reusable UI atoms/molecules
│   └── layouts/                 # Layout components
│
├── router/
├── i18n/
└── main.ts
```

## Views (Pages)

- Views are **thin orchestrators** — ideally <150 lines, max ~200 lines of `<script setup>`.
- Views compose feature components and call composables. They do NOT contain:
  - Direct API calls (`api.get()`, `apiV2.post()`)
  - Complex business logic
  - Reusable UI patterns
  - Types/interfaces (unless page-local and trivial)
- A view that exceeds **250 total lines** (template + script + style) must be decomposed. **God Views (>700 lines) are strictly forbidden.**

```vue
<!-- ✅ Good — thin view -->
<script setup lang="ts">
import { useTournamentList } from '@/features/tournament/composables/useTournamentList'
import TournamentGrid from '@/features/tournament/components/TournamentGrid.vue'
import TournamentFilters from '@/features/tournament/components/TournamentFilters.vue'

const { tournaments, loading, error, filters, loadMore } = useTournamentList()
</script>

<template>
  <div class="tournaments-page">
    <TournamentFilters v-model="filters" />
    <TournamentGrid :items="tournaments" :loading="loading" @load-more="loadMore" />
  </div>
</template>
```

```vue
<!-- ❌ Bad — monolithic view with 3000+ lines -->
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiV2 } from '@/lib/api'

interface Tournament { /* ... 30 lines of types ... */ }
const tournaments = ref<Tournament[]>([])
// ... 500 more lines of logic
</script>
```

## API Layer

- **All API calls** go through domain-specific API modules: `features/{domain}/api/{domain}Api.ts`
- Views and components **never** import `api` or `apiV2` directly.
- API modules return typed promises and handle response mapping.
- Shared HTTP client stays in `core/api/client.ts`.

```ts
// ✅ features/booking/api/bookingApi.ts
import { api } from '@/core/api/client'
import type { Booking, CreateBookingPayload, BookingWithPayment } from '../types/booking'

export const bookingApi = {
  list: () => api.get<Booking[]>('/bookings'),
  create: (payload: CreateBookingPayload) =>
    api.post<BookingWithPayment>('/bookings', payload),
  cancel: (code: string, reason?: string) =>
    api.post<Booking>(`/bookings/${code}/cancel`, { reason }),
}
```

## Composables

- Use composables to encapsulate **reusable reactive logic**.
- Name pattern: `use{Feature}{Action}.ts` — e.g., `useBookingCreate`, `useTournamentList`.
- Composables encapsulate: state, API calls, error handling, loading state.
- Shared composables (pagination, form validation, confirm dialogs) live in `core/composables/`.
- Domain composables live in `features/{domain}/composables/`.

```ts
// ✅ features/booking/composables/useBookingCreate.ts
export function useBookingCreate() {
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function create(courtId: number, slots: string[], couponCode?: string) {
    loading.value = true
    error.value = null
    try {
      return await bookingApi.create({ court_id: courtId, slots, coupon_code: couponCode })
    } catch (e) {
      error.value = errorMessage(e)
      throw e
    } finally {
      loading.value = false
    }
  }

  return { create, loading, error }
}
```

## Components

- **Shared components** (`shared/components/`): Pure UI atoms and molecules. No domain logic. Props in, events out.
- **Feature components** (`features/{domain}/components/`): Domain-aware, can access domain stores and composables.
- Every component file max **300 lines**. Split when exceeding.
- Use `defineProps` and `defineEmits` with TypeScript generics.
- Prefix shared components with a project prefix: `Aura{Name}.vue`.

## Pinia Stores

- One store per domain feature, located in `features/{domain}/stores/`.
- Stores manage **shared state** across components within a feature.
- If state is only used within one component/composable, use local `ref()` instead.
- Stores use `defineStore` with the Composition API (`setup()` syntax).
- Stores call domain API modules, not raw `api.get()`.
- **Legacy Stores (`@/stores/`) are deprecated.** All new state management must be placed inside the relevant `features/{domain}/stores/` module.

## TypeScript Types

- **Core types** (API error, pagination, common enums) → `core/types/`
- **Domain types** (Booking, Court, Tournament) → `features/{domain}/types/`
- Types are defined as `type` (preferred) or `interface`. Use `interface` only when extension is needed.
- Avoid `any`. Use `unknown` for untyped data, then narrow.
- Export types from the feature's barrel file: `features/{domain}/index.ts`.
- Inline types/interfaces in views or components are **forbidden** unless trivially small.
- **Cross-feature type imports** (e.g., `admin` importing a type from `court/types/`) are acceptable. However, cross-feature imports of logic (composables/stores) should be avoided. Shared types used across >2 features should ideally be promoted to `core/types/`.

```ts
// ✅ features/booking/types/booking.ts
export type BookingStatus = 'pending_payment' | 'confirmed' | 'cancelled' | 'expired' | 'refunded'

export type Booking = {
  id: number
  code: string
  status: BookingStatus
  totalAmount: number
  court: Court
  slots: BookingSlot[]
}
```

## Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| View file | `{Name}View.vue` | `BookingView.vue` |
| Component file | `{Name}.vue` or `{Prefix}{Name}.vue` | `SlotPicker.vue`, `AuraButton.vue` |
| Composable file | `use{Name}.ts` | `useBookingCreate.ts` |
| Store file | `{name}Store.ts` | `bookingStore.ts` |
| API module | `{name}Api.ts` | `bookingApi.ts` |
| Type file | `{name}.ts` | `booking.ts` |
| Feature dir | kebab-case | `features/booking/`, `features/tournament/` |

## Error Handling

- Use the shared `useApiCall` composable for consistent loading/error state.
- Display user-facing errors via the `errorMessage()` utility.
- Never show raw API errors or stack traces to users.
- Toast/modal errors for mutations. Inline errors for forms.

## Styling

- Use `<style scoped>` for component styles. **Inline scoped CSS should be the standard for component-specific styles (100% compliance).**
- Use CSS custom properties for theming (defined in `assets/`).
- No inline styles (`style="..."`) in templates unless dynamically computed.
- BEM naming for CSS classes when scoped styles aren't sufficient.

## Performance

- Lazy-load feature views via `defineAsyncComponent` or dynamic `import()` in router.
- Use `v-memo` or `computed` for expensive template computations.
- Debounce search inputs and filter changes.
- Paginate lists — never load all items at once.

## General Rules

- All code in English. User-facing strings in Vietnamese via i18n.
- Max file size: 400 lines (components), 200 lines script (views).
- Prefer `<script setup lang="ts">` (Composition API).
- No `this.$` usage — Options API is forbidden in new code.
- Money values are integers (VND). Format with `Intl.NumberFormat('vi-VN')`.
- Dates: Store as ISO strings. Display with locale-aware formatting.
