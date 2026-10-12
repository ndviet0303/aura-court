import { describe, expect, it } from 'vitest'
import { venueClock, venueDate } from '../utils/venueTime'

describe('venueTime', () => {
  it('reads UTC API datetimes in venue time', () => {
    expect(venueClock('2026-08-03T12:00:00.000000Z')).toBe('19:00')
    expect(venueDate('2026-07-31T17:00:00.000000Z')).toBe('2026-08-01')
    expect(venueClock('2026-08-03T19:00:00+07:00')).toBe('19:00')
  })

  it('keeps wall-clock strings and empty values as they are', () => {
    expect(venueClock('2026-08-03 19:00:00')).toBe('19:00')
    expect(venueClock('2026-08-03T19:00:00')).toBe('19:00')
    expect(venueDate('2026-08-01')).toBe('2026-08-01')
    expect(venueClock(null)).toBe('')
    expect(venueDate(undefined)).toBe('')
  })
})
