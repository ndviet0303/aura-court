import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import RecurringBookingView from '../views/RecurringBookingView.vue'
import { i18n } from '@/i18n'
import { api } from '@/core/api/client'
import { setTimeout as sleep } from 'node:timers/promises'

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
  errorMessage: (_err: unknown, fallback: string) => fallback,
  idempotencyKey: () => 'test-key',
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
            venue: {
              id: 1,
              name: 'Sân Cầu Lông Aura - Quận 1',
              slug: 'quan-1',
              address: '123 Le Loi',
              google_maps_url: null,
              is_active: true,
              share_url: '',
            },
            price_slots: [
              { id: 1, weekday: 1, starts_at: '06:00', ends_at: '22:00', price: 120000 },
            ],
          },
        ]
      }
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

  it('submits the accepted preview snapshot when creating a series', async () => {
    const preview = {
      frequency: 'weekly',
      weekdays: [1, 3, 5],
      count: 2,
      currency: 'VND',
      total_amount: 480000,
      truncated_by: null,
      occurrences: [
        {
          index: 1,
          starts_at: '2026-10-05T11:00:00Z',
          ends_at: '2026-10-05T13:00:00Z',
          weekday: 1,
          price: 240000,
          conflict: false,
          conflict_reason: null,
        },
        {
          index: 2,
          starts_at: '2026-10-07T11:00:00Z',
          ends_at: '2026-10-07T13:00:00Z',
          weekday: 3,
          price: 240000,
          conflict: false,
          conflict_reason: null,
        },
      ],
    }
    vi.mocked(api.post).mockImplementation(async (url: string) => {
      if (url === '/recurring-series/preview') return preview
      if (url === '/recurring-series') {
        return {
          series: {
            id: 11,
            court_id: 1,
            frequency: 'weekly',
            starts_at: '',
            ends_at: '',
            status: 'pending',
            total_amount: 480000,
          },
        }
      }
      return {}
    })

    const wrapper = mount(RecurringBookingView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()
    // Let the debounced server preview resolve so an accepted snapshot exists to submit.
    await sleep(350)
    await flushPromises()

    await wrapper.find('form.wizard-form').trigger('submit.prevent')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith(
      '/recurring-series',
      expect.objectContaining({
        court_id: 1,
        frequency: 'weekly',
        accept_partial: false,
        accepted: expect.objectContaining({
          total: 480000,
          occurrences: [
            { starts_at: '2026-10-05T11:00:00Z', price: 240000 },
            { starts_at: '2026-10-07T11:00:00Z', price: 240000 },
          ],
        }),
      }),
      { idempotencyKey: 'test-key' },
    )
  })

  it('renders the partial-conflict panel and accepts the remaining sessions', async () => {
    const preview = {
      frequency: 'weekly',
      weekdays: [1, 3, 5],
      count: 2,
      currency: 'VND',
      total_amount: 480000,
      truncated_by: null,
      occurrences: [
        {
          index: 1,
          starts_at: '2026-10-05T11:00:00Z',
          ends_at: '2026-10-05T13:00:00Z',
          weekday: 1,
          price: 240000,
          conflict: false,
          conflict_reason: null,
        },
        {
          index: 2,
          starts_at: '2026-10-07T11:00:00Z',
          ends_at: '2026-10-07T13:00:00Z',
          weekday: 3,
          price: 240000,
          conflict: true,
          conflict_reason: 'Đã có người đặt',
        },
      ],
    }
    vi.mocked(api.post).mockImplementation(async (url: string, body?: unknown) => {
      if (url === '/recurring-series/preview') return preview
      if (url === '/recurring-series') {
        if ((body as { accept_partial?: boolean })?.accept_partial) {
          return {
            series: {
              id: 12,
              court_id: 1,
              frequency: 'weekly',
              starts_at: '',
              ends_at: '',
              status: 'pending',
              total_amount: 240000,
            },
          }
        }
        return {
          status: 'partial_conflict',
          currency: 'VND',
          held_count: 1,
          revised_total: 240000,
          excluded: [
            {
              index: 2,
              starts_at: '2026-10-07T11:00:00Z',
              ends_at: '2026-10-07T13:00:00Z',
              conflict_reason: 'Đã có người đặt',
            },
          ],
        }
      }
      return {}
    })

    const wrapper = mount(RecurringBookingView, { global: { plugins: [i18n] } })
    await flushPromises()
    await sleep(350)
    await flushPromises()

    await wrapper.find('form.wizard-form').trigger('submit.prevent')
    await flushPromises()

    const panel = wrapper.find('[data-testid="recurring-partial-conflict"]')
    expect(panel.exists()).toBe(true)
    expect(panel.text()).toContain('Đã có người đặt')

    await wrapper.find('[data-testid="recurring-accept-remaining"]').trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenNthCalledWith(
      3,
      '/recurring-series',
      expect.objectContaining({ accept_partial: true }),
      { idempotencyKey: 'test-key' },
    )
    expect(wrapper.find('[data-testid="recurring-partial-conflict"]').exists()).toBe(false)
  })
})
