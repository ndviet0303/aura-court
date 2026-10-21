import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import CancelPaymentButton from '../components/reservation/CancelPaymentButton.vue'
import HeldReservationBanner from '../components/reservation/HeldReservationBanner.vue'
import { bookingApi } from '../api/bookingApi'
import { i18n } from '@/i18n'
import type { Reservation } from '../types/reservation'

vi.mock('../api/bookingApi', () => ({
  bookingApi: { cancelReservationPayment: vi.fn() },
}))

const mountButton = () =>
  mount(CancelPaymentButton, { props: { code: 'RSV-PAY-7' }, global: { plugins: [i18n] } })

describe('CancelPaymentButton', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    ;(i18n.global.locale as unknown as { value: string }).value = 'en'
  })

  afterEach(() => {
    ;(i18n.global.locale as unknown as { value: string }).value = 'vi'
  })

  it('asks for confirmation before cancelling and can be backed out of', async () => {
    const wrapper = mountButton()

    await wrapper.get('[data-testid="cancel-payment"]').trigger('click')
    expect(wrapper.get('[data-testid="cancel-payment-confirm"]').text()).toContain(
      'The court time and voucher will be released.',
    )
    expect(bookingApi.cancelReservationPayment).not.toHaveBeenCalled()

    await wrapper.findAll('[data-testid="cancel-payment-confirm"] button')[1]!.trigger('click')
    expect(wrapper.find('[data-testid="cancel-payment-confirm"]').exists()).toBe(false)
    expect(wrapper.emitted('cancelled')).toBeUndefined()
  })

  it('cancels the booking once confirmed and reports it', async () => {
    vi.mocked(bookingApi.cancelReservationPayment).mockResolvedValue({
      reservation: { code: 'RSV-PAY-7', status: 'cancelled' } as never,
    })
    const wrapper = mountButton()

    await wrapper.get('[data-testid="cancel-payment"]').trigger('click')
    await wrapper.get('[data-testid="confirm-cancel-payment"]').trigger('click')
    await flushPromises()

    expect(bookingApi.cancelReservationPayment).toHaveBeenCalledWith('RSV-PAY-7')
    expect(wrapper.emitted('cancelled')).toHaveLength(1)
  })

  it('explains why a booking that is no longer pending cannot be cancelled', async () => {
    vi.mocked(bookingApi.cancelReservationPayment).mockRejectedValue(
      Object.assign(new Error('Conflict'), { status: 409, code: 'RESERVATION_NOT_PENDING' }),
    )
    const wrapper = mountButton()

    await wrapper.get('[data-testid="cancel-payment"]').trigger('click')
    await wrapper.get('[data-testid="confirm-cancel-payment"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="cancel-payment-error"]').text()).toBe(
      'This booking is no longer pending payment, so it cannot be cancelled.',
    )
    expect(wrapper.emitted('cancelled')).toBeUndefined()
  })

  it('is offered on the pending booking banner and hands the booking back once cancelled', async () => {
    vi.mocked(bookingApi.cancelReservationPayment).mockResolvedValue({
      reservation: { code: 'RSV-PAY-7', status: 'cancelled' } as never,
    })
    const held = { id: 7, code: 'RSV-PAY-7', status: 'held', slots: [] } as unknown as Reservation
    const wrapper = mount(HeldReservationBanner, {
      props: {
        reservation: held,
        confirmingCode: null,
        money: String,
        formatDate: String,
        formatTimeSlot: String,
      },
      global: { plugins: [i18n] },
    })

    await wrapper.get('[data-testid="cancel-payment"]').trigger('click')
    await wrapper.get('[data-testid="confirm-cancel-payment"]').trigger('click')
    await flushPromises()

    expect(wrapper.emitted('cancelled')?.[0]).toEqual([held])
  })
})
