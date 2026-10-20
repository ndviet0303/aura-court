import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ReservationView from '../views/ReservationView.vue'
import { bookingApi } from '../api/bookingApi'
import type { Reservation } from '../types/reservation'
import { i18n } from '@/i18n'

const mockRoute = vi.hoisted(() => ({ params: {} as Record<string, string> }))

vi.mock('vue-router', () => ({ useRoute: () => mockRoute }))
vi.mock('../api/bookingApi', () => ({
  bookingApi: {
    getCourts: vi.fn(),
    getMyReservations: vi.fn(),
    getReservation: vi.fn(),
    createReservationPayment: vi.fn(),
    getReservationPayment: vi.fn(),
  },
}))

// GET /reservations/{code} carries legacy_mapping for a mirrored legacy booking.
const heldMirror = {
  id: 7,
  code: 'LEGACY-BK-OLD7',
  status: 'held',
  payment_model: 'direct_owner_v1',
  total_amount: 150000,
  court: { id: 3, name: 'Sân 3' },
  slots: [],
  legacy_mapping: [
    { id: 1, reservation_id: 7, legacy_code: 'BK-OLD7', legacy_status: 'pending_payment' },
  ],
} as unknown as Reservation

describe('ReservationView deep link to a V1 mirror', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockRoute.params = { code: 'LEGACY-BK-OLD7' }
    vi.mocked(bookingApi.getCourts).mockResolvedValue([])
    // Empty list: the modals must render from the detail payload alone.
    vi.mocked(bookingApi.getMyReservations).mockResolvedValue({ data: [], total: 0 })
    vi.mocked(bookingApi.getReservation).mockResolvedValue({ reservation: heldMirror })
    vi.mocked(bookingApi.createReservationPayment).mockResolvedValue({
      reservation: { code: 'LEGACY-BK-OLD7', status: 'held' },
      payment_attempt: { id: 3, status: 'failed', amount: 150000 },
    } as never)
  })

  it('shows the original booking code in the detail and payment modals, never the mirror code', async () => {
    const wrapper = mount(ReservationView, {
      global: {
        plugins: [i18n],
        stubs: { QuoteCalculatorCard: true },
      },
    })
    await flushPromises()

    expect(bookingApi.getReservation).toHaveBeenCalledWith('LEGACY-BK-OLD7')
    expect(bookingApi.createReservationPayment).toHaveBeenCalledWith('LEGACY-BK-OLD7')
    const details = wrapper.findComponent({ name: 'ReservationDetailModal' })
    const payment = wrapper.get('[data-testid="reservation-payment-modal"]')
    expect(details.text()).toContain('BK-OLD7')
    expect(payment.text()).toContain('BK-OLD7')
    expect(wrapper.text()).not.toContain('LEGACY-')
    wrapper.unmount()
  })
})
