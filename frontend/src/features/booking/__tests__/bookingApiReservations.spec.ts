import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/core/api/client'
import { bookingApi } from '../api/bookingApi'

vi.mock('@/core/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn() },
}))

describe('bookingApi reservation endpoints', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it("lists the caller's own reservations across organizations", () => {
    bookingApi.getMyReservations()
    expect(api.get).toHaveBeenCalledWith('/me/reservations')
  })

  it('submits a review by reservation code only', () => {
    bookingApi.submitReview('RSV-1', { rating: 4, body: 'Sân đẹp' })
    expect(api.post).toHaveBeenCalledWith('/reviews', {
      reservation_code: 'RSV-1',
      rating: 4,
      body: 'Sân đẹp',
    })
  })

  it('sends the loyalty idempotency key as the checkout request option', () => {
    const payload = { court_id: 2, slots: ['2099-01-01T10:00:00Z'], loyalty_points: 50 }
    bookingApi.checkoutReservation(payload, 'loy-key-1')
    bookingApi.checkoutReservation(payload)

    expect(vi.mocked(api.post).mock.calls).toEqual([
      ['/reservations/checkout', payload, { idempotencyKey: 'loy-key-1' }],
      // No key: the client generates a fresh Idempotency-Key.
      ['/reservations/checkout', payload, undefined],
    ])
  })

  it('calls the reservation endpoints for refunds and reschedules', () => {
    const slots = ['2099-01-01T10:00:00Z']
    bookingApi.getRefundQuote('LEGACY-BK-1', [7, 8])
    bookingApi.createRefundRequest('LEGACY-BK-1', {
      slot_ids: [7],
      reason: 'Bận',
      idempotency_key: 'k1',
    })
    bookingApi.previewReschedule('RSV-1', { court_id: 2, slots })
    bookingApi.createRescheduleRequest('RSV-1', { court_id: 2, slots, idempotency_key: 'k2' })

    expect(api.get).toHaveBeenCalledWith(
      '/reservations/LEGACY-BK-1/refund-quote?slot_ids[]=7&slot_ids[]=8',
    )
    expect(api.post).toHaveBeenCalledWith(
      '/reservations/LEGACY-BK-1/refund-requests',
      { slot_ids: [7], reason: 'Bận', idempotency_key: 'k1' },
      { idempotencyKey: 'k1' },
    )
    expect(api.post).toHaveBeenCalledWith('/reservations/RSV-1/reschedule-preview', {
      court_id: 2,
      slots,
    })
    expect(api.post).toHaveBeenCalledWith(
      '/reservations/RSV-1/reschedule-requests',
      { court_id: 2, slots, idempotency_key: 'k2' },
      { idempotencyKey: 'k2' },
    )
  })
})
