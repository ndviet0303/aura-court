import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import OwnerPaymentExceptionsView from '../views/OwnerPaymentExceptionsView.vue'
import type { OwnerPaymentException } from '../types/finance'
import { i18n, setAppLocale } from '@/i18n'
import { api } from '@/core/api/client'

vi.mock('@/core/api/client', () => ({ api: { get: vi.fn() } }))

function row(overrides: Partial<OwnerPaymentException>): OwnerPaymentException {
  return {
    id: 1,
    code: 'transfer_after_expiry',
    status: 'open',
    amount: 300000,
    currency: 'VND',
    payment_code: 'ACABCDEF0123456789XY',
    event_at: '2026-10-21T11:30:00.000Z',
    connection: { bank_code: 'MBBank', account_last_four: '4321' },
    purpose: 'booking',
    booking_code: 'RSVABC123',
    detected_at: '2026-10-21T11:30:05.000Z',
    ...overrides,
  }
}

function page(data: OwnerPaymentException[], current = 1, last = 1) {
  return { data, meta: { current_page: current, last_page: last, total: data.length } }
}

function mountView() {
  return mount(OwnerPaymentExceptionsView, { global: { plugins: [i18n] } })
}

describe('OwnerPaymentExceptionsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setAppLocale('vi')
  })

  it('shows what arrived, where, and the booking its code named', async () => {
    vi.mocked(api.get).mockResolvedValue(
      page([
        row({}),
        row({
          id: 2,
          code: 'unmatched_reference',
          payment_code: 'ACNOSUCHREQUEST00001',
          purpose: null,
          booking_code: null,
        }),
      ]),
    )
    const wrapper = mountView()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/owner-payment-exceptions?page=1')
    const [cancelled, unknown] = wrapper.findAll('tbody tr').map((tr) => tr.text())
    expect(cancelled).toContain('Chuyển sau khi đơn hết hạn hoặc đã hủy')
    expect(cancelled).toContain('ACABCDEF0123456789XY')
    expect(cancelled).toContain('MBBank ••4321')
    expect(cancelled).toContain('RSVABC123')
    expect(cancelled).toContain('Chưa xử lý')
    expect(cancelled).toMatch(/300\.000/)
    expect(cancelled).toContain('21/10/2026')
    expect(unknown).toContain('Mã không khớp yêu cầu nào')
    expect(unknown).not.toContain('RSVABC123')
    expect(wrapper.text()).toContain('Màn hình chỉ để tra cứu')
  })

  it('names the purpose when the code matched a request other than a booking', async () => {
    vi.mocked(api.get).mockResolvedValue(
      page([row({ code: 'excess_amount', purpose: 'split_share', booking_code: null })]),
    )
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('tbody tr').text()).toContain('Phần chia tiền')
  })

  it('shows an empty state and a load error', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(page([]))
    const empty = mountView()
    await flushPromises()
    expect(empty.find('[data-testid="owner-payment-exceptions-empty"]').exists()).toBe(true)

    vi.mocked(api.get).mockRejectedValueOnce(new Error('network down'))
    const failed = mountView()
    await flushPromises()
    expect(failed.find('[role="alert"]').exists()).toBe(true)
    expect(failed.find('[data-testid="owner-payment-exceptions-empty"]').exists()).toBe(false)
  })

  it('pages through older exceptions', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce(page([row({})], 1, 2))
      .mockResolvedValueOnce(page([row({ id: 9, booking_code: 'RSVOLDER01' })], 2, 2))
    const wrapper = mountView()
    await flushPromises()

    const next = wrapper.findAll('nav button').find((b) => b.text() === 'Trang sau')!
    await next.trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenLastCalledWith('/owner-payment-exceptions?page=2')
    expect(wrapper.text()).toContain('RSVOLDER01')
    expect(wrapper.text()).toContain('Trang 2/2')
  })
})
