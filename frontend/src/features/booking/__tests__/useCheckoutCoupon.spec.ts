import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { useCheckoutCoupon } from '../composables/useCheckoutCoupon'
import { bookingApi } from '../api/bookingApi'
import { useBookingStore } from '../stores/bookingStore'
import type { PriceQuote, Reservation } from '../types/reservation'

vi.mock('../api/bookingApi', () => ({
  bookingApi: { getPriceQuote: vi.fn(), checkoutReservation: vi.fn() },
}))

const slot = (startsAt: string) => ({ startsAt, endsAt: startsAt, price: 100000, available: true })
const first = slot('2026-10-14T11:00:00Z')
const second = slot('2026-10-14T12:00:00Z')

const couponQuote = {
  id: 7,
  quote_token: 'qt-coupon',
  subtotal: 200000,
  total_amount: 180000,
  benefit_snapshot: { coupon_id: 5, coupon_code: 'SUMMER10', coupon_discount_amount: 20000 },
  expires_at: '2026-10-14T10:15:00Z',
  slot_snapshot: [],
} as PriceQuote

let scope = effectScope()

async function setup() {
  const store = useBookingStore()
  store.selectedCourt = 3
  store.toggle(first)
  store.toggle(second)
  scope = effectScope()
  const coupon = scope.run(useCheckoutCoupon)!
  coupon.code.value = '  SUMMER10 '
  await nextTick()
  return { store, coupon }
}

describe('useCheckoutCoupon', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  afterEach(() => scope.stop())

  it('prices the selected times with the trimmed code and shows the discounted total', async () => {
    vi.mocked(bookingApi.getPriceQuote).mockResolvedValue({ quote: couponQuote })
    const { coupon } = await setup()
    expect(coupon.total.value).toBe(200000)

    await coupon.apply()

    expect(bookingApi.getPriceQuote).toHaveBeenCalledWith({
      court_id: 3,
      slots: [first.startsAt, second.startsAt],
      coupon_code: 'SUMMER10',
    })
    expect(coupon.appliedCode.value).toBe('SUMMER10')
    expect(coupon.discount.value).toBe(20000)
    expect(coupon.total.value).toBe(180000)
    expect(coupon.error.value).toBe('')
    expect(coupon.loading.value).toBe(false)
  })

  it('shows the coupon_code error of a 422, or its message when the field has none', async () => {
    vi.mocked(bookingApi.getPriceQuote).mockRejectedValueOnce({
      status: 422,
      message: 'The given data was invalid.',
      errors: { coupon_code: ['Mã giảm giá đã hết hạn.'] },
    })
    const { coupon } = await setup()

    await coupon.apply()

    expect(coupon.error.value).toBe('Mã giảm giá đã hết hạn.')
    expect(coupon.appliedCode.value).toBeNull()
    expect(coupon.total.value).toBe(200000)

    vi.mocked(bookingApi.getPriceQuote).mockRejectedValueOnce({
      status: 422,
      message: 'Coupon cannot be combined with a membership.',
    })
    await coupon.apply()

    expect(coupon.error.value).toBe('Coupon cannot be combined with a membership.')
  })

  it('drops the applied coupon when the times, the court or the code change', async () => {
    vi.mocked(bookingApi.getPriceQuote).mockResolvedValue({ quote: couponQuote })
    const { store, coupon } = await setup()

    await coupon.apply()
    store.toggle(second)
    await nextTick()
    expect(coupon.appliedCode.value).toBeNull()
    expect(coupon.discount.value).toBe(0)
    expect(coupon.total.value).toBe(100000)

    await coupon.apply()
    store.selectedCourt = 4
    await nextTick()
    expect(coupon.appliedCode.value).toBeNull()

    await coupon.apply()
    coupon.code.value = 'OTHER'
    await nextTick()
    expect(coupon.appliedCode.value).toBeNull()
  })

  it('ignores a quote that resolves after the selection changed', async () => {
    let resolve!: (value: { quote: PriceQuote }) => void
    vi.mocked(bookingApi.getPriceQuote).mockReturnValue(
      new Promise((done) => {
        resolve = done
      }),
    )
    const { store, coupon } = await setup()

    const pending = coupon.apply()
    expect(coupon.loading.value).toBe(true)
    store.toggle(second)
    await nextTick()
    resolve({ quote: couponQuote })
    await pending

    expect(coupon.appliedCode.value).toBeNull()
    expect(coupon.loading.value).toBe(false)
  })

  it('sends the applied code and the amount payable shown with the checkout', async () => {
    vi.mocked(bookingApi.getPriceQuote).mockResolvedValue({ quote: couponQuote })
    vi.mocked(bookingApi.checkoutReservation).mockResolvedValue({
      reservation: { code: 'RSV-1' } as Reservation,
    })
    const { store, coupon } = await setup()

    await coupon.apply()
    // The rental is the price before the voucher; the total is what the customer pays.
    expect(coupon.rental.value).toBe(200000)
    expect(coupon.total.value).toBe(180000)
    await store.checkoutReservation(null, coupon.appliedCode.value, coupon.total.value)

    expect(bookingApi.checkoutReservation).toHaveBeenCalledWith(
      {
        court_id: 3,
        slots: [first.startsAt, second.startsAt],
        coupon_code: 'SUMMER10',
        expected_total: 180000,
      },
      undefined,
    )
  })
})
