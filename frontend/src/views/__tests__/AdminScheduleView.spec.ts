import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminScheduleView from '../AdminScheduleView.vue'
import { i18n } from '@/i18n'
import { api } from '@/lib/api'

vi.mock('vue-router', () => ({
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
  errorMessage: (err: unknown, fallback: string) => fallback,
}))

describe('AdminScheduleView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url === '/admin/courts') {
        return [
          {
            id: 1,
            name: 'Sân 01 - Tiêu Chuẩn',
            venue: { id: 1, name: 'Aura Downtown' },
            blocks: [
              { id: 101, court_id: 1, starts_at: '2026-10-12T08:00', ends_at: '2026-10-14T17:00', reason: 'Court 4 Resurfacing' },
            ],
          },
          {
            id: 2,
            name: 'Sân 02 - VIP Match',
            venue: { id: 2, name: 'Aura Westside' },
            blocks: [],
          },
        ]
      }
      if (url === '/admin/venues') {
        return [
          { id: 1, name: 'Aura Downtown' },
          { id: 2, name: 'Aura Westside' },
        ]
      }
      if (url === '/admin/audit-logs') {
        return { data: [{ id: 1, action: 'Cập nhật lịch', user: { name: 'Admin' }, created_at: new Date().toISOString() }] }
      }
      return []
    })
  })

  it('renders standard weekly schedule and special dates panel', async () => {
    const wrapper = mount(AdminScheduleView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.find('.page-title').text()).toContain('Lịch hoạt động')
    expect(wrapper.find('.weekly-schedule-card').exists()).toBe(true)
    expect(wrapper.findAll('.day-schedule-row').length).toBe(7)
    expect(wrapper.find('.special-dates-panel').exists()).toBe(true)
    expect(wrapper.find('.maintenance-panel').exists()).toBe(true)
  })

  it('allows adding shifts to a weekday', async () => {
    const wrapper = mount(AdminScheduleView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const firstDay = wrapper.findAll('.day-schedule-row')[0]
    expect(firstDay).toBeDefined()
    if (firstDay) {
      expect(firstDay.findAll('.shift-time-block').length).toBe(1)
      const addShiftBtn = firstDay.find('.btn-add-shift-link')
      await addShiftBtn.trigger('click')
      expect(firstDay.findAll('.shift-time-block').length).toBe(2)
    }
  })

  it('opens and closes the Schedule Downtime modal', async () => {
    const wrapper = mount(AdminScheduleView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.find('.modal-backdrop').exists()).toBe(false)
    const openDowntimeBtn = wrapper.find('.btn-schedule-downtime')
    await openDowntimeBtn.trigger('click')

    expect(wrapper.find('.modal-backdrop').exists()).toBe(true)
    expect(wrapper.find('.modal-card h3').text()).toContain('Lên lịch bảo trì')

    const closeBtn = wrapper.find('.btn-close')
    await closeBtn.trigger('click')
    expect(wrapper.find('.modal-backdrop').exists()).toBe(false)
  })
})
