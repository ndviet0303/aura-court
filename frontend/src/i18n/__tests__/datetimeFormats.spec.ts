import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest'
import { i18n, setAppLocale } from '../index'

// 23:30 UTC on Oct 11 is 06:30 on Oct 12 in Vietnam.
const instant = new Date('2026-10-11T23:30:00Z')
// ICU versions differ on the space before AM/PM; compare plain spaces.
const format = (key: 'date' | 'time' | 'datetime') =>
  i18n.global.d(instant, key).replace(/\s/g, ' ')

describe('datetimeFormats', () => {
  // The browser's own zone and locale must not leak into $d / d().
  beforeAll(() => {
    vi.stubEnv('TZ', 'America/Los_Angeles')
  })

  afterAll(() => {
    vi.unstubAllEnvs()
    setAppLocale('vi')
  })

  it('formats Vietnamese dates on the venue clock', () => {
    setAppLocale('vi')
    expect(format('date')).toBe('12/10/2026')
    expect(format('time')).toBe('06:30')
    expect(format('datetime')).toBe('06:30 12/10/2026')
  })

  it('formats English dates on the venue clock', () => {
    setAppLocale('en')
    expect(format('date')).toBe('10/12/2026')
    expect(format('time')).toBe('06:30 AM')
    expect(format('datetime')).toBe('10/12/2026, 06:30 AM')
  })
})
