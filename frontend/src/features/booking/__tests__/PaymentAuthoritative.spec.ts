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
    list: vi.fn(),
    getPayment: vi.fn(),
    cancel: vi.fn(),
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

  it('Booking history cancels and reloads authoritative state on success, preserves state on error', async () => {
    vi.mocked(bookingApi.list).mockResolvedValue({
      data: [
        {
          id: 1,
          code: 'BK-CANCEL-TEST',
          status: 'confirmed',
          total_amount: 200000,
          slots: [{ id: 1, starts_at: '2026-10-10T10:00:00Z', ends_at: '2026-10-10T11:00:00Z' }],
        } as any,
      ],
    })

    const { load, bookings, confirmCancel, openCancel, error } = useUserBookings()
    await load()
    const firstBooking = bookings.value[0]!
    expect(firstBooking.status).toBe('confirmed')

    // 1. Cancellation failure preserves original status and exposes error
    vi.mocked(bookingApi.cancel).mockRejectedValue(new Error('Booking cannot be cancelled within 2 hours'))
    openCancel(firstBooking)
    await confirmCancel()

    expect(error.value).toContain('Booking cannot be cancelled within 2 hours')
    expect(bookings.value[0]?.status).toBe('confirmed')
    // 2. Cancellation success calls cancel and reloads authoritative list
    vi.mocked(bookingApi.cancel).mockResolvedValue({} as any)
    vi.mocked(bookingApi.list).mockResolvedValue({
      data: [
        {
          id: 1,
          code: 'BK-CANCEL-TEST',
          status: 'refunded',
          total_amount: 200000,
          slots: [{ id: 1, starts_at: '2026-10-10T10:00:00Z', ends_at: '2026-10-10T11:00:00Z' }],
        } as any,
      ],
    })

    openCancel(bookings.value[0]!)
    await confirmCancel()

    expect(bookingApi.cancel).toHaveBeenCalledWith('BK-CANCEL-TEST', expect.any(String))
    expect(bookings.value[0]?.status).toBe('refunded')
  })
})
