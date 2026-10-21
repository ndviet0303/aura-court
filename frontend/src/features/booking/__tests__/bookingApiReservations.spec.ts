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
    expect(api.get).toHaveBeenCalledWith('/me/reservations?page=1')
    bookingApi.getMyReservations(3)
    expect(api.get).toHaveBeenLastCalledWith('/me/reservations?page=3')
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
    const payload = {
      court_id: 2,
      slots: ['2099-01-01T10:00:00Z'],
      loyalty_points: 50,
      expected_total: 150000,
    }
    bookingApi.checkoutReservation(payload, 'loy-key-1')
    bookingApi.checkoutReservation(payload)

    expect(vi.mocked(api.post).mock.calls).toEqual([
      ['/reservations/checkout', payload, { idempotencyKey: 'loy-key-1' }],
      // No key: the client generates a fresh Idempotency-Key.
      ['/reservations/checkout', payload, undefined],
    ])
  })

  it('exposes no cancellation, refund or reschedule calls', () => {
    for (const name of [
      'getRefundQuote',
      'createRefundRequest',
      'previewReschedule',
      'createRescheduleRequest',
    ]) {
      expect(bookingApi).not.toHaveProperty(name)
    }
  })
})
