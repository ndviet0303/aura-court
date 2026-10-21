import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ReservationDetailModal from '../components/reservation/ReservationDetailModal.vue'
import { i18n } from '@/i18n'
import type { Reservation } from '../types/reservation'

const base = {
  id: 51,
  code: 'RSV-DETAIL-51',
  total_amount: 180000,
  payment_model: 'direct_owner_v1',
  court: { id: 3, name: 'Sân 3' },
  slots: [],
  booker: { name: 'Lan', phone: '0900000001', email: 'lan@example.com' },
}

const mountDetail = (reservation: Record<string, unknown>) =>
  mount(ReservationDetailModal, {
    props: {
      show: true,
      reservation: { ...base, ...reservation } as unknown as Reservation,
      money: (value: number) => `${value} VND`,
      formatDate: (value: string) => value,
      formatTimeSlot: (startsAt: string) => startsAt,
    },
    global: { plugins: [i18n], stubs: { RouterLink: true } },
  })

const fact = (wrapper: ReturnType<typeof mountDetail>, key: string) =>
  wrapper.find(`[data-testid="detail-${key}"]`)

describe('ReservationDetailModal', () => {
  beforeEach(() => {
    ;(i18n.global.locale as unknown as { value: string }).value = 'en'
  })

  afterEach(() => {
    ;(i18n.global.locale as unknown as { value: string }).value = 'vi'
  })

  it('shows a pending booking with its deadline and lets the customer pay again or cancel', async () => {
    const wrapper = mountDetail({
      status: 'held',
      hold_expires_at: '2026-09-25T12:30:00Z',
      quote: { benefit_snapshot: { coupon_code: 'SAVE20K' } },
      payment: { status: 'pending', amount: 180000, currency: 'VND', receipt: null },
    })

    expect(fact(wrapper, 'booker').text()).toContain('Lan · 0900000001 · lan@example.com')
    expect(fact(wrapper, 'voucher').text()).toContain('SAVE20K')
    // The deadline reads on the venue clock: 12:30Z is 19:30 in Vietnam.
    expect(fact(wrapper, 'deadline').text().replace(/\s/g, ' ')).toContain('09/25/2026, 07:30 PM')
    expect(fact(wrapper, 'payment').text()).toContain('Waiting for the transfer')
    expect(wrapper.find('[data-testid="cancel-payment"]').exists()).toBe(true)

    await wrapper.get('[data-testid="reservation-detail-pay"]').trigger('click')
    expect(wrapper.emitted('pay')?.[0]?.[0]).toMatchObject({ code: 'RSV-DETAIL-51' })
  })

  it('shows the payment and attendance times of a completed booking without actions on it', () => {
    const wrapper = mountDetail({
      status: 'confirmed',
      metadata: { operational_status: 'completed' },
      payment: {
        status: 'paid',
        amount: 180000,
        currency: 'VND',
        paid_at: '2026-09-25T11:00:00Z',
        receipt: { status: 'allocated', amount: 180000, currency: 'VND' },
      },
      attendance: {
        status: 'checked_out',
        checked_in_at: '2026-09-25T12:00:00Z',
        checked_out_at: '2026-09-25T13:00:00Z',
      },
    })

    expect(fact(wrapper, 'payment').text().replace(/\s/g, ' ')).toContain(
      'Paid at 09/25/2026, 06:00 PM',
    )
    expect(fact(wrapper, 'checkIn').text()).toContain('07:00 PM')
    expect(fact(wrapper, 'checkOut').text()).toContain('08:00 PM')
    expect(fact(wrapper, 'deadline').exists()).toBe(false)
    expect(wrapper.find('[data-testid="cancel-payment"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="reservation-detail-pay"]').exists()).toBe(false)
    // Check-in and check-out are done at the venue, never from the customer's detail.
    expect(wrapper.findAll('button').map((button) => button.text())).not.toContainEqual(
      expect.stringMatching(/check/i),
    )
  })

  it('labels a payment the customer cancelled apart from other cancellations', () => {
    const cancelledByCustomer = mountDetail({
      status: 'cancelled',
      metadata: { release_reason: 'customer_payment_cancel' },
      payment: {
        status: 'expired',
        amount: 180000,
        currency: 'VND',
        receipt: { status: 'late', amount: 180000, currency: 'VND' },
      },
    })
    expect(cancelledByCustomer.get('.status-badge').text()).toBe('Payment cancelled')
    expect(fact(cancelledByCustomer, 'payment').text()).toContain('the venue will reconcile it')

    const cancelledByVenue = mountDetail({ status: 'cancelled', metadata: null })
    expect(cancelledByVenue.get('.status-badge').text()).toBe('Cancelled')
    expect(fact(cancelledByVenue, 'payment').exists()).toBe(false)
  })
})
