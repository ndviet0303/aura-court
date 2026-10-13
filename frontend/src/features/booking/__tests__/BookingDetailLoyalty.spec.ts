import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import UserBookingCard from '../components/history/UserBookingCard.vue'
import { i18n, setAppLocale } from '@/i18n'
import type { Reservation } from '../types/reservation'

describe('BookingDetailLoyalty (Task 7.3 & 7.4)', () => {
  // The history card renders reservations (GET /me/reservations).
  const mockReservationWithLoyalty: Reservation = {
    id: 42,
    code: 'RSV-LOYALTY-001',
    status: 'confirmed',
    total_amount: 195000,
    loyalty_discount_amount: 5000,
    loyalty_points_redeemed: 100,
    court: {
      id: 1,
      name: 'Sân VIP 1',
      slug: 'san-vip-1',
      is_active: true,
      sport_zone: { id: 1, name: 'Zone A', sport_type: 'badminton' } as any,
      venue: { id: 1, name: 'Aura Badminton Center', address: '123 HCM' } as any,
    },
    slots: [
      {
        id: 1,
        starts_at: '2026-10-20T09:00:00.000Z',
        ends_at: '2026-10-20T10:00:00.000Z',
        unit_price: 200000,
      },
    ],
  }

  const globalOptions = {
    plugins: [i18n],
    stubs: {
      RouterLink: {
        props: ['to'],
        template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
      },
    },
  }

  beforeEach(() => {
    setAppLocale('vi')
  })

  it('renders persisted points redeemed and loyalty reduction in UserBookingCard', () => {
    const wrapper = mount(UserBookingCard, {
      props: {
        booking: mockReservationWithLoyalty,
      },
      global: globalOptions,
    })

    expect(wrapper.text()).toContain('Giảm giá điểm')
    expect(wrapper.text()).toContain('(100 điểm)')
    expect(wrapper.text()).toContain('-5.000')
    expect(wrapper.text()).toContain('195.000')
  })

  it('does not render loyalty discount row in UserBookingCard when no points were used', () => {
    const ordinaryBooking: Reservation = {
      ...mockReservationWithLoyalty,
      loyalty_discount_amount: 0,
      loyalty_points_redeemed: 0,
      total_amount: 200000,
    }

    const wrapper = mount(UserBookingCard, {
      props: {
        booking: ordinaryBooking,
      },
      global: globalOptions,
    })

    expect(wrapper.text()).not.toContain('Giảm giá điểm')
    expect(wrapper.text()).toContain('200.000')
  })

  it('renders English localized loyalty labels in UserBookingCard when locale is en', () => {
    setAppLocale('en')

    const wrapper = mount(UserBookingCard, {
      props: {
        booking: mockReservationWithLoyalty,
      },
      global: globalOptions,
    })

    expect(wrapper.text()).toContain('Loyalty discount:')
    expect(wrapper.text()).toContain('(100 pts)')
    expect(wrapper.text()).toContain('5,000')
  })
})
