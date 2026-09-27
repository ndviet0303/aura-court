import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import BookingCancelModal from '../components/history/BookingCancelModal.vue'
import { bookingApi } from '../api/bookingApi'
import { i18n } from '@/i18n'
import type {
  ChangeableBooking,
  RefundCalculation,
  RefundObligation,
  RefundQuote,
} from '../types/reservationChanges'

vi.mock('../api/bookingApi', () => ({
  bookingApi: {
    getRefundQuote: vi.fn(),
    createRefundRequest: vi.fn(),
  },
}))

// Deterministic, distinct idempotency keys so a wrongly-regenerated key would
// be observable; the modal must reuse the first one across retries.
let keyCounter = 0
vi.mock('@/core/api/client', () => ({
  errorMessage: (_cause: unknown, fallback: string) => fallback,
  idempotencyKey: vi.fn(() => `idem-${++keyCounter}`),
}))

const booking: ChangeableBooking = {
  code: 'BK-CANCEL-1',
  status: 'confirmed',
  slots: [
    { id: 1, starts_at: '2099-09-01T18:00:00Z', ends_at: '2099-09-01T19:00:00Z', unit_price: 200000 },
    { id: 2, starts_at: '2099-09-01T19:00:00Z', ends_at: '2099-09-01T20:00:00Z', unit_price: 200000 },
    { id: 3, starts_at: '2099-09-01T20:00:00Z', ends_at: '2099-09-01T21:00:00Z', unit_price: 200000 },
  ],
}

function baseQuote(calc: Partial<RefundCalculation> = {}): RefundQuote {
  return {
    reservation_code: 'BK-CANCEL-1',
    slot_ids: [1, 2, 3],
    calculation: {
      policy_version: 3,
      gross_amount: 600000,
      fee_type: 'none',
      fee_value: 0,
      fee_amount: 0,
      refund_amount: 600000,
      currency: 'VND',
      due_at: '2099-09-05T00:00:00Z',
      ...calc,
    },
    points_to_restore: 0,
  }
}

function baseObligation(overrides: Partial<RefundObligation> = {}): RefundObligation {
  return {
    id: 1,
    reference_code: 'RF-CANCEL-1',
    reservation_code: 'BK-CANCEL-1',
    payer_id: 1,
    status: 'requested',
    gross_amount: 600000,
    fee_amount: 0,
    refund_amount: 0,
    currency: 'VND',
    slot_ids: [1, 2, 3],
    reason: 'test',
    recipient: null,
    execution_reference: null,
    due_at: '2099-09-05T00:00:00Z',
    requested_at: '2099-09-01T00:00:00Z',
    approved_at: null,
    owner_reported_at: null,
    recipient_acknowledged_at: null,
    evidence: [],
    ...overrides,
  }
}

function mountModal() {
  return mount(BookingCancelModal, {
    props: { show: true, booking },
    global: { plugins: [i18n] },
  })
}

async function quoteWhole() {
  const wrapper = mountModal()
  await wrapper.get('[data-testid="cancel-quote-btn"]').trigger('click')
  await flushPromises()
  return wrapper
}

describe('BookingCancelModal', () => {
  beforeEach(() => {
    keyCounter = 0
    vi.clearAllMocks()
    ;(i18n.global.locale as unknown as { value: string }).value = 'vi'
  })

  afterEach(() => {
    vi.restoreAllMocks()
    ;(i18n.global.locale as unknown as { value: string }).value = 'vi'
  })

  it('previews a whole-scope quote with the fee-type label and refund amount', async () => {
    vi.mocked(bookingApi.getRefundQuote).mockResolvedValue({
      data: baseQuote({ fee_type: 'fixed', fee_value: 30000, fee_amount: 30000, refund_amount: 570000 }),
    })
    const wrapper = await quoteWhole()

    // Whole scope quotes every slot on the booking.
    expect(bookingApi.getRefundQuote).toHaveBeenCalledWith('BK-CANCEL-1', [1, 2, 3], false)
    const preview = wrapper.get('[data-testid="cancel-quote"]')
    // Fixed-fee type is labelled, and the refund amount comes from the quote.
    expect(preview.text()).toContain('Phí cố định')
    expect(wrapper.get('[data-testid="cancel-refund"]').text()).toContain('570')
    wrapper.unmount()
  })

  it('quotes only the selected slots in partial scope and clears the quote on reselection', async () => {
    vi.mocked(bookingApi.getRefundQuote).mockResolvedValue({
      data: baseQuote({ refund_amount: 400000 }),
    })
    const wrapper = mountModal()
    await wrapper.get('[data-testid="cancel-scope-partial"]').setValue()
    // Pick slots 1 and 3 (skipping 2) so a wrong payload would be observable.
    await wrapper.get('[data-testid="cancel-slot-1"]').trigger('change')
    await wrapper.get('[data-testid="cancel-slot-3"]').trigger('change')
    await wrapper.get('[data-testid="cancel-quote-btn"]').trigger('click')
    await flushPromises()

    expect(bookingApi.getRefundQuote).toHaveBeenCalledWith('BK-CANCEL-1', [1, 3], false)
    expect(wrapper.find('[data-testid="cancel-quote"]').exists()).toBe(true)

    // Changing the selection invalidates the captured quote.
    await wrapper.get('[data-testid="cancel-slot-2"]').trigger('change')
    expect(wrapper.find('[data-testid="cancel-quote"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('requires recipient details only when a refund is due', async () => {
    // refund > 0: empty recipient blocks the submit.
    vi.mocked(bookingApi.getRefundQuote).mockResolvedValue({
      data: baseQuote({ refund_amount: 500000 }),
    })
    vi.mocked(bookingApi.createRefundRequest).mockResolvedValue({ data: baseObligation() })
    const withRefund = await quoteWhole()
    await withRefund.get('[data-testid="cancel-reason"]').setValue('Đổi kế hoạch')
    await withRefund.get('[data-testid="cancel-submit-btn"]').trigger('click')
    await flushPromises()

    expect(bookingApi.createRefundRequest).not.toHaveBeenCalled()
    expect(withRefund.get('[data-testid="cancel-error"]').text()).toContain('tài khoản')
    withRefund.unmount()

    // refund == 0: no recipient needed, submit proceeds without one.
    vi.mocked(bookingApi.getRefundQuote).mockResolvedValue({
      data: baseQuote({ fee_type: 'fixed', fee_value: 600000, fee_amount: 600000, refund_amount: 0 }),
    })
    vi.mocked(bookingApi.createRefundRequest).mockResolvedValue({
      data: baseObligation({ refund_amount: 0 }),
    })
    const noRefund = await quoteWhole()
    await noRefund.get('[data-testid="cancel-reason"]').setValue('Không cần hoàn')
    await noRefund.get('[data-testid="cancel-submit-btn"]').trigger('click')
    await flushPromises()

    expect(bookingApi.createRefundRequest).toHaveBeenCalledTimes(1)
    const payload = vi.mocked(bookingApi.createRefundRequest).mock.calls[0]?.[1]
    expect(payload?.recipient).toBeUndefined()
    expect(payload?.slot_ids).toEqual([1, 2, 3])
    noRefund.unmount()
  })

  it('blocks submit when the reason is empty', async () => {
    vi.mocked(bookingApi.getRefundQuote).mockResolvedValue({
      data: baseQuote({ fee_type: 'fixed', fee_value: 600000, fee_amount: 600000, refund_amount: 0 }),
    })
    const wrapper = await quoteWhole()
    // Reason left blank.
    await wrapper.get('[data-testid="cancel-submit-btn"]').trigger('click')
    await flushPromises()

    expect(bookingApi.createRefundRequest).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="cancel-error"]').text()).toContain('lý do')
    wrapper.unmount()
  })

  it('reuses the same idempotency_key when a submit is retried after a failure', async () => {
    vi.mocked(bookingApi.getRefundQuote).mockResolvedValue({
      data: baseQuote({ fee_type: 'fixed', fee_value: 600000, fee_amount: 600000, refund_amount: 0 }),
    })
    vi.mocked(bookingApi.createRefundRequest)
      .mockRejectedValueOnce({ status: 500, message: 'boom' })
      .mockResolvedValueOnce({ data: baseObligation() })
    const wrapper = await quoteWhole()
    await wrapper.get('[data-testid="cancel-reason"]').setValue('Thử lại')

    await wrapper.get('[data-testid="cancel-submit-btn"]').trigger('click')
    await flushPromises()
    // Retry the same submit.
    await wrapper.get('[data-testid="cancel-submit-btn"]').trigger('click')
    await flushPromises()

    expect(bookingApi.createRefundRequest).toHaveBeenCalledTimes(2)
    const firstKey = vi.mocked(bookingApi.createRefundRequest).mock.calls[0]?.[1].idempotency_key
    const secondKey = vi.mocked(bookingApi.createRefundRequest).mock.calls[1]?.[1].idempotency_key
    expect(firstKey).toBe('idem-1')
    expect(secondKey).toBe('idem-1')
    wrapper.unmount()
  })

  it('renders the truthful requested status (never "completed") with the not-verified hint', async () => {
    vi.mocked(bookingApi.getRefundQuote).mockResolvedValue({
      data: baseQuote({ fee_type: 'fixed', fee_value: 600000, fee_amount: 600000, refund_amount: 0 }),
    })
    vi.mocked(bookingApi.createRefundRequest).mockResolvedValue({
      data: baseObligation({ status: 'requested' }),
    })
    const wrapper = await quoteWhole()
    await wrapper.get('[data-testid="cancel-reason"]').setValue('Hoàn tất gửi')
    await wrapper.get('[data-testid="cancel-submit-btn"]').trigger('click')
    await flushPromises()

    const statusText = wrapper.get('[data-testid="cancel-result-status"]').text()
    // 'requested' maps to its VI label — never a fabricated "completed".
    expect(statusText).toContain('Đã gửi yêu cầu')
    expect(statusText.toLowerCase()).not.toContain('completed')
    expect(statusText).not.toContain('hoàn tất')
    expect(wrapper.find('[data-testid="cancel-not-verified"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('renders English strings when the locale is switched', async () => {
    ;(i18n.global.locale as unknown as { value: string }).value = 'en'
    vi.mocked(bookingApi.getRefundQuote).mockResolvedValue({
      data: baseQuote({ fee_type: 'fixed', fee_value: 30000, fee_amount: 30000, refund_amount: 570000 }),
    })
    const wrapper = await quoteWhole()

    expect(wrapper.get('[data-testid="cancel-quote"]').text()).toContain('Fixed fee')
    wrapper.unmount()
  })
})
