import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest'
import { venueClock, venueClockRange, venueDate } from '../venueTime'

describe('venue time helpers', () => {
  // A browser far from Vietnam must still read the venue clock.
  beforeAll(() => {
    vi.stubEnv('TZ', 'America/Los_Angeles')
  })

  afterAll(() => {
    vi.unstubAllEnvs()
  })

  it('runs these checks outside the venue time zone', () => {
    expect(new Date('2026-10-12T03:00:00Z').getHours()).toBe(20)
  })

  it('converts UTC API datetimes to the venue clock', () => {
    // Laravel serializes model datetimes as UTC with microseconds.
    expect(venueClock('2026-10-12T03:00:00.000000Z')).toBe('10:00')
    expect(venueClockRange('2026-10-12T03:00:00.000000Z', '2026-10-12T04:30:00.000000Z')).toBe(
      '10:00 - 11:30',
    )
    expect(venueClock(new Date('2026-10-12T03:00:00Z'))).toBe('10:00')
  })

  it('reads datetimes carrying the venue offset as-is', () => {
    expect(venueClock('2026-10-12T10:00:00+07:00')).toBe('10:00')
    expect(venueDate('2026-10-12T10:00:00+07:00')).toBe('2026-10-12')
  })

  it('moves early-morning slots onto the venue date', () => {
    // 23:30 UTC on Oct 11 is 06:30 on Oct 12 in Vietnam.
    expect(venueClock('2026-10-11T23:30:00Z')).toBe('06:30')
    expect(venueDate('2026-10-11T23:30:00Z')).toBe('2026-10-12')
    // 17:00 UTC on Oct 31 is midnight on Nov 1 in Vietnam (00:00, not 24:00).
    expect(venueClock('2026-10-31T17:00:00.000000Z')).toBe('00:00')
    expect(venueDate('2026-10-31T17:00:00.000000Z')).toBe('2026-11-01')
  })

  it('keeps offset-less datetimes as venue wall-clock time', () => {
    expect(venueClock('2026-10-12 10:00:00')).toBe('10:00')
    expect(venueClock('2026-10-12T10:00:00')).toBe('10:00')
    expect(venueDate('2026-10-12 10:00:00')).toBe('2026-10-12')
    expect(venueDate('2026-10-12')).toBe('2026-10-12')
  })

  it('returns an empty string for missing or invalid values', () => {
    expect(venueClock(null)).toBe('')
    expect(venueClock(undefined)).toBe('')
    expect(venueDate('')).toBe('')
    expect(venueClock('not-a-dateZ')).toBe('')
    expect(venueClockRange('2026-10-12T03:00:00Z', undefined)).toBe('10:00 - ')
  })
})
