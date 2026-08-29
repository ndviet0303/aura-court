import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import PaymentCycleDetailView from '../PaymentCycleDetailView.vue'
import { i18n } from '@/i18n'
import { apiV2 } from '@/core/api/client'

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({
    params: { id: '101' },
    path: '/recurring-booking/cycles/101',
  }),
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

describe('PaymentCycleDetailView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(apiV2.get).mockImplementation(async (url: string) => {
      if (url === '/monthly-invoices/101') {
        return {
          invoice: {
            id: 101,
            organization_id: 1,
            recurring_series_id: 10,
            period_start: '2026-11-01T00:00:00Z',
            period_end: '2026-11-30T23:59:59Z',
            due_at: '2026-10-25T00:00:00Z',
            amount: 1101600,
            status: 'pending',
            series: {
              id: 10,
              court_id: 1,
              frequency: 'weekly',
              status: 'active',
              court: {
                id: 1,
                name: 'VIP 01',
                venue: { id: 1, name: 'Aura Central', address: '123 Le Loi' },
              },
            },
          },
          occurrences: [
            {
              id: 1,
              recurring_series_id: 10,
              sequence: 1,
              starts_at: '2026-11-02T18:00:00Z',
              ends_at: '2026-11-02T20:00:00Z',
              amount: 300000,
              status: 'confirmed',
              court_name: 'VIP 01',
            },
            {
              id: 2,
              recurring_series_id: 10,
              sequence: 2,
              starts_at: '2026-11-09T18:00:00Z',
              ends_at: '2026-11-09T20:00:00Z',
              amount: 300000,
              status: 'confirmed',
              court_name: 'VIP 01',
            },
          ],
        }
      }

      if (url === '/recurring-series') {
        return {
          data: [],
        }
      }

      return {}
    })
  })

  it('renders statement details, sessions table, and summary correctly', async () => {
    const wrapper = mount(PaymentCycleDetailView, {
      global: {
        plugins: [i18n],
      },
    })

    await flushPromises()

    // Assert statement title or status
    expect(wrapper.text()).toContain('VIP 01')
    expect(wrapper.text()).toContain('Tóm Tắt Thanh Toán')
    expect(wrapper.text()).toContain('CHỜ THANH TOÁN')
    expect(wrapper.text()).toContain('Lịch Sử & Danh Sách Buổi Thi Đấu')
  })

  it('opens payment modal and confirms payment with apiV2', async () => {
    vi.mocked(apiV2.post).mockResolvedValueOnce({
      invoice: {
        id: 101,
        status: 'paid',
      },
    })

    const wrapper = mount(PaymentCycleDetailView, {
      global: {
        plugins: [i18n],
      },
    })

    await flushPromises()

    // Click pay button
    const payBtn = wrapper.find('.btn-action-primary')
    expect(payBtn.exists()).toBe(true)
    await payBtn.trigger('click')

    // Expect modal to appear
    expect(wrapper.find('.modal-payment').exists()).toBe(true)

    // Confirm pay
    const confirmBtn = wrapper.find('.btn-confirm-pay')
    await confirmBtn.trigger('click')
    await flushPromises()

    expect(apiV2.post).toHaveBeenCalledWith(
      '/monthly-invoices/101/pay',
      expect.objectContaining({
        payment_reference: expect.stringContaining('vietqr-101'),
      }),
    )
  })
})
