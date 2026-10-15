import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import HomeView from '../views/HomeView.vue'
import { i18n, setAppLocale } from '@/i18n'
import { useBookingStore } from '@/stores/booking'
import { bookingApi } from '@/features/booking/api/bookingApi'
import type { Court } from '@/features/court/types/court'
import {
  getVenueLocalDate,
  formatSlotRange,
  resolveAvailabilityHeading,
} from '../utils/homeAvailability'
import DatePicker from '@/shared/components/forms/DatePicker.vue'

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({ path: '/', query: {} }),
  RouterLink: {
    template: '<a><slot /></a>',
  },
}))

describe('HomeAvailability (Phase 8: Tasks 8.1, 8.2, 8.3)', () => {
  const courtA = {
    id: 1,
    name: 'Court A',
    slug: 'court-a',
    description: 'Badminton Court A',
    price_slots: [{ id: 1, price: 120000, start_time: '19:00', end_time: '21:00' }],
  }
  const courtB = {
    id: 2,
    name: 'Court B',
    slug: 'court-b',
    description: 'Badminton Court B',
    price_slots: [{ id: 2, price: 120000, start_time: '19:00', end_time: '21:00' }],
  }

  const courtASlotsDate1 = [
    {
      startsAt: '2026-09-22T17:00:00+07:00',
      endsAt: '2026-09-22T18:00:00+07:00',
      price: 120000,
      available: true,
    },
    {
      startsAt: '2026-09-22T18:00:00+07:00',
      endsAt: '2026-09-22T19:00:00+07:00',
      price: 120000,
      available: true,
    },
    {
      startsAt: '2026-09-22T19:00:00+07:00',
      endsAt: '2026-09-22T20:00:00+07:00',
      price: 120000,
      available: false,
    }, // booked
    {
      startsAt: '2026-09-22T20:00:00+07:00',
      endsAt: '2026-09-22T21:00:00+07:00',
      price: 120000,
      available: false,
    }, // booked
    {
      startsAt: '2026-09-22T21:00:00+07:00',
      endsAt: '2026-09-22T22:00:00+07:00',
      price: 120000,
      available: true,
    },
  ]

  const courtBSlotsDate1 = [
    {
      startsAt: '2026-09-22T17:00:00+07:00',
      endsAt: '2026-09-22T18:00:00+07:00',
      price: 120000,
      available: true,
    },
    {
      startsAt: '2026-09-22T18:00:00+07:00',
      endsAt: '2026-09-22T19:00:00+07:00',
      price: 120000,
      available: true,
    },
    {
      startsAt: '2026-09-22T19:00:00+07:00',
      endsAt: '2026-09-22T20:00:00+07:00',
      price: 120000,
      available: true,
    }, // available
    {
      startsAt: '2026-09-22T20:00:00+07:00',
      endsAt: '2026-09-22T21:00:00+07:00',
      price: 120000,
      available: true,
    }, // available
    {
      startsAt: '2026-09-22T21:00:00+07:00',
      endsAt: '2026-09-22T22:00:00+07:00',
      price: 120000,
      available: true,
    },
  ]

  const courtASlotsDate2 = [
    {
      startsAt: '2026-09-25T19:00:00+07:00',
      endsAt: '2026-09-25T20:00:00+07:00',
      price: 140000,
      available: true,
    },
    {
      startsAt: '2026-09-25T20:00:00+07:00',
      endsAt: '2026-09-25T21:00:00+07:00',
      price: 140000,
      available: true,
    },
  ]

  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-09-22T08:00:00.000Z'))
    setActivePinia(createPinia())
    setAppLocale('vi')
    vi.clearAllMocks()

    vi.spyOn(bookingApi, 'getCourts').mockResolvedValue([
      courtA as unknown as Court,
      courtB as unknown as Court,
    ])
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('Task 8.1 & 8.3: Court A booked 19:00–21:00 disables exactly its two hourly slots (19:00–20:00 and 20:00–21:00) under dynamic heading', async () => {
    vi.spyOn(bookingApi, 'getAvailability').mockImplementation(async (courtId, date) => {
      if (courtId === 1 && date === '2026-09-22') return courtASlotsDate1
      return []
    })

    const wrapper = mount(HomeView, {
      global: { plugins: [i18n] },
    })

    await flushPromises()

    // Select Court A and Date 2026-09-22
    const chips = wrapper.findAll('.court-chip')
    expect(chips.length).toBeGreaterThanOrEqual(2)
    await chips[0]!.trigger('click')
    const datePicker = wrapper.findComponent(DatePicker)
    await datePicker.vm.$emit('update:modelValue', '2026-09-22')
    await datePicker.vm.$emit('change', '2026-09-22')
    await flushPromises()

    // 1. Dynamic Heading identifies Court A and date without misleading fixed "today"
    const heading = wrapper.find('.availability-title')
    expect(heading.exists()).toBe(true)
    expect(heading.text()).toContain('Court A')
    expect(wrapper.find('.availability-meta').text()).toContain('22/09/2026')
    expect(heading.text()).not.toContain('hôm nay')
    expect(heading.text()).not.toContain('Today')

    // 2. Slots rendered with start and end times
    const slots = wrapper.findAll('.slot-preview .slot')
    expect(slots.length).toBe(5)

    // Slot 1: 17:00 – 18:00 (available)
    expect(slots[0]!.text()).toContain('17:00 – 18:00')
    expect(slots[0]!.attributes('disabled')).toBeUndefined()

    // Slot 2: 18:00 – 19:00 (available)
    expect(slots[1]!.text()).toContain('18:00 – 19:00')
    expect(slots[1]!.attributes('disabled')).toBeUndefined()

    // Slot 3: 19:00 – 20:00 (booked -> disabled and unavailable)
    expect(slots[2]!.text()).toContain('19:00 – 20:00')
    expect(slots[2]!.attributes('disabled')).toBeDefined()
    expect(slots[2]!.classes()).toContain('unavailable')

    // Slot 4: 20:00 – 21:00 (booked -> disabled and unavailable)
    expect(slots[3]!.text()).toContain('20:00 – 21:00')
    expect(slots[3]!.attributes('disabled')).toBeDefined()
    expect(slots[3]!.classes()).toContain('unavailable')

    // Slot 5: 21:00 – 22:00 (available)
    expect(slots[4]!.text()).toContain('21:00 – 22:00')
    expect(slots[4]!.attributes('disabled')).toBeUndefined()
  })

  it('Task 8.1 & 8.3: Switching to Court B shows 19:00–20:00 and 20:00–21:00 available under heading identifying Court B', async () => {
    vi.spyOn(bookingApi, 'getAvailability').mockImplementation(async (courtId) => {
      if (courtId === 1) return courtASlotsDate1
      if (courtId === 2) return courtBSlotsDate1
      return []
    })

    const wrapper = mount(HomeView, {
      global: { plugins: [i18n] },
    })

    await flushPromises()

    // Start with Court A
    const chips = wrapper.findAll('.court-chip')
    await chips[0]!.trigger('click')
    await flushPromises()

    // Switch to Court B
    await chips[1]!.trigger('click')
    await flushPromises()

    // Heading now identifies Court B
    const heading = wrapper.find('.availability-title')
    expect(heading.text()).toContain('Court B')
    expect(heading.text()).not.toContain('Court A')

    // Slots 19:00–20:00 and 20:00–21:00 are now available on Court B
    const slots = wrapper.findAll('.slot-preview .slot')
    expect(slots.length).toBe(5)

    const slot19 = slots[2]!
    expect(slot19.text()).toContain('19:00 – 20:00')
    expect(slot19.attributes('disabled')).toBeUndefined()
    expect(slot19.classes()).not.toContain('unavailable')

    const slot20 = slots[3]!
    expect(slot20.text()).toContain('20:00 – 21:00')
    expect(slot20.attributes('disabled')).toBeUndefined()
    expect(slot20.classes()).not.toContain('unavailable')
  })

  it('Task 8.2 & 8.3: Selecting a different date renders heading and slots for that date without "today" label', async () => {
    vi.spyOn(bookingApi, 'getAvailability').mockImplementation(async (courtId, date) => {
      if (date === '2026-09-25') return courtASlotsDate2
      return courtASlotsDate1
    })

    const wrapper = mount(HomeView, {
      global: { plugins: [i18n] },
    })

    await flushPromises()

    const chips = wrapper.findAll('.court-chip')
    await chips[0]!.trigger('click')

    // Change date to 2026-09-25
    const datePicker = wrapper.findComponent(DatePicker)
    await datePicker.vm.$emit('update:modelValue', '2026-09-25')
    await datePicker.vm.$emit('change', '2026-09-25')
    await flushPromises()

    // Heading identifies 2026-09-25 and Court A
    const heading = wrapper.find('.availability-title')
    expect(heading.text()).toContain('Court A')
    expect(wrapper.find('.availability-meta').text()).toContain('25/09/2026')
    expect(heading.text()).not.toContain('hôm nay')
    expect(heading.text()).not.toContain('today')

    // Slots rendered for 2026-09-25
    const slots = wrapper.findAll('.slot-preview .slot')
    expect(slots.length).toBe(2)
    expect(slots[0]!.text()).toContain('19:00 – 20:00')
    expect(slots[1]!.text()).toContain('20:00 – 21:00')
  })

  it('Task 8.2 & 8.3: Out-of-order / rapid switching race conditions cannot overwrite newer selection', async () => {
    const { promise: courtAPromise, resolve: resolveCourtA } = (Promise as any).withResolvers()

    vi.spyOn(bookingApi, 'getAvailability').mockImplementation(async (courtId) => {
      if (courtId === 1) {
        // Slow response for Court A
        return courtAPromise
      }
      if (courtId === 2) {
        // Fast response for Court B
        return courtBSlotsDate1
      }
      return []
    })

    const wrapper = mount(HomeView, {
      global: { plugins: [i18n] },
    })
    await flushPromises()

    const chips = wrapper.findAll('.court-chip')

    // 1. Click Court A (request starts and hangs)
    await chips[0]!.trigger('click')

    // 2. Rapidly switch to Court B (request resolves quickly)
    await chips[1]!.trigger('click')
    await flushPromises()

    // Court B slots are visible
    expect(wrapper.find('.availability-title').text()).toContain('Court B')
    expect(wrapper.findAll('.slot-preview .slot').length).toBe(5)

    // 3. Now slow Court A response finally resolves
    resolveCourtA(courtASlotsDate1)
    await flushPromises()

    // Court B selection and title must NOT be overwritten by Court A's late response
    const headingAfterLateResolution = wrapper.find('.availability-title')
    expect(headingAfterLateResolution.text()).toContain('Court B')
    expect(headingAfterLateResolution.text()).not.toContain('Court A')

    // Slots must remain Court B's slots (all available)
    const slots = wrapper.findAll('.slot-preview .slot')
    expect(slots.length).toBe(5)
    expect(slots[2]!.classes()).not.toContain('unavailable')
  })
  it('Task 8.2: store.loadAvailability discards late responses at commit point and does not overwrite newer slots', async () => {
    const store = useBookingStore()
    const { promise: courtAPromise, resolve: resolveCourtA } = (Promise as any).withResolvers()

    vi.spyOn(bookingApi, 'getAvailability').mockImplementation(async (courtId) => {
      if (courtId === 1) return courtAPromise
      if (courtId === 2) return courtBSlotsDate1
      return []
    })

    // 1. Trigger load for Court A (slow response)
    const loadAPromise = store.loadAvailability(1, '2026-09-22')

    // 2. Trigger load for Court B (fast response)
    const loadBPromise = store.loadAvailability(2, '2026-09-22')
    await loadBPromise

    // Fast response for Court B finishes and commits Court B slots
    expect(store.selectedCourt).toBe(2)
    expect(store.slots).toEqual(courtBSlotsDate1)
    expect(store.slots[2]!.available).toBe(true)

    // 3. Slow response for Court A resolves later
    resolveCourtA(courtASlotsDate1)
    await loadAPromise

    // Verify it is DISCARDED at the commit point and does NOT overwrite Court B's slots in the store!
    expect(store.selectedCourt).toBe(2)
    expect(store.slots).toEqual(courtBSlotsDate1)
    expect(store.slots[2]!.available).toBe(true)
  })

  it('Task 8.1 & 8.3: Localization in both vi and en renders correct dynamic headings and labels', () => {
    // 1. Vietnamese locale
    setAppLocale('vi')
    expect(resolveAvailabilityHeading('Sân 1', '2026-09-22', 'vi')).toBe(
      'Lịch trống — Sân 1 — 2026-09-22',
    )
    expect(resolveAvailabilityHeading(null, '2026-09-22', 'vi')).toBe('Lịch sân trống trực quan')

    // 2. English locale
    setAppLocale('en')
    expect(resolveAvailabilityHeading('Court 1', '2026-09-22', 'en')).toBe(
      'Availability — Court 1 — 2026-09-22',
    )
    expect(resolveAvailabilityHeading(null, '2026-09-22', 'en')).toBe('Live Availability')

    // 3. Slot Range format
    const slot = {
      startsAt: '2026-09-22T19:00:00+07:00',
      endsAt: '2026-09-22T20:00:00+07:00',
      price: 120000,
      available: true,
    }
    expect(formatSlotRange(slot)).toBe('19:00 – 20:00')
  })

  it('Task 8.2 & 8.3: Timezone date boundary handling correctly computes venue-local play date', () => {
    // Late evening in UTC: 2026-09-22 18:30:00Z
    // In Asia/Ho_Chi_Minh (+07:00), this is 2026-09-23 01:30:00 AM (the NEXT day).
    const utcDate = new Date('2026-09-22T18:30:00.000Z')

    // Raw UTC slicing gives 2026-09-22 (wrong date for local venue)
    const utcSliced = utcDate.toISOString().slice(0, 10)
    expect(utcSliced).toBe('2026-09-22')

    // getVenueLocalDate gives the correct local venue date: 2026-09-23
    const localVenueDate = getVenueLocalDate(utcDate, 'Asia/Ho_Chi_Minh')
    expect(localVenueDate).toBe('2026-09-23')

    // Early morning UTC: 2026-09-22 02:00:00Z -> Asia/Ho_Chi_Minh 09:00:00 AM (same day)
    const utcDateMorning = new Date('2026-09-22T02:00:00.000Z')
    expect(getVenueLocalDate(utcDateMorning, 'Asia/Ho_Chi_Minh')).toBe('2026-09-22')
  })

  it('Task 8.1, 8.2 & 8.3: Non-HCM venue (Asia/Tokyo) renders venue-local date and slot ranges according to its timezone', async () => {
    const courtTokyo = {
      id: 3,
      name: 'Tokyo Court',
      slug: 'tokyo-court',
      timezone: 'Asia/Tokyo',
      venue: { id: 10, name: 'Tokyo Arena', timezone: 'Asia/Tokyo' },
    }
    vi.spyOn(bookingApi, 'getCourts').mockResolvedValue([courtTokyo as unknown as Court])
    const tokyoSlots = [
      {
        startsAt: '2026-09-22T10:00:00.000Z', // 19:00 in Tokyo (+9), 17:00 in HCM (+7)
        endsAt: '2026-09-22T11:00:00.000Z', // 20:00 in Tokyo (+9), 18:00 in HCM (+7)
        price: 150000,
        available: true,
      },
    ]
    vi.spyOn(bookingApi, 'getAvailability').mockResolvedValue(tokyoSlots)

    const wrapper = mount(HomeView, {
      global: { plugins: [i18n] },
    })
    await flushPromises()

    // Heading identifies Tokyo Court
    const heading = wrapper.find('.availability-title')
    expect(heading.text()).toContain('Tokyo Court')

    // Slot range renders in Tokyo timezone (19:00 – 20:00, NOT 17:00 – 18:00)
    const slot = wrapper.find('.slot-preview .slot')
    expect(slot.exists()).toBe(true)
    expect(slot.text()).toContain('19:00 – 20:00')
    expect(slot.text()).not.toContain('17:00 – 18:00')

    // Verify formatSlotRange directly with Tokyo timezone
    expect(formatSlotRange(tokyoSlots[0]!, 'en', 'Asia/Tokyo')).toBe('19:00 – 20:00')
    expect(formatSlotRange(tokyoSlots[0]!, 'en', 'Europe/London')).toBe('11:00 – 12:00')
  })

  it('Task 8.2: English locale renders localized English error message when availability fetch fails', async () => {
    setAppLocale('en')
    vi.spyOn(bookingApi, 'getAvailability').mockRejectedValue(new Error('Network failure'))

    const wrapper = mount(HomeView, {
      global: { plugins: [i18n] },
    })
    await flushPromises()

    const errorEl = wrapper.find('.error')
    expect(errorEl.exists()).toBe(true)
    expect(errorEl.text()).toBe('Failed to load court availability. Please try again.')
    expect(errorEl.text()).not.toContain('Không thể')
  })

  it('Task 8.2 & 8.3: getVenueLocalDate handles UTC and GMT without throwing RangeError', () => {
    expect(() => getVenueLocalDate('UTC')).not.toThrow()
    expect(() => getVenueLocalDate(new Date(), 'UTC')).not.toThrow()
    expect(getVenueLocalDate('UTC')).toBe('2026-09-22')
    expect(getVenueLocalDate(new Date(), 'UTC')).toBe('2026-09-22')
    expect(getVenueLocalDate('GMT')).toBe('2026-09-22')
    expect(getVenueLocalDate(new Date(), 'GMT')).toBe('2026-09-22')
  })
})
