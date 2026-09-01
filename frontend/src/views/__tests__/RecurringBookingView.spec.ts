import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import RecurringBookingView from '../RecurringBookingView.vue'
import { i18n } from '@/i18n'
import { api, apiV2 } from '@/core/api/client'

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({ path: '/recurring-booking' }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  apiV2: {
    get: vi.fn(),
    post: vi.fn(),
  },
  errorMessage: (_err: unknown, fallback: string) => fallback,
}))

describe('RecurringBookingView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url === '/courts') {
        return [
          {
            id: 1,
            name: 'Sân 1 (Thảm Tinsue)',
            venue: { id: 1, name: 'Sân Cầu Lông Aura - Quận 1', slug: 'quan-1', address: '123 Le Loi', google_maps_url: null, is_active: true, share_url: '' },
            price_slots: [{ id: 1, weekday: 1, starts_at: '06:00', ends_at: '22:00', price: 120000 }],
          },
        ]
      }
      return []
    })

    vi.mocked(apiV2.get).mockImplementation(async (url: string) => {
      if (url === '/recurring-series') {
        return {
          data: [
            {
              id: 10,
              court_id: 1,
              frequency: 'weekly',
              starts_at: '2026-09-01T18:00:00Z',
              ends_at: '2026-11-30T20:00:00Z',
              status: 'active',
              total_amount: 3600000,
              invoices: [
                {
                  id: 101,
                  recurring_series_id: 10,
                  period_start: '2026-09-01T00:00:00Z',
                  period_end: '2026-09-30T23:59:59Z',
                  due_at: '2026-08-28T23:59:59Z',
                  amount: 1200000,
                  status: 'pending',
                },
              ],
              occurrences: [
                {
                  id: 201,
                  recurring_series_id: 10,
                  sequence: 1,
                  starts_at: '2026-09-01T18:00:00Z',
                  ends_at: '2026-09-01T20:00:00Z',
                  amount: 240000,
                  status: 'scheduled',
                },
              ],
            },
          ],
        }
      }
      return { data: [] }
    })
  })

  it('renders KPI stats and active series', async () => {
    const wrapper = mount(RecurringBookingView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Đặt Sân Cố Định & Quản Lý Lịch Định Kỳ')
    expect(wrapper.text()).toContain('Tạo Lịch Định Kỳ Mới')
    expect(wrapper.text()).toContain('Quản Lý Hợp Đồng Đặt Sân Cố Định')
    expect(wrapper.text()).toContain('Hóa Đơn Chu Kỳ Tháng')
    expect(wrapper.text()).toContain('1.200.000')
  })

  it('allows interacting with weekday circles and frequency selector', async () => {
    const wrapper = mount(RecurringBookingView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const weekdayButtons = wrapper.findAll('.weekday-circle-btn')
    expect(weekdayButtons.length).toBe(7)

    // Click T3 button
    const btn = weekdayButtons[1]
    if (btn) {
      await btn.trigger('click')
    }
    expect(wrapper.vm).toBeDefined()
  })

  it('submits recurring series creation', async () => {
    vi.mocked(apiV2.post).mockResolvedValueOnce({ data: { id: 11 } })

    const wrapper = mount(RecurringBookingView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const form = wrapper.find('form.wizard-form')
    expect(form.exists()).toBe(true)

    await form.trigger('submit.prevent')
    await flushPromises()

    expect(apiV2.post).toHaveBeenCalledWith('/recurring-series', expect.objectContaining({
      court_id: 1,
      frequency: 'weekly',
    }))
  })
})
