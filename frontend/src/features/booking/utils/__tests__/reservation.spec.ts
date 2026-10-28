import { describe, expect, it } from 'vitest'
import type { Reservation, ReservationReview } from '../../types/reservation'
import { canEditReview, canOfferTransfer, canReview, showsQr } from '../reservation'

const DAY = 24 * 60 * 60 * 1000
const checkedOut = '2026-10-01T10:00:00+07:00'
const out = new Date(checkedOut).getTime()

function booking(overrides: Partial<Reservation> = {}): Reservation {
  return {
    code: 'R1',
    status: 'confirmed',
    metadata: { operational_status: 'completed' },
    attendance: { status: 'checked_out', checked_out_at: checkedOut },
    review: null,
    ...overrides,
  } as Reservation
}

const review = (extra: Partial<ReservationReview> = {}): ReservationReview => ({
  id: 1,
  rating: 5,
  body: null,
  images: [],
  reply: null,
  created_at: checkedOut,
  deleted_at: null,
  ...extra,
})

describe('canReview (BR-147)', () => {
  it('allows a completed booking without a review within 7 days of check-out', () => {
    expect(canReview(booking(), out + 7 * DAY)).toBe(true)
  })

  it('refuses after the 7-day window', () => {
    expect(canReview(booking(), out + 7 * DAY + 1)).toBe(false)
  })

  it('refuses a booking that is not completed or has no check-out', () => {
    expect(canReview(booking({ metadata: { operational_status: 'in_use' } }), out)).toBe(false)
    expect(canReview(booking({ attendance: null }), out)).toBe(false)
  })

  it('refuses when a review exists, even a deleted one', () => {
    expect(canReview(booking({ review: review({ deleted_at: checkedOut }) }), out)).toBe(false)
  })
})

describe('canEditReview', () => {
  it('allows the author within 7 days of the first submission', () => {
    expect(canEditReview({ review: review() }, out + 7 * DAY)).toBe(true)
    expect(canEditReview({ review: review() }, out + 7 * DAY + 1)).toBe(false)
  })

  it('refuses a deleted or missing review', () => {
    expect(canEditReview({ review: review({ deleted_at: checkedOut }) }, out)).toBe(false)
    expect(canEditReview({ review: null }, out)).toBe(false)
  })
})

describe('showsQr (D5)', () => {
  it('needs a confirmed or in-use booking with a check-in code', () => {
    const confirmed = booking({ metadata: null, check_in_code: 'CHK1' })
    expect(showsQr(confirmed)).toBe(true)
    expect(showsQr({ ...confirmed, check_in_code: null })).toBe(false)
    expect(showsQr({ ...confirmed, status: 'held' })).toBe(false)
  })
})

describe('canOfferTransfer (UC-9.1)', () => {
  const upcoming = (startsAt: string, extra: Partial<Reservation> = {}) =>
    booking({ metadata: null, slots: [{ starts_at: startsAt, unit_price: 0 }], ...extra })

  it('offers a confirmed booking that has not started', () => {
    expect(canOfferTransfer(upcoming('2026-11-01T18:00:00+07:00'), out)).toBe(true)
  })

  it('refuses a started, unconfirmed or checked-in booking', () => {
    expect(canOfferTransfer(upcoming('2026-09-01T18:00:00+07:00'), out)).toBe(false)
    expect(canOfferTransfer(upcoming('2026-11-01T18:00:00+07:00', { status: 'held' }), out)).toBe(
      false,
    )
    expect(
      canOfferTransfer(
        upcoming('2026-11-01T18:00:00+07:00', { metadata: { operational_status: 'in_use' } }),
        out,
      ),
    ).toBe(false)
    expect(canOfferTransfer(booking({ metadata: null, slots: [] }), out)).toBe(false)
  })
})
