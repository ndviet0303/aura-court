import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useV2Reservations } from '../composables/useV2Reservations'
import { bookingApi } from '../api/bookingApi'
import type { V2PriceQuote, V2Reservation } from '../types/v2Reservation'

const mockRoute = vi.hoisted(() => ({ params: {} as Record<string, string> }))

vi.mock('vue-router', () => ({ useRoute: () => mockRoute }))
vi.mock('../api/bookingApi', () => ({
  bookingApi: {
    getCourts: vi.fn(),
    getMyReservations: vi.fn(),
    getReservation: vi.fn(),
    checkoutReservation: vi.fn(),
  },
}))

const reservation = (overrides: Partial<V2Reservation>): V2Reservation =>
  ({
    id: 9,
    code: 'RSV-DEEP-1',
    status: 'confirmed',
    total_amount: 200000,
    payment_model: 'direct_owner_v1',
    court: { id: 3, name: 'Sân 3' },
    slots: [],
    ...overrides,
  }) as V2Reservation

function mountComposable() {
  let state!: ReturnType<typeof useV2Reservations>
  mount(
    defineComponent({
      setup() {
        state = useV2Reservations()
        return () => null
      },
    }),
  )
  return state
}

describe('useV2Reservations', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockRoute.params = {}
    vi.mocked(bookingApi.getCourts).mockResolvedValue([])
    vi.mocked(bookingApi.getMyReservations).mockResolvedValue({ data: [] })
  })

  it('loads the list from the caller-wide reservations endpoint', async () => {
    const legacy = reservation({ code: 'LEGACY-BK-1', status: 'legacy' })
    vi.mocked(bookingApi.getMyReservations).mockResolvedValue({ data: [legacy] })

    const state = mountComposable()
    await flushPromises()

    expect(state.reservations.value).toEqual([legacy])
    expect(bookingApi.getReservation).not.toHaveBeenCalled()
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
    const quote = { quote_token: 'qt-1', expires_at: '2099-01-01T00:00:00Z' } as V2PriceQuote

    state.selectedCourtId.value = 3
    state.activeQuote.value = quote
    await state.checkoutReservation({ loyalty_points: 0, loyalty_policy_version: 2 })
    state.activeQuote.value = quote
    await state.checkoutReservation({
      loyalty_points: 50,
      loyalty_policy_version: 2,
      idempotency_key: 'loy-key-1',
    })

    expect(vi.mocked(bookingApi.checkoutReservation).mock.calls).toEqual([
      [{ quote_token: 'qt-1', court_id: 3 }, undefined],
      [
        { quote_token: 'qt-1', court_id: 3, loyalty_points: 50, loyalty_policy_version: 2 },
        'loy-key-1',
      ],
    ])
  })
})
