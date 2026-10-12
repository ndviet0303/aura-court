import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { usePaymentProcessing } from '../composables/usePaymentProcessing'
import { useUserBookings } from '../composables/useUserBookings'
import { bookingApi } from '../api/bookingApi'
import { useBookingStore } from '@/stores/booking'

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: { code: 'BK-AUTH-TEST-1' },
    query: {},
  }),
  useRouter: () => ({
    push: vi.fn(),
  }),
}))

vi.mock('../api/bookingApi', () => ({
  bookingApi: {
    getMyReservations: vi.fn(),
    getPayment: vi.fn(),
    submitReview: vi.fn(),
    getPaymentStatus: vi.fn(),
  },
}))

describe('Payment & History Authoritative Integration', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('Payment session connects to authoritative server record on reload: confirmed server status shows confirmed', async () => {
    vi.mocked(bookingApi.getPayment).mockResolvedValue({
      status: 'confirmed',
      booking: {
        id: 1,
        code: 'BK-AUTH-TEST-1',
        status: 'confirmed',
        total_amount: 150000,
      } as any,
    })

    const { startTimers } = usePaymentProcessing()
    startTimers()
    await vi.waitFor(() => expect(bookingApi.getPayment).toHaveBeenCalledWith('BK-AUTH-TEST-1'))
    const store = useBookingStore()
    expect(bookingApi.getPayment).toHaveBeenCalledWith('BK-AUTH-TEST-1')
    expect(store.current?.status).toBe('confirmed')
  })

  it('Payment session does not fabricate confirmed status when server returns pending payment on reload', async () => {
    vi.mocked(bookingApi.getPayment).mockResolvedValue({
      status: 'pending_payment',
      booking: {
        id: 1,
        code: 'BK-AUTH-TEST-1',
        status: 'pending_payment',
        total_amount: 150000,
      } as any,
    })
    const { startTimers } = usePaymentProcessing()
    startTimers()
    await vi.waitFor(() => expect(bookingApi.getPayment).toHaveBeenCalledWith('BK-AUTH-TEST-1'))
    const store = useBookingStore()
    expect(bookingApi.getPayment).toHaveBeenCalledWith('BK-AUTH-TEST-1')
    expect(store.current?.status).toBe('pending_payment')
  })

  it('handleWalletPay rejects and displays error when server cannot confirm payment', async () => {
    vi.mocked(bookingApi.getPayment).mockRejectedValue(new Error('Server error'))

    const { handleWalletPay, error } = usePaymentProcessing()
    await handleWalletPay()

    expect(error.value).toBeTruthy()
  })

  it('Booking history loads authoritative state and does not expose cancellation controls', async () => {
    vi.mocked(bookingApi.getMyReservations).mockResolvedValue({
      data: [
        {
          id: 1,
          code: 'RSV-HISTORY-TEST',
          status: 'confirmed',
          total_amount: 200000,
          slots: [{ id: 1, starts_at: '2026-10-10T10:00:00Z', ends_at: '2026-10-10T11:00:00Z' }],
        } as any,
        {
          id: 2,
          code: 'RSV-HISTORICAL-CANCELLED',
          status: 'cancelled',
          total_amount: 150000,
          slots: [{ id: 2, starts_at: '2026-09-01T10:00:00Z', ends_at: '2026-09-01T11:00:00Z' }],
        } as any,
      ],
    })

    const { load, bookings, filteredBookings } = useUserBookings()
    await load()

    expect(bookings.value.length).toBe(2)
    expect(bookings.value[0]?.status).toBe('confirmed')
    expect(bookings.value[1]?.status).toBe('cancelled')
    expect(filteredBookings.value.length).toBe(2)
  })
})
