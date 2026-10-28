import { describe, expect, it } from 'vitest'
import { monthDays, pickedDays, validTimes, weekdayDays } from '../recurringDates'

describe('recurring booking days', () => {
  it('BR-163 lists the chosen weekdays of one month only', () => {
    // November 2026: Tuesdays 3, 10, 17, 24 and Thursdays 5, 12, 19, 26.
    expect(weekdayDays('2026-11', [2, 4], '2026-11-01').dates).toEqual([
      '2026-11-03',
      '2026-11-05',
      '2026-11-10',
      '2026-11-12',
      '2026-11-17',
      '2026-11-19',
      '2026-11-24',
      '2026-11-26',
    ])
  })

  it('BR-164, BR-166 skip a day a month lacks and show it, without moving it', () => {
    const plan = monthDays('2026-10', '2026-12', [31, 15], '2026-10-01')
    expect(plan.dates).toEqual([
      '2026-10-15',
      '2026-10-31',
      '2026-11-15',
      '2026-12-15',
      '2026-12-31',
    ])
    expect(plan.skipped).toEqual([{ month: '2026-11', day: 31 }])
    expect(monthDays('2026-12', '2026-11', [1], '2026-10-01').dates).toEqual([])
  })

  it('BR-165, BR-167 keep exactly the picked days, once each, earliest first, none past', () => {
    expect(
      pickedDays(['2026-11-20', '2026-11-03', '2026-11-20', '2026-10-30'], '2026-11-02').dates,
    ).toEqual(['2026-11-03', '2026-11-20'])
  })

  it('BR-168 takes only the 30-minute grid with the end after the start', () => {
    expect(validTimes('18:00', '19:30')).toBe(true)
    expect(validTimes('18:15', '19:00')).toBe(false)
    expect(validTimes('19:00', '19:00')).toBe(false)
    expect(validTimes('20:00', '18:00')).toBe(false)
  })
})
