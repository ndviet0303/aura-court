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
// All-day weekly rules apply to every cell; priority favours the wider scope to prove rank wins.
const rule = (id: number, price: number, scope: Partial<PriceRuleItem>): PriceRuleItem => ({
  id,
  type: 'weekly',
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
function firstCellPrice(shown: Court, rules: PriceRuleItem[]) {
  const wrapper = mount(PricingCalendarGrid, {
    props: { rules, courts: [shown], venues: [venue] },
    global: { plugins: [i18n] },
  })
  return wrapper.get('.slot-rate').text()
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
