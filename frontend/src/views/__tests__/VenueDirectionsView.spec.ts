import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import VenueDirectionsView from '@/features/court/views/VenueDirectionsView.vue'
import { i18n } from '@/i18n'
import { useBookingStore } from '@/stores/booking'

let mockSlug = 'san-4-vip-cau-giay'

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: { slug: mockSlug },
    query: {},
  }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

describe('VenueDirectionsView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    mockSlug = 'san-4-vip-cau-giay'
  })

  const mockCourt = {
    id: 4,
    name: 'Sân 4 - VIP Cầu Giấy',
    slug: 'san-4-vip-cau-giay',
    venue_id: 1,
    venue: {
      id: 1,
      name: 'Aura Court Cầu Giấy',
      slug: 'aura-court-cau-giay',
      address: 'Số 12 Dịch Vọng Hậu, Cầu Giấy, Hà Nội',
      google_maps_url: 'https://maps.google.com/?q=Aura+Court+Cau+Giay',
      is_active: true,
      share_url: '',
    },
    sport_zone: {
      id: 1,
      venue_id: 1,
      name: 'Thảm BWF VIP',
      sport_type: 'badminton' as const,
      slot_duration: 60,
      is_active: true,
    },
  }

  it('resolves venue and renders hero, map action card, transport guide, and courts', async () => {
    const store = useBookingStore()
    store.courts = [mockCourt]

    const wrapper = mount(VenueDirectionsView, {
      global: {
        plugins: [i18n],
      },
    })

    await wrapper.vm.$nextTick()

    expect(wrapper.find('.venue-title').text()).toContain('Aura Court Cầu Giấy')
    expect(wrapper.find('.venue-subtitle').text()).toContain('Số 12 Dịch Vọng Hậu')
    expect(wrapper.find('.map-action-card').exists()).toBe(true)
    expect(wrapper.find('.transport-guide-card').exists()).toBe(true)
    expect(wrapper.find('.venue-courts-section').exists()).toBe(true)
    expect(wrapper.find('.court-name').text()).toContain('Sân 4 - VIP Cầu Giấy')
  })

  it('handles copying address', async () => {
    const writeTextSpy = vi.fn().mockResolvedValue(undefined)
    Object.assign(navigator, {
      clipboard: {
        writeText: writeTextSpy,
      },
    })

    const store = useBookingStore()
    store.courts = [mockCourt]

    const wrapper = mount(VenueDirectionsView, {
      global: {
        plugins: [i18n],
      },
    })

    await wrapper.vm.$nextTick()

    const copyBtn = wrapper.find('.btn-secondary-action')
    expect(copyBtn.exists()).toBe(true)
    await copyBtn.trigger('click')

    expect(writeTextSpy).toHaveBeenCalledWith('Số 12 Dịch Vọng Hậu, Cầu Giấy, Hà Nội')
  })
})
