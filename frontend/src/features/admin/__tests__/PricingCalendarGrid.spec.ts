import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { formatCurrency, i18n } from '@/i18n'
import type { Court, SportType, Venue } from '@/features/court/types/court'
import type { PriceRuleItem } from '../types/pricing'
import PricingCalendarGrid from '../components/pricing/PricingCalendarGrid.vue'

const venue: Venue = {
  id: 1,
  name: 'Venue 1',
  slug: 'venue-1',
  address: null,
  google_maps_url: null,
  is_active: true,
}
const court = (id: number, sport_type: SportType): Court => ({
  id,
  name: `Court ${id}`,
  venue_id: 1,
  venue,
  sport_type,
  slot_duration: 60,
})
// All-day Monday rules price the first cell; priority favours the wider scope to prove rank wins.
const rule = (id: number, price: number, scope: Partial<PriceRuleItem>): PriceRuleItem => ({
  id,
  type: 'weekly',
  weekday: 1,
  price,
  priority: 10 - id,
  is_active: true,
  version: 1,
  ...scope,
})
const venueRule = rule(1, 100000, { venue_id: 1 })
const sportRule = rule(2, 200000, { venue_id: 1, sport_type: 'pickleball' })
const courtRule = rule(3, 300000, { venue_id: 1, court_id: 12 })

// The grid shows the first court until another is picked.
function mountGrid(shown: Court, rules: PriceRuleItem[]) {
  return mount(PricingCalendarGrid, {
    props: { rules, courts: [shown], venues: [venue] },
    global: { plugins: [i18n] },
  })
}
function firstCellPrice(shown: Court, rules: PriceRuleItem[]) {
  return mountGrid(shown, rules).get('.slot-rate').text()
}
// One row per day, Monday first, one entry per time block from 06:00.
function weekRates(shown: Court, rules: PriceRuleItem[]) {
  return mountGrid(shown, rules)
    .findAll('.day-column')
    .map((day) => day.findAll('.slot-rate').map((cell) => cell.text()))
}

describe('PricingCalendarGrid rule ranking', () => {
  it('prefers a venue sport rule over a venue rule for a court of that sport', () => {
    expect(firstCellPrice(court(12, 'pickleball'), [venueRule, sportRule])).toBe(
      formatCurrency(200000),
    )
  })

  it('does not apply a venue sport rule to a court of another sport', () => {
    expect(firstCellPrice(court(11, 'badminton'), [venueRule, sportRule])).toBe(
      formatCurrency(100000),
    )
  })

  it('prefers a court rule over both', () => {
    expect(firstCellPrice(court(12, 'pickleball'), [venueRule, sportRule, courtRule])).toBe(
      formatCurrency(300000),
    )
  })
})

describe('PricingCalendarGrid unpriced slots', () => {
  // The quote rejects an unmatched slot as 'chưa mở bán', so the grid must not invent a price.
  it('shows Chưa có giá and no money when no rule matches', () => {
    const wrapper = mount(PricingCalendarGrid, {
      props: { rules: [], courts: [court(12, 'pickleball')], venues: [venue] },
      global: { plugins: [i18n] },
    })
    const rates = wrapper.findAll('.slot-rate').map((cell) => cell.text())

    expect(rates).toHaveLength(42)
    expect(new Set(rates)).toEqual(new Set(['Chưa có giá']))
    expect(wrapper.text()).not.toMatch(/\d[\d.,\s]*₫/)
  })

  it('prices an otherwise unmatched slot from a configured base rule', () => {
    const base: PriceRuleItem = { ...rule(4, 90000, { venue_id: 1 }), type: 'base' }
    expect(firstCellPrice(court(12, 'pickleball'), [base])).toBe(formatCurrency(90000))
  })

  // Hourly court price rows outrank base in the quote, so the base amount would be wrong here.
  it('points to the hourly table instead of the base price when the court has hourly rows', () => {
    const base: PriceRuleItem = { ...rule(4, 240000, { court_id: 12 }), type: 'base' }
    const wrapper = mount(PricingCalendarGrid, {
      props: {
        rules: [base],
        courts: [{ ...court(12, 'pickleball'), price_slots_count: 119 }],
        venues: [venue],
      },
      global: { plugins: [i18n] },
    })
    const rates = wrapper.findAll('.slot-rate').map((cell) => cell.text())

    expect(new Set(rates)).toEqual(new Set(['Theo bảng giá giờ']))
    expect(wrapper.text()).not.toMatch(/\d[\d.,\s]*₫/)
  })

  it('still shows a weekly rule over hourly rows, as the quote does', () => {
    const hourlyCourt = { ...court(12, 'pickleball'), price_slots_count: 119 }
    expect(firstCellPrice(hourlyCourt, [courtRule])).toBe(formatCurrency(300000))
  })

  it('lets hourly rows outrank venue and sport weekly rules, as the quote does', () => {
    const hourlyCourt = { ...court(12, 'pickleball'), price_slots_count: 119 }
    expect(firstCellPrice(hourlyCourt, [venueRule, sportRule])).toBe('Theo bảng giá giờ')
  })

  it('ignores hourly rows on a court whose slots are not an hour long', () => {
    const longCourt = { ...court(12, 'pickleball'), slot_duration: 90, price_slots_count: 119 }
    expect(firstCellPrice(longCourt, [venueRule])).toBe(formatCurrency(100000))
  })

  it('uses loaded price rows only for slots of their own length, as the quote does', () => {
    const twoHours = { weekday: 1, starts_at: '06:00:00', ends_at: '08:00:00', price: 200000 }
    const twoHourCourt = { ...court(12, 'pickleball'), slot_duration: 120, price_slots: [twoHours] }

    expect(firstCellPrice(twoHourCourt, [venueRule])).toBe('Theo bảng giá giờ')
    expect(firstCellPrice({ ...twoHourCourt, slot_duration: 90 }, [venueRule])).toBe(
      formatCurrency(100000),
    )
  })

  // Older data holds weekly rules like this; the quote matches weekly rules by weekday only.
  it('shows no price for a weekly rule without a weekday', () => {
    const anyDay = rule(5, 120000, {
      venue_id: 1,
      weekday: null,
      starts_at: '06:00',
      ends_at: '11:00',
    })
    const rates = weekRates(court(12, 'pickleball'), [anyDay]).flat()

    expect(new Set(rates)).toEqual(new Set(['Chưa có giá']))
  })

  it('shows no price for surge or special_event rules, which the quote never uses', () => {
    const surge = rule(6, 90000, {
      venue_id: 1,
      type: 'surge',
      starts_at: '11:00',
      ends_at: '14:00',
    })
    const event = rule(7, 150000, { venue_id: 1, type: 'special_event', exception_date: null })
    const rates = weekRates(court(12, 'pickleball'), [surge, event]).flat()

    expect(new Set(rates)).toEqual(new Set(['Chưa có giá']))
  })

  it('applies a peak rule without hours to the whole day, as the quote does', () => {
    const allDayPeak = rule(8, 180000, { venue_id: 1, type: 'peak', weekday: null })
    const rates = weekRates(court(12, 'pickleball'), [allDayPeak]).flat()

    expect(new Set(rates)).toEqual(new Set([formatCurrency(180000)]))
  })

  // The API returns times as HH:mm:ss, so a rule starting on a block boundary must still match.
  it('matches rule hours sent as HH:mm:ss', () => {
    const evening = rule(9, 180000, {
      venue_id: 1,
      type: 'peak',
      starts_at: '17:00:00',
      ends_at: '21:00:00',
    })
    const [monday] = weekRates(court(12, 'pickleball'), [evening])

    expect(monday?.[4]).toBe(formatCurrency(180000))
    expect(monday?.[3]).toBe('Chưa có giá')
  })

  it("does not price a court with another organization's base rule", () => {
    const otherVenue: Venue = { ...venue, id: 2, organization_id: 2 }
    const otherCourt: Court = { ...court(21, 'pickleball'), venue_id: 2, venue: otherVenue }
    const orgWideBase = rule(10, 100000, { organization_id: 1, type: 'base', venue_id: null })
    const rates = weekRates(otherCourt, [orgWideBase]).flat()

    expect(new Set(rates)).toEqual(new Set(['Chưa có giá']))
  })

  it('points to the hourly table only for blocks that loaded hourly rows cover', () => {
    const base = rule(11, 90000, { venue_id: 1, type: 'base' })
    const mondayMorning = { weekday: 1, starts_at: '07:00:00', ends_at: '08:00:00', price: 150000 }
    const hourlyCourt: Court = { ...court(12, 'pickleball'), price_slots: [mondayMorning] }
    const week = weekRates(hourlyCourt, [base])

    expect(week[0]?.slice(0, 2)).toEqual(['Theo bảng giá giờ', formatCurrency(90000)])
    expect(week[6]?.[0]).toBe(formatCurrency(90000))
  })
})
