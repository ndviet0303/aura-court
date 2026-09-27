import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminRefundsPanel from '@/features/admin/components/manage/AdminRefundsPanel.vue'
import { i18n } from '@/i18n'
import { refundReviewApi } from '@/features/admin/api/refundReviewApi'
import type { RefundObligation } from '@/features/admin/api/refundReviewApi'

// The panel exercises the real owner refund-obligation review endpoints via
// `refundReviewApi`; assert behaviour through data-testids + the mocked api,
// never through translated copy (the locale keys are owned elsewhere).
vi.mock('@/core/api/client', () => ({
  errorMessage: (_cause: unknown, fallback: string) => fallback,
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  apiV2: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

vi.mock('@/features/admin/api/refundReviewApi', () => ({
  refundReviewApi: {
    listRefundInbox: vi.fn().mockResolvedValue({ data: [] }),
    approveRefund: vi.fn().mockResolvedValue({ data: {} }),
    rejectRefund: vi.fn().mockResolvedValue({ data: {} }),
    reportRefundExecution: vi.fn().mockResolvedValue({ data: {} }),
    acknowledgeRefund: vi.fn().mockResolvedValue({ data: {} }),
  },
}))

function obligation(overrides: Partial<RefundObligation> = {}): RefundObligation {
  return {
    id: 1,
    reference_code: 'RF-TEST-1',
    reservation_code: 'RSV-1',
    status: 'requested',
    gross_amount: 100000,
    fee_amount: 10000,
    refund_amount: 90000,
    currency: 'VND',
    slot_ids: [1],
    reason: 'Khách huỷ sân',
    recipient: null,
    due_at: null,
    requested_at: '2026-09-25T00:00:00+07:00',
    approved_at: null,
    owner_reported_at: null,
    recipient_acknowledged_at: null,
    evidence: [],
    ...overrides,
  }
}

function mountPanel(refunds: RefundObligation[]) {
  return mount(AdminRefundsPanel, {
    props: { refunds },
    global: { plugins: [i18n] },
  })
}

describe('AdminRefundsPanel (owner refund-obligation review)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders the empty state when there are no obligations', () => {
    const wrapper = mountPanel([])
    expect(wrapper.find('.empty-refunds-box').exists()).toBe(true)
  })

  it('exposes approve and reject actions for a requested obligation', () => {
    const wrapper = mountPanel([obligation({ id: 7, status: 'requested' })])

    expect(wrapper.find('[data-testid="refund-approve-7"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="refund-reject-7"]').exists()).toBe(true)
    // A requested obligation must not present a report-execution control yet.
    expect(wrapper.find('[data-testid="refund-report-7"]').exists()).toBe(false)
  })

  it('calls approveRefund and emits changed when approve is clicked', async () => {
    const wrapper = mountPanel([obligation({ id: 7, status: 'requested' })])

    await wrapper.find('[data-testid="refund-approve-7"]').trigger('click')
    await flushPromises()

    expect(refundReviewApi.approveRefund).toHaveBeenCalledWith(7)
    expect(refundReviewApi.approveRefund).toHaveBeenCalledTimes(1)
    expect(wrapper.emitted('changed')).toBeTruthy()
  })

  it('calls rejectRefund with the entered reason when reject is submitted', async () => {
    const wrapper = mountPanel([obligation({ id: 9, status: 'requested' })])

    // Reveal the reason form, enter a reason, then submit.
    await wrapper.find('[data-testid="refund-reject-9"]').trigger('click')
    await wrapper.find('[data-testid="refund-reject-reason-9"]').setValue('Chứng từ không hợp lệ')
    await wrapper.find('[data-testid="refund-reject-submit-9"]').trigger('click')
    await flushPromises()

    expect(refundReviewApi.rejectRefund).toHaveBeenCalledWith(9, 'Chứng từ không hợp lệ')
    expect(refundReviewApi.approveRefund).not.toHaveBeenCalled()
    expect(wrapper.emitted('changed')).toBeTruthy()
  })

  it('renders the report-execution form for an approved obligation and submits transfer details', async () => {
    const wrapper = mountPanel([obligation({ id: 12, status: 'approved' })])

    const referenceInput = wrapper.find('[data-testid="refund-transfer-reference-12"]')
    const evidenceInput = wrapper.find('[data-testid="refund-evidence-input-12"]')
    expect(referenceInput.exists()).toBe(true)
    expect(evidenceInput.exists()).toBe(true)
    // An approved obligation is not yet actionable for approve/reject.
    expect(wrapper.find('[data-testid="refund-approve-12"]').exists()).toBe(false)

    await referenceInput.setValue('BANK-REF-1')
    await evidenceInput.setValue('Đã chuyển khoản qua app ngân hàng')
    await wrapper.find('[data-testid="refund-report-12"]').trigger('click')
    await flushPromises()

    expect(refundReviewApi.reportRefundExecution).toHaveBeenCalledWith(12, {
      transfer_reference: 'BANK-REF-1',
      evidence: 'Đã chuyển khoản qua app ngân hàng',
    })
    expect(wrapper.emitted('changed')).toBeTruthy()
  })

  it('renders an owner_reported obligation as pending acknowledgement, never completed or re-actionable', () => {
    const wrapper = mountPanel([obligation({ id: 20, status: 'owner_reported' })])

    // Truthful finance: an owner-reported transfer is pending the payer's
    // acknowledgement — surfaced as a pending indicator, with no action that
    // would imply the refund is already settled/verified.
    expect(wrapper.find('[data-testid="refund-pending-ack-20"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="refund-status-20"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="refund-approve-20"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="refund-reject-20"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="refund-report-20"]').exists()).toBe(false)
  })

  it('renders the evidence timeline when evidence events are present', () => {
    const wrapper = mountPanel([
      obligation({
        id: 30,
        status: 'owner_reported',
        evidence: [
          { id: 1, event_type: 'requested', actor_id: 10, event_fingerprint: 'a', created_at: '2026-09-25T01:00:00+07:00' },
          { id: 2, event_type: 'approved', actor_id: 11, event_fingerprint: 'b', created_at: '2026-09-25T02:00:00+07:00' },
        ],
      }),
    ])

    const timeline = wrapper.find('[data-testid="refund-evidence-timeline-30"]')
    expect(timeline.exists()).toBe(true)
    expect(timeline.findAll('.evidence-item').length).toBe(2)
  })
})
