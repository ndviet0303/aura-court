import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useReservations } from '../composables/useReservations'
import { bookingApi } from '../api/bookingApi'
import type { PriceQuote, Reservation } from '../types/reservation'

const mockRoute = vi.hoisted(() => ({ params: {} as Record<string, string> }))

vi.mock('vue-router', () => ({ useRoute: () => mockRoute }))
vi.mock('../api/bookingApi', () => ({
  bookingApi: {
    getCourts: vi.fn(),
    getMyReservations: vi.fn(),
    getReservation: vi.fn(),
    checkoutReservation: vi.fn(),
    holdReservation: vi.fn(),
  },
}))

const reservation = (overrides: Partial<Reservation>): Reservation =>
  ({
    id: 9,
    code: 'RSV-DEEP-1',
    status: 'confirmed',
    total_amount: 200000,
    payment_model: 'direct_owner_v1',
    court: { id: 3, name: 'Sân 3' },
    slots: [],
    ...overrides,
  }) as Reservation

function mountComposable() {
  let state!: ReturnType<typeof useReservations>
  mount(
    defineComponent({
      setup() {
        state = useReservations()
        return () => null
      },
    }),
  )
  return state
}

describe('useReservations', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockRoute.params = {}
    vi.mocked(bookingApi.getCourts).mockResolvedValue([])
    vi.mocked(bookingApi.getMyReservations).mockResolvedValue({ data: [], total: 0 })
  })

  it('shows slot times in venue time', () => {
    const state = mountComposable()

    expect(state.formatTimeSlot('2026-10-13T12:00:00.000000Z', '2026-10-13T13:00:00.000000Z')).toBe(
      '19:00 - 20:00',
    )
    expect(state.formatTimeSlot('2026-10-13T12:30:00.000000Z')).toBe('19:30 - 20:30')
  })

  it('loads the list from the caller-wide reservations endpoint', async () => {
    const legacy = reservation({ code: 'LEGACY-BK-1', status: 'legacy' })
    vi.mocked(bookingApi.getMyReservations).mockResolvedValue({ data: [legacy], total: 1 })

    const state = mountComposable()
    await flushPromises()

    expect(state.reservations.value).toEqual([legacy])
    expect(bookingApi.getReservation).not.toHaveBeenCalled()
  })

  it('keeps used orders under the confirmed tab and expired ones under cancelled', async () => {
    const used = reservation({
      code: 'RSV-USED',
      status: 'confirmed',
      metadata: { operational_status: 'completed' },
    })
    const expired = reservation({ code: 'RSV-EXP', status: 'expired' })
    vi.mocked(bookingApi.getMyReservations).mockResolvedValue({ data: [used, expired], total: 2 })

    const state = mountComposable()
    await flushPromises()

    state.activeTab.value = 'confirmed'
    expect(state.filteredReservations.value).toEqual([used])
    expect(state.confirmedCount.value).toBe(1)
    state.activeTab.value = 'cancelled'
    expect(state.filteredReservations.value).toEqual([expired])
    expect(state.cancelledCount.value).toBe(1)
  })

  it('opens details and the payment modal for a held direct-owner deep link', async () => {
    const held = reservation({ status: 'held' })
    mockRoute.params = { code: 'RSV-DEEP-1' }
    vi.mocked(bookingApi.getReservation).mockResolvedValue({ reservation: held })

    const state = mountComposable()
    await flushPromises()

    expect(bookingApi.getReservation).toHaveBeenCalledWith('RSV-DEEP-1')
    expect(state.selectedReservationForDetails.value).toEqual(held)
    expect(state.selectedReservationForPayment.value).toEqual(held)
  })

  it('opens only the details for a confirmed deep link', async () => {
    const confirmed = reservation({ status: 'confirmed' })
    mockRoute.params = { code: 'RSV-DEEP-1' }
    vi.mocked(bookingApi.getReservation).mockResolvedValue({ reservation: confirmed })

    const state = mountComposable()
    await flushPromises()

    expect(state.selectedReservationForDetails.value).toEqual(confirmed)
    expect(state.selectedReservationForPayment.value).toBeNull()
  })

  it('checks out the quote with its court and sends loyalty only for a positive redemption', async () => {
    vi.mocked(bookingApi.checkoutReservation).mockResolvedValue({
      reservation: reservation({ status: 'confirmed' }),
    })
    const state = mountComposable()
    await flushPromises()
    const quote = {
      quote_token: 'qt-1',
      total_amount: 200000,
      expires_at: '2099-01-01T00:00:00Z',
    } as PriceQuote

    state.selectedCourtId.value = 3
    state.activeQuote.value = quote
    await state.checkoutReservation({
      loyalty_points: 0,
      loyalty_policy_version: 2,
      discount_vnd: 0,
    })
    state.activeQuote.value = quote
    await state.checkoutReservation({
      loyalty_points: 50,
      loyalty_policy_version: 2,
      idempotency_key: 'loy-key-1',
      discount_vnd: 50000,
    })

    // expected_total is the amount payable shown: the quote total less the points discount.
    expect(vi.mocked(bookingApi.checkoutReservation).mock.calls).toEqual([
      [{ quote_token: 'qt-1', court_id: 3, expected_total: 200000 }, undefined],
      [
        {
          quote_token: 'qt-1',
          court_id: 3,
          expected_total: 150000,
          loyalty_points: 50,
          loyalty_policy_version: 2,
        },
        'loy-key-1',
      ],
    ])
  })

  it('holds the quote at its total, the amount the customer was shown', async () => {
    vi.mocked(bookingApi.holdReservation).mockResolvedValue({
      reservation: reservation({ status: 'held' }),
    })
    const state = mountComposable()
    await flushPromises()
    state.activeQuote.value = {
      quote_token: 'qt-2',
      total_amount: 180000,
      expires_at: '2099-01-01T00:00:00Z',
    } as PriceQuote

    await state.holdReservation()

    expect(bookingApi.holdReservation).toHaveBeenCalledWith({
      quote_token: 'qt-2',
      expected_total: 180000,
    })
  })

  it('shows a changed price and resubmits only after the customer confirms it', async () => {
    vi.mocked(bookingApi.checkoutReservation)
      .mockRejectedValueOnce({
        status: 409,
        code: 'PRICE_CHANGED',
        message: 'Giá đã thay đổi.',
        rental: 240000,
        discount: 20000,
        total: 220000,
      })
      .mockResolvedValueOnce({ reservation: reservation({ status: 'confirmed' }) })
    const state = mountComposable()
    await flushPromises()
    state.selectedCourtId.value = 3
    state.activeQuote.value = {
      quote_token: 'qt-3',
      total_amount: 200000,
      expires_at: '2099-01-01T00:00:00Z',
    } as PriceQuote
    const loyalty = { loyalty_points: 0, loyalty_policy_version: 2, idempotency_key: 'loy-key-3' }

    await state.checkoutReservation(loyalty)

    expect(state.priceChange.value).toEqual({ rental: 240000, discount: 20000, total: 220000 })
    expect(state.error.value).toBe('')
    expect(vi.mocked(bookingApi.checkoutReservation).mock.calls).toEqual([
      [{ quote_token: 'qt-3', court_id: 3, expected_total: 200000 }, 'loy-key-3'],
    ])

    await state.confirmPriceChange()

    expect(state.priceChange.value).toBeNull()
    // The resubmission has another body, so it does not reuse the first Idempotency-Key.
    expect(vi.mocked(bookingApi.checkoutReservation).mock.calls[1]).toEqual([
      { quote_token: 'qt-3', court_id: 3, expected_total: 220000 },
      undefined,
    ])
  })

  it.each([
    ['PHONE_REQUIRED', 'Vui lòng cập nhật số điện thoại trong hồ sơ trước khi đặt sân.'],
    [
      'PAYMENT_WINDOW_CLOSED',
      'Đã hết thời gian thanh toán cho khung giờ này. Vui lòng chọn giờ khác.',
    ],
  ])('explains a %s refusal of the hold', async (code, message) => {
    vi.mocked(bookingApi.holdReservation).mockRejectedValueOnce({ status: 422, code, message: 'x' })
    const state = mountComposable()
    await flushPromises()
    state.activeQuote.value = {
      quote_token: 'qt-4',
      total_amount: 100000,
      expires_at: '2099-01-01T00:00:00Z',
    } as PriceQuote

    await state.holdReservation()

    expect(state.error.value).toBe(message)
    expect(state.priceChange.value).toBeNull()
  })
})
