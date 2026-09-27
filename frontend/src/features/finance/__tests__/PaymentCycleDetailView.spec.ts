import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import PaymentCycleDetailView from '../views/PaymentCycleDetailView.vue'
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


  it('does not offer a bank account or paid status when no verified attempt is available', async () => {
    const wrapper = mount(PaymentCycleDetailView, { global: { plugins: [i18n] } })
    await flushPromises()

    await wrapper.find('.btn-action-primary').trigger('click')
    const modal = wrapper.find('.modal-payment')
    expect(modal.text()).toContain('Chưa có hướng dẫn chuyển khoản hợp lệ')
    expect(modal.find('img').exists()).toBe(false)
    expect(modal.find('.btn-confirm-pay').exists()).toBe(false)
    expect(wrapper.text()).toContain('CHỜ THANH TOÁN')
    expect(apiV2.post).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('shows a guaranteed period as unpaid without inventing payment instructions', async () => {
    vi.mocked(apiV2.get).mockResolvedValue({
      invoice: {
        id: 101,
        organization_id: 1,
        recurring_series_id: 10,
        period_start: '2026-11-01',
        period_end: '2026-11-30',
        due_at: '2026-10-25',
        amount: 150000,
        status: 'guaranteed',
        paid_at: null,
        series: { id: 10, court_id: 1, frequency: 'monthly', status: 'confirmed' },
      },
      occurrences: [],
      payment_attempt: null,
    })

    const wrapper = mount(PaymentCycleDetailView, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.text()).toContain('ĐƯỢC BẢO LÃNH (CHƯA THANH TOÁN)')
    await wrapper.find('.btn-action-primary').trigger('click')
    expect(wrapper.find('.modal-payment').text()).toContain('chưa được xác minh đã thanh toán')
    expect(wrapper.find('.modal-payment img').exists()).toBe(false)
    expect(apiV2.post).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('distinguishes a confirmed zero-payable period from a receipt-paid invoice', async () => {
    vi.mocked(apiV2.get).mockResolvedValue({
      invoice: {
        id: 101,
        organization_id: 1,
        recurring_series_id: 10,
        period_start: '2026-11-01',
        period_end: '2026-11-30',
        due_at: '2026-10-25',
        amount: 0,
        status: 'zero_payable',
        paid_at: null,
        series: { id: 10, court_id: 1, frequency: 'monthly', status: 'confirmed' },
      },
      occurrences: [],
      payment_attempt: null,
    })

    const wrapper = mount(PaymentCycleDetailView, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.text()).toContain('KHÔNG CẦN THANH TOÁN (0 ₫)')
    expect(wrapper.text()).toContain('Kỳ này không phát sinh tiền phải chuyển')
    expect(wrapper.text()).not.toContain('Kỳ thanh toán đã được quyết toán thành công')
    expect(wrapper.find('.btn-action-primary').exists()).toBe(false)
    wrapper.unmount()
  })

  it('renders the direct-owner payment attempt and confirms via server refresh, not a client reference', async () => {
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
            series: { id: 10, court_id: 1, frequency: 'weekly', status: 'active', court: { id: 1, name: 'VIP 01' } },
          },
          occurrences: [],
          payment_attempt: {
            id: 5,
            provider: 'sepay',
            reference: 'REC-101-ABCDEF',
            transfer_content: 'REC-101-ABCDEF',
            amount: 1101600,
            currency: 'VND',
            status: 'pending',
            expires_at: '2026-10-25T00:00:00Z',
            paid_at: null,
            receiver: { bank_code: 'MB', account_name: 'AURA COURT', account_number: '0123456789' },
            qr_url: 'https://qr.example/rec-101',
          },
        }
      }
      if (url === '/recurring-series') return { data: [] }
      return {}
    })

    const wrapper = mount(PaymentCycleDetailView, { global: { plugins: [i18n] } })
    await flushPromises()

    await wrapper.find('.btn-action-primary').trigger('click')
    expect(wrapper.find('.modal-payment').exists()).toBe(true)

    // Direct-owner path: the verified-receipt panel replaces the client "confirm transfer" button.
    expect(wrapper.find('[data-testid="owner-payment-instructions"]').exists()).toBe(true)
    expect(wrapper.find('.btn-confirm-pay').exists()).toBe(false)
    expect(wrapper.find('[data-testid="owner-payment-qr"]').attributes('src')).toBe('https://qr.example/rec-101')

    const getCallsBefore = vi.mocked(apiV2.get).mock.calls.length
    await wrapper.find('[data-testid="owner-payment-refresh"]').trigger('click')
    await flushPromises()

    // Refresh re-loads the invoice from the server; it NEVER posts a self-declared payment.
    expect(vi.mocked(apiV2.get).mock.calls.length).toBeGreaterThan(getCallsBefore)
    expect(apiV2.post).not.toHaveBeenCalled()

    wrapper.unmount()
  })
})
