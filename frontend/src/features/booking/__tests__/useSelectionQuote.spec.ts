import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'
import { useSelectionQuote } from '../composables/useSelectionQuote'
import { bookingApi } from '../api/bookingApi'
import { useBookingStore } from '../stores/bookingStore'
import { useAuthStore } from '@/features/auth/stores/authStore'
import type { PriceQuote } from '../types/reservation'

vi.mock('../api/bookingApi', () => ({
  bookingApi: { getPriceQuote: vi.fn(), checkoutReservation: vi.fn() },
}))

const slot = (startsAt: string) => ({ startsAt, endsAt: startsAt, price: 100000, available: true })
const first = slot('2026-10-14T11:00:00Z')
const second = slot('2026-10-14T12:00:00Z')

const quoteOf = (total: number) =>
  ({
    id: 7,
    quote_token: `qt-${total}`,
    subtotal: total,
    total_amount: total,
    benefit_snapshot: null,
    expires_at: '2026-10-14T10:15:00Z',
    slot_snapshot: [],
  }) as PriceQuote

let scope = effectScope()

async function setup() {
  useAuthStore().user = { id: 1, name: 'Player', email: 'p@example.com', role: 'customer' }
  const store = useBookingStore()
  store.selectedCourt = 3
  store.toggle(first)
  store.toggle(second)
  scope = effectScope()
  const quote = scope.run(useSelectionQuote)!
  await flushPromises()
  return { store, quote }
}

describe('useSelectionQuote', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  afterEach(() => scope.stop())

  it('drops the old quote and quotes again when the times or the court change', async () => {
    vi.mocked(bookingApi.getPriceQuote).mockResolvedValueOnce({ quote: quoteOf(200000) })
    const { store, quote } = await setup()
    expect(bookingApi.getPriceQuote).toHaveBeenCalledWith({
      court_id: 3,
      slots: [first.startsAt, second.startsAt],
    })
    expect(quote.total.value).toBe(200000)

    vi.mocked(bookingApi.getPriceQuote).mockReturnValueOnce(new Promise(() => {}))
    store.toggle(second)
    await nextTick()
    expect(quote.total.value).toBeNull()
    expect(quote.loading.value).toBe(true)
    expect(bookingApi.getPriceQuote).toHaveBeenLastCalledWith({
      court_id: 3,
      slots: [first.startsAt],
    })

    vi.mocked(bookingApi.getPriceQuote).mockResolvedValueOnce({ quote: quoteOf(90000) })
    store.selectedCourt = 4
    await flushPromises()
    expect(bookingApi.getPriceQuote).toHaveBeenLastCalledWith({
      court_id: 4,
      slots: [first.startsAt],
    })
    expect(quote.total.value).toBe(90000)
  })

  it('ignores a quote that resolves after the selection changed', async () => {
    let resolve!: (value: { quote: PriceQuote }) => void
    vi.mocked(bookingApi.getPriceQuote).mockReturnValueOnce(
      new Promise((done) => {
        resolve = done
      }),
    )
    vi.mocked(bookingApi.getPriceQuote).mockReturnValueOnce(new Promise(() => {}))
    const { store, quote } = await setup()
    expect(quote.loading.value).toBe(true)

    store.toggle(second)
    await nextTick()
    resolve({ quote: quoteOf(200000) })
    await flushPromises()

    expect(quote.total.value).toBeNull()
    expect(quote.loading.value).toBe(true)
  })
})
