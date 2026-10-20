import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { i18n } from '@/i18n'
import type { Court, Venue } from '@/features/court/types/court'
import PricingSimulatorModal from '../components/pricing/PricingSimulatorModal.vue'

const venue: Venue = {
  id: 1,
  name: 'Venue 1',
  slug: 'venue-1',
  address: null,
  google_maps_url: null,
  is_active: true,
}
const court: Court = {
  id: 12,
  name: 'Court 12',
  venue_id: 1,
  venue,
  sport_type: 'pickleball',
  slot_duration: 60,
}

function mountModal(quoteError?: string) {
  return mount(PricingSimulatorModal, {
    props: { show: true, courts: [court], rules: [], quoteResult: null, quoteError },
    global: {
      plugins: [i18n],
      stubs: { AuraButton: true, DatePicker: true, SelectDropdown: true, TimePicker: true },
    },
  })
}

// Only the server quote may show an amount; spec forbids an invented fallback price.
describe('PricingSimulatorModal without a server quote', () => {
  it('shows no amount before a quote is calculated', () => {
    expect(mountModal().text()).not.toMatch(/\d[\d.,\s]*₫/)
  })

  it('shows only the error when the quote fails as unsold', () => {
    const text = mountModal('Khung giờ 25/10 18:00 chưa mở bán.').text()

    expect(text).toContain('Khung giờ 25/10 18:00 chưa mở bán.')
    expect(text).not.toMatch(/\d[\d.,\s]*₫/)
  })
})
