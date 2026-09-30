import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import { i18n } from '@/i18n'
import CourtStickyBookingCard from '@/features/court/components/detail/CourtStickyBookingCard.vue'
import VenueMapActionCard from '@/features/court/components/directions/VenueMapActionCard.vue'
import type { Court, Venue } from '@/features/court/types/court'

describe('Court Detail & Venue Directions V2 Adaptation', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  const mockVenue: Venue = {
    id: 1,
    name: 'Aura Court Central',
    slug: 'aura-court-central',
    address: '123 Nguyễn Thị Minh Khai, Q.1, TP.HCM',
    phone: '0901234567',
    google_maps_url: 'https://maps.google.com',
    is_active: true,
    status: 'active',
  }

  const mockCourt: Court = {
    id: 101,
    name: 'Sân 1 - Taraflex Pro',
    slug: 'san-1-taraflex-pro',
    venue_id: 1,
    venue: mockVenue,
    is_active: true,
  }
  beforeEach(() => {
    document.documentElement.removeAttribute('data-ui-version')
    document.documentElement.classList.remove('dark')
  })

  it('contains V2 court detail and directions styling rules in CSS with exact root selectors', () => {
    expect(erabookCss).toContain('html[data-ui-version="v2"] .sticky-booking-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .court-hero-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .court-specs-section')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .court-amenities-section')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .court-reviews-section')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .hero-main-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .map-action-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .transport-guide-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .venue-courts-section')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .duration-btn.active')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .btn-book-now')
  })

  it('renders CourtStickyBookingCard with duration options and emits duration selection', async () => {
    const wrapper = mount(CourtStickyBookingCard, {
      props: {
        court: mockCourt,
        basePrice: 150000,
        selectedDuration: 1,
        estimatedTotal: 150000,
        durationOptions: [
          { value: 1, label: '1 giờ' },
          { value: 1.5, label: '1.5 giờ' },
          { value: 2, label: '2 giờ' },
        ],
      },
      global: {
        plugins: [i18n],
        stubs: {
          RouterLink: {
            template: '<a :href="to"><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    expect(wrapper.find('.sticky-booking-card').exists()).toBe(true)
    const durationBtns = wrapper.findAll('.duration-btn')
    expect(durationBtns.length).toBe(3)

    // Click 2 hours option
    await durationBtns[2]?.trigger('click')
    const emitted = wrapper.emitted('update:selectedDuration')
    expect(emitted?.[0]).toEqual([2])
  })

  it('renders VenueMapActionCard with address and triggers actions', async () => {
    const wrapper = mount(VenueMapActionCard, {
      props: {
        venue: mockVenue,
        copied: false,
      },
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.find('.map-action-card').exists()).toBe(true)
    expect(wrapper.text()).toContain('123 Nguyễn Thị Minh Khai')

    const copyBtn = wrapper.find('.btn-secondary-action')
    expect(copyBtn.exists()).toBe(true)
    await copyBtn.trigger('click')
    expect(wrapper.emitted('copyAddress')).toBeTruthy()
  })

  it('handles venue without address gracefully', () => {
    const venueWithoutAddress: Venue = {
      ...mockVenue,
      address: null,
      google_maps_url: null,
    }
    const wrapper = mount(VenueMapActionCard, {
      props: {
        venue: venueWithoutAddress,
        copied: false,
      },
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.find('.map-action-card').exists()).toBe(true)
  })
})
