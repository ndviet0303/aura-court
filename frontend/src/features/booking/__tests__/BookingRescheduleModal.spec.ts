import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import BookingRescheduleModal from '../components/history/BookingRescheduleModal.vue'
import { bookingApi } from '../api/bookingApi'
import { i18n } from '@/i18n'
import type {
  ChangeableBooking,
  ReschedulePreview,
  RescheduleResult,
} from '../types/reservationChanges'
import type { Court } from '@/features/court/types/court'

vi.mock('../api/bookingApi', () => ({
  bookingApi: {
    getCourts: vi.fn(),
    previewReschedule: vi.fn(),
    createRescheduleRequest: vi.fn(),
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
  code: 'BK-RESCHED-1',
  status: 'confirmed',
  slots: [
    {
      id: 1,
      starts_at: '2099-09-01T18:00:00Z',
      ends_at: '2099-09-01T19:00:00Z',
      unit_price: 200000,
    },
  ],
}

function basePreview(overrides: Partial<ReschedulePreview>): ReschedulePreview {
  return {
    reservation_code: 'BK-RESCHED-1',
    new_court_id: 2,
    slots: [
      { starts_at: '2099-09-02T18:00:00Z', ends_at: '2099-09-02T19:00:00Z', unit_price: 200000 },
    ],
    policy_version: 3,
    deadline_at: '2099-08-30T18:00:00Z',
    original_paid_amount: 200000,
    new_total_amount: 200000,
    change_fee_amount: 0,
    extra_payment_amount: 0,
    refund_amount: 0,
    currency: 'VND',
    ...overrides,
  }
}

function mountModal() {
  return mount(BookingRescheduleModal, {
    props: { show: true, booking },
    global: { plugins: [i18n] },
  })
}

async function preview() {
  const wrapper = mountModal()
  await flushPromises() // courts load
  await wrapper.get('[data-testid="reschedule-preview-btn"]').trigger('click')
  await flushPromises()
  return wrapper
}

describe('BookingRescheduleModal', () => {
  beforeEach(() => {
    keyCounter = 0
    vi.clearAllMocks()
    ;(i18n.global.locale as unknown as { value: string }).value = 'vi'
    vi.mocked(bookingApi.getCourts).mockResolvedValue([
      { id: 2, name: 'Court 2' },
    ] as unknown as Court[])
  })

  afterEach(() => {
    vi.restoreAllMocks()
    ;(i18n.global.locale as unknown as { value: string }).value = 'vi'
  })

  it('shows the extra-payment case (more expensive) with the amount due', async () => {
    vi.mocked(bookingApi.previewReschedule).mockResolvedValue({
      data: basePreview({
        new_total_amount: 320000,
        change_fee_amount: 20000,
        extra_payment_amount: 140000,
      }),
    })
    const wrapper = await preview()

    expect(wrapper.find('[data-testid="reschedule-case-extra"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="reschedule-case-refund"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="reschedule-case-equal"]').exists()).toBe(false)
    // Amount due is surfaced (140,000 VND) before committing.
    expect(wrapper.get('[data-testid="reschedule-extra-amount"]').text()).toContain('140')
    wrapper.unmount()
  })

  it('shows the refund-difference case (cheaper) with the refunded amount', async () => {
    vi.mocked(bookingApi.previewReschedule).mockResolvedValue({
      data: basePreview({ new_total_amount: 150000, refund_amount: 50000 }),
    })
    const wrapper = await preview()

    expect(wrapper.find('[data-testid="reschedule-case-refund"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="reschedule-case-extra"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="reschedule-refund-amount"]').text()).toContain('50')
    // A refund difference requires recipient details.
    expect(wrapper.find('[data-testid="reschedule-recipient-bank"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('shows the equal case (no money movement)', async () => {
    vi.mocked(bookingApi.previewReschedule).mockResolvedValue({
      data: basePreview({}),
    })
    const wrapper = await preview()

    expect(wrapper.find('[data-testid="reschedule-case-equal"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="reschedule-case-extra"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="reschedule-case-refund"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="reschedule-recipient-bank"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('surfaces a 409 replacement-time conflict as an error without altering the original', async () => {
    vi.mocked(bookingApi.previewReschedule).mockRejectedValueOnce({
      status: 409,
      message: 'conflict',
    })
    const wrapper = mountModal()
    await flushPromises()
    await wrapper.get('[data-testid="reschedule-preview-btn"]').trigger('click')
    await flushPromises()

    // Error surfaced; no captured preview means the original is untouched.
    expect(wrapper.find('[data-testid="reschedule-error"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="reschedule-error"]').text()).toContain('sân thay thế')
    expect(wrapper.find('[data-testid="reschedule-preview"]').exists()).toBe(false)
    // Submit is disabled and no request is created.
    expect(
      wrapper.get('[data-testid="reschedule-submit-btn"]').attributes('disabled'),
    ).toBeDefined()
    expect(bookingApi.createRescheduleRequest).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('reuses the same idempotency_key when a submit is retried after a failure', async () => {
    vi.mocked(bookingApi.previewReschedule).mockResolvedValue({
      data: basePreview({ new_total_amount: 320000, extra_payment_amount: 120000 }),
    })
    const result: RescheduleResult = {
      reservation_code: 'BK-RESCHED-1',
      status: 'pending_reschedule',
      replacement: {
        code: 'RSV-NEW-1',
        status: 'held',
        court_id: 2,
        total_amount: 320000,
        hold_expires_at: '2099-09-01T18:30:00Z',
        slots: [
          {
            starts_at: '2099-09-02T18:00:00Z',
            ends_at: '2099-09-02T19:00:00Z',
            unit_price: 320000,
          },
        ],
      },
      change: null,
      payment_attempt: null,
      refund: null,
    }
    // First submit fails, second succeeds.
    vi.mocked(bookingApi.createRescheduleRequest)
      .mockRejectedValueOnce({ status: 500, message: 'boom' })
      .mockResolvedValueOnce({ data: result })

    const wrapper = await preview()

    await wrapper.get('[data-testid="reschedule-submit-btn"]').trigger('click')
    await flushPromises()
    // Retry the same submit.
    await wrapper.get('[data-testid="reschedule-submit-btn"]').trigger('click')
    await flushPromises()

    expect(bookingApi.createRescheduleRequest).toHaveBeenCalledTimes(2)
    const firstKey = vi.mocked(bookingApi.createRescheduleRequest).mock.calls[0]?.[1]
      .idempotency_key
    const secondKey = vi.mocked(bookingApi.createRescheduleRequest).mock.calls[1]?.[1]
      .idempotency_key
    expect(firstKey).toBe('idem-1')
    expect(secondKey).toBe('idem-1')
    // Result status is shown truthfully (held replacement, not "completed").
    expect(wrapper.get('[data-testid="reschedule-result-status"]').text()).toContain(
      'pending_reschedule',
    )
    wrapper.unmount()
  })

  it('renders English strings when the locale is switched', async () => {
    ;(i18n.global.locale as unknown as { value: string }).value = 'en'
    vi.mocked(bookingApi.previewReschedule).mockResolvedValue({
      data: basePreview({ new_total_amount: 320000, extra_payment_amount: 140000 }),
    })
    const wrapper = await preview()

    expect(wrapper.get('[data-testid="reschedule-case-extra"]').text()).toContain(
      'Extra payment due',
    )
    wrapper.unmount()
  })
})
