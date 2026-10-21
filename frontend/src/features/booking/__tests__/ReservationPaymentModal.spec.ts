import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ReservationPaymentModal from '../components/reservation/ReservationPaymentModal.vue'
import { bookingApi } from '../api/bookingApi'
import { i18n } from '@/i18n'
import type { Reservation } from '../types/reservation'

vi.mock('../api/bookingApi', () => ({
  bookingApi: {
    createReservationPayment: vi.fn(),
    getReservationPayment: vi.fn(),
    cancelReservationPayment: vi.fn(),
  },
}))

const pendingPayment = {
  id: 41,
  provider: 'sepay',
  reference: 'ACRESV41TESTCODE',
  transfer_content: 'ACRESV41TESTCODE',
  amount: 100000,
  currency: 'VND',
  status: 'pending',
  expires_at: '2026-09-25T12:30:00Z',
  paid_at: null,
  receiver: { bank_code: 'MBBank', account_name: 'Venue Owner', account_number: '0123456789' },
  qr_url:
    'https://img.vietqr.io/image/MB-0123456789-compact2.png?amount=100000&addInfo=ACRESV41TESTCODE',
}

describe('ReservationPaymentModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    ;(i18n.global.locale as unknown as { value: string }).value = 'en'
    vi.mocked(bookingApi.createReservationPayment).mockResolvedValue({
      reservation: { code: 'RSV-PAY-41', status: 'held' },
      payment_attempt: pendingPayment,
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
    ;(i18n.global.locale as unknown as { value: string }).value = 'vi'
  })

  it('shows server payment instructions and refreshes to the persisted paid status', async () => {
    const wrapper = mount(ReservationPaymentModal, {
      props: {
        show: true,
        reservation: {
          id: 41,
          code: 'RSV-PAY-41',
          status: 'held',
          total_amount: 100000,
        } as Reservation,
      },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    expect(bookingApi.createReservationPayment).toHaveBeenCalledWith('RSV-PAY-41')
    expect(wrapper.get('[data-testid="reservation-payment-qr"]').attributes('src')).toBe(
      pendingPayment.qr_url,
    )
    expect(wrapper.get('[data-testid="reservation-payment-status"]').text()).toContain(
      'Waiting for verified bank transfer',
    )
    expect(wrapper.text()).toContain('0123456789')
    expect(wrapper.text()).toContain('ACRESV41TESTCODE')
    // Expiry follows the app locale on the venue clock: 12:30Z is 19:30 in Vietnam.
    expect(wrapper.text().replace(/\s/g, ' ')).toContain('expire at 09/25/2026, 07:30 PM.')

    vi.mocked(bookingApi.getReservationPayment).mockResolvedValueOnce({
      reservation: { code: 'RSV-PAY-41', status: 'confirmed' },
      payment_attempt: { ...pendingPayment, status: 'paid', paid_at: '2026-09-25T12:05:00Z' },
    })
    await wrapper.get('[data-testid="refresh-payment-status"]').trigger('click')
    await flushPromises()

    expect(bookingApi.getReservationPayment).toHaveBeenCalledWith('RSV-PAY-41')
    expect(wrapper.get('[data-testid="reservation-payment-status"]').text()).toContain(
      'Payment verified. Reservation confirmed.',
    )
    expect(wrapper.find('[data-testid="reservation-payment-qr"]').exists()).toBe(false)
    expect(wrapper.emitted('paid')).toHaveLength(1)
    wrapper.unmount()
  })

  it('counts down to the booking payment deadline while the transfer is pending', async () => {
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] })
    vi.setSystemTime(new Date('2026-09-25T12:20:00Z'))
    // The status poll keeps reporting the pending transfer while the clock runs.
    vi.mocked(bookingApi.getReservationPayment).mockResolvedValue({
      reservation: { code: 'RSV-PAY-43', status: 'held' },
      payment_attempt: pendingPayment,
    })
    try {
      const wrapper = mount(ReservationPaymentModal, {
        props: {
          show: true,
          reservation: {
            id: 43,
            code: 'RSV-PAY-43',
            status: 'held',
            total_amount: 100000,
            hold_expires_at: '2026-09-25T12:30:00Z',
          } as Reservation,
        },
        global: { plugins: [i18n] },
      })
      await flushPromises()

      expect(wrapper.get('[data-testid="payment-countdown"]').text()).toBe(
        'Time left to pay: 10:00',
      )
      vi.advanceTimersByTime(61_000)
      await flushPromises()
      expect(wrapper.get('[data-testid="payment-countdown"]').text()).toBe(
        'Time left to pay: 08:59',
      )
      wrapper.unmount()
    } finally {
      vi.useRealTimers()
    }
  })

  it('shows no countdown for a booking without a payment deadline', async () => {
    const wrapper = mount(ReservationPaymentModal, {
      props: {
        show: true,
        reservation: { id: 44, code: 'RSV-PAY-44', status: 'held' } as Reservation,
      },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    expect(wrapper.find('[data-testid="reservation-payment-qr"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="payment-countdown"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('does not show a bank QR when the server cannot start a verified payment', async () => {
    vi.mocked(bookingApi.createReservationPayment).mockRejectedValueOnce(
      new Error('No selected verified account.'),
    )
    const wrapper = mount(ReservationPaymentModal, {
      props: {
        show: true,
        reservation: {
          id: 42,
          code: 'RSV-PAY-42',
          status: 'held',
          total_amount: 100000,
        } as Reservation,
      },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    expect(wrapper.find('[data-testid="reservation-payment-qr"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="payment-error"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('lets the customer cancel the pending booking from the payment screen', async () => {
    vi.mocked(bookingApi.cancelReservationPayment).mockResolvedValue({
      reservation: { code: 'RSV-PAY-45', status: 'cancelled' } as Reservation,
    })
    const held = { id: 45, code: 'RSV-PAY-45', status: 'held' } as Reservation
    const wrapper = mount(ReservationPaymentModal, {
      props: { show: true, reservation: held },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    await wrapper.get('[data-testid="cancel-payment"]').trigger('click')
    await wrapper.get('[data-testid="confirm-cancel-payment"]').trigger('click')
    await flushPromises()

    expect(bookingApi.cancelReservationPayment).toHaveBeenCalledWith('RSV-PAY-45')
    expect(wrapper.emitted('cancelled')?.[0]).toEqual([held])
    wrapper.unmount()
  })
})
