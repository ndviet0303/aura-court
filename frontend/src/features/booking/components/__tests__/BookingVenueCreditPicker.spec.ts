import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick, type App } from 'vue'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import { i18n } from '@/i18n'
import BookingVenueCreditPicker from '../BookingVenueCreditPicker.vue'
import { useBookingStore } from '../../stores/bookingStore'

const { profileApi, bookingApi } = vi.hoisted(() => ({
  profileApi: { getVenueCredits: vi.fn() },
  bookingApi: { checkoutReservation: vi.fn() },
}))
vi.mock('@/features/profile/api/profileApi', () => ({ profileApi }))
vi.mock('@/features/booking/api/bookingApi', () => ({ bookingApi }))

let app: App | null = null
let pinia: Pinia

async function flush() {
  for (let i = 0; i < 5; i++) await Promise.resolve()
  await nextTick()
}

async function mountPicker(organizationId: number, payable: number | null) {
  const el = document.createElement('div')
  document.body.appendChild(el)
  app = createApp(BookingVenueCreditPicker, { organizationId, payable })
  app.use(pinia).use(i18n).mount(el)
  await flush()
  return el
}

beforeEach(() => {
  pinia = createPinia()
  setActivePinia(pinia)
  profileApi.getVenueCredits.mockResolvedValue({
    data: [{ organization: { id: 5, name: 'Aura' }, balance: 80000, reserved: 0, entries: [] }],
  })
  bookingApi.checkoutReservation.mockResolvedValue({ reservation: { id: 1 } })
})

afterEach(() => {
  app?.unmount()
  app = null
  vi.clearAllMocks()
})

describe('checkout with venue credit', () => {
  it('is hidden when the customer has no credit at the court organization', async () => {
    const el = await mountPicker(9, 200000)

    expect(el.querySelector('[data-testid="venue-credit-picker"]')).toBeNull()
  })

  it('spends part of the amount payable and sends venue_credit_amount at checkout', async () => {
    const el = await mountPicker(5, 200000)
    const store = useBookingStore()
    expect(store.venueCreditPayload()).toEqual({})

    const use = el.querySelector<HTMLInputElement>('[data-testid="venue-credit-use"]')!
    use.checked = true
    use.dispatchEvent(new Event('change'))
    await nextTick()

    expect(el.querySelector('[data-testid="venue-credit-paid"]')!.textContent).toContain('80.000')
    expect(el.querySelector('[data-testid="venue-credit-rest"]')!.textContent).toContain('120.000')

    const amount = el.querySelector<HTMLInputElement>('[data-testid="venue-credit-amount"]')!
    amount.value = '500000'
    amount.dispatchEvent(new Event('change'))
    await nextTick()
    // Never more than the balance.
    expect(store.venueCreditAmount).toBe(80000)

    amount.value = '30000'
    amount.dispatchEvent(new Event('change'))
    await store.checkoutReservation(200000)

    expect(bookingApi.checkoutReservation).toHaveBeenCalledWith(
      expect.objectContaining({ expected_total: 200000, venue_credit_amount: 30000 }),
    )
  })

  it('caps the credit at the amount payable so it can cover a booking in full', async () => {
    const el = await mountPicker(5, 50000)
    const use = el.querySelector<HTMLInputElement>('[data-testid="venue-credit-use"]')!
    use.checked = true
    use.dispatchEvent(new Event('change'))
    await nextTick()

    expect(useBookingStore().venueCreditPayload()).toEqual({ venue_credit_amount: 50000 })
    expect(el.querySelector('[data-testid="venue-credit-rest"]')!.textContent).toMatch(/0\s*₫/)
  })

  it('drops the choice when the checkout form closes', async () => {
    const el = await mountPicker(5, 200000)
    const use = el.querySelector<HTMLInputElement>('[data-testid="venue-credit-use"]')!
    use.checked = true
    use.dispatchEvent(new Event('change'))
    await nextTick()
    const store = useBookingStore()
    expect(store.venueCreditAmount).toBe(80000)

    app!.unmount()
    app = null
    expect(store.venueCreditPayload()).toEqual({})
  })
})
