import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import { i18n } from '@/i18n'
import ScheduleDatePicker from '@/features/court/components/schedule/ScheduleDatePicker.vue'
import ScheduleSlotsGrid from '@/features/court/components/schedule/ScheduleSlotsGrid.vue'
import ScheduleBookingSummary from '@/features/court/components/schedule/ScheduleBookingSummary.vue'
import type { AvailabilitySlot, Court } from '@/features/court/types/court'

describe('Schedule View & Slots Grid V2 Adaptation', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  const mockVenue = {
    id: 1,
    name: 'Aura Court Central',
    slug: 'aura-court-central',
    address: '123 Đường Số 1',
    google_maps_url: 'https://maps.google.com',
    is_active: true,
  }

  const mockCourt: Court = {
    id: 101,
    name: 'Sân 1 - Taraflex Pro',
    slug: 'san-1-taraflex-pro',
    venue_id: 1,
    venue: mockVenue,
    is_active: true,
  }

  const mockSlots: AvailabilitySlot[] = [
    {
      startsAt: '2026-10-01T01:00:00Z', // 08:00 VN time
      endsAt: '2026-10-01T02:00:00Z',
      price: 150000,
      available: true,
    },
    {
      startsAt: '2026-10-01T02:00:00Z', // 09:00 VN time
      endsAt: '2026-10-01T03:00:00Z',
      price: 150000,
      available: false,
    },
    {
      startsAt: '2026-10-01T11:00:00Z', // 18:00 VN time (Peak hour)
      endsAt: '2026-10-01T12:00:00Z',
      price: 200000,
      available: true,
    },
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    document.documentElement.removeAttribute('data-ui-version')
    document.documentElement.classList.remove('dark')
  })

  it('contains V2 schedule styling rules in CSS', () => {
    expect(erabookCss).toContain('html[data-ui-version="v2"] .schedule-date-picker-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .court-selector-container')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .schedule-slots-section')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .schedule-summary-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .date-card-btn.active')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .slot-cell-btn.is-selected')
  })

  it('renders ScheduleDatePicker and emits selectDate on click', async () => {
    const days = [
      { iso: '2026-10-01', dayNum: 1, dayName: 'T5', isToday: true },
      { iso: '2026-10-02', dayNum: 2, dayName: 'T6', isToday: false },
    ]

    const wrapper = mount(ScheduleDatePicker, {
      props: {
        days,
        selectedDate: '2026-10-01',
      },
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.find('.schedule-date-picker-card').exists()).toBe(true)
    const dateBtns = wrapper.findAll('.date-card-btn')
    expect(dateBtns.length).toBe(2)
    expect(dateBtns[0]?.classes()).toContain('active')

    await dateBtns[1]?.trigger('click')
    expect(wrapper.emitted('selectDate')).toBeTruthy()
    const emitted = wrapper.emitted('selectDate')
    expect(emitted?.[0]).toEqual(['2026-10-02'])
  })

  it('renders ScheduleSlotsGrid with non-color-only cues for available, selected, and occupied slots', async () => {
    const selectedSlots = [mockSlots[0]!]

    const wrapper = mount(ScheduleSlotsGrid, {
      props: {
        slots: mockSlots,
        selectedSlots,
        timeFilter: 'all',
      },
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.find('.schedule-slots-section').exists()).toBe(true)
    const slotBtns = wrapper.findAll('.slot-cell-btn')
    expect(slotBtns.length).toBe(3)

    // Slot 0 is selected -> has check icon + 'Đã chọn' text cue
    expect(slotBtns[0]?.classes()).toContain('is-selected')
    expect(slotBtns[0]?.find('.selected-tag').exists()).toBe(true)
    expect(slotBtns[0]?.text()).toContain('Đã chọn')

    // Slot 1 is occupied -> has lock icon + 'Đã kín' text cue and is disabled
    expect(slotBtns[1]?.classes()).toContain('is-occupied')
    expect(slotBtns[1]?.find('.occupied-tag').exists()).toBe(true)
    expect(slotBtns[1]?.text()).toContain('Đã kín')
    expect(slotBtns[1]?.attributes('disabled')).toBeDefined()

    // Slot 2 is available peak -> has 'Giờ vàng' text cue
    expect(slotBtns[2]?.classes()).toContain('is-available')
    expect(slotBtns[2]?.text()).toContain('Giờ vàng')
  })

  it('renders ScheduleBookingSummary with court details, selected slots and total price', () => {
    const wrapper = mount(ScheduleBookingSummary, {
      props: {
        court: mockCourt,
        directOwnerPayments: false,
        date: '2026-10-01',
        selectedSlots: [mockSlots[0]!],
        totalPrice: 150000,
        loading: false,
        error: '',
        notice: '',
        resendingEmail: false,
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

    expect(wrapper.find('.schedule-summary-card').exists()).toBe(true)
    expect(wrapper.text()).toContain('Sân 1 - Taraflex Pro')
    expect(wrapper.find('.slot-summary-item').exists()).toBe(true)
    expect(wrapper.find('.btn-checkout').exists()).toBe(true)
  })
})
