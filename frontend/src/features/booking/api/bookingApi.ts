import { api, apiV2 } from '@/core/api/client'
import type { Booking, PaymentInfo, CreateBookingPayload, CardDetails } from '../types/booking'
import type { Court, AvailabilitySlot } from '@/features/court/types/court'

export const bookingApi = {
  getCourts: <T = Court[]>(params?: { sport_type?: string; venue_id?: number }) => {
    const query = new URLSearchParams()
    if (params?.sport_type && params.sport_type !== 'all') query.set('sport_type', params.sport_type)
    if (params?.venue_id) query.set('venue_id', String(params.venue_id))
    const qs = query.toString() ? `?${query.toString()}` : ''
    return api.get<T>(`/courts${qs}`)
  },

  getAvailability: (courtId: number, date: string) =>
    api.get<AvailabilitySlot[]>(`/availability?court_id=${courtId}&date=${date}`),

  create: (payload: CreateBookingPayload) =>
    api.post<{ booking: Booking; payment: PaymentInfo }>('/bookings', payload),

  createRecurring: (payload: { court_id: number; slots: string[]; weeks: number }) =>
    api.post<{ bookings: { booking: Booking; payment: PaymentInfo }[] }>('/bookings/recurring', payload),

  getPayment: (code: string) =>
    api.get<{ booking?: Booking; payment: PaymentInfo; status: string }>(`/bookings/${code}/payment`),

  getPaymentStatus: (code: string) =>
    api.get<{ status: string; payment: unknown; expiresAt: string }>(`/bookings/${code}/payment-status`),

  applyCoupon: (code: string, couponCode?: string | null) =>
    api.post<{ booking: Booking; payment: PaymentInfo; status: string }>(`/bookings/${code}/coupon`, {
      coupon_code: couponCode || null,
    }),

  previewCoupon: <T = unknown>(payload: { subtotal: number; code: string }) =>
    api.post<T>('/coupons/preview', payload),

  stripePay: (code: string, cardDetails: CardDetails = {}) =>
    api.post<{ status: string; booking: Booking }>(`/bookings/${code}/stripe-pay`, {
      card_number: cardDetails.cardNumber,
      card_expiry: cardDetails.cardExpiry,
      card_cvc: cardDetails.cardCvc,
      card_name: cardDetails.cardName,
    }),

  demoPay: (code: string) =>
    api.post<{ status: string; booking: Booking }>(`/bookings/${code}/demo-pay`),

  cancel: (code: string, reason?: string) =>
    api.post<Booking>(`/bookings/${code}/cancel`, { reason }),

  cancelSlots: (code: string, slotIds: number[], reason?: string) =>
    api.post<Booking>(`/bookings/${code}/cancel-slots`, { slot_ids: slotIds, reason }),

  list: () => api.get<{ data: Booking[] }>('/bookings'),

  submitReview: (bookingCode: string, payload: { rating: number; body?: string }) =>
    api.post('/reviews', { booking_code: bookingCode, ...payload }),

  createRecurringSeries: <T = { series: import('../types/recurring').RecurringSeries }>(payload: import('../types/recurring').CreateRecurringSeriesPayload) =>
    apiV2.post<T>('/recurring-series', payload),

  getRecurringSeries: <T = { data: import('../types/recurring').RecurringSeries[] }>() =>
    apiV2.get<T>('/recurring-series'),

  // V2 Reservations & Quotes
  getReservations: <T = { data: import('../types/v2Reservation').V2Reservation[] }>() =>
    apiV2.get<T>('/reservations'),

  getReservation: <T = { reservation: import('../types/v2Reservation').V2Reservation }>(code: string) =>
    apiV2.get<T>(`/reservations/${code}`),

  getPriceQuote: <T = { quote: import('../types/v2Reservation').V2PriceQuote }>(payload: { court_id: number; slots: string[] }) =>
    apiV2.post<T>('/price-quotes', payload),

  holdReservation: <T = { reservation: import('../types/v2Reservation').V2Reservation }>(payload: import('../types/v2Reservation').V2HoldReservationPayload) =>
    apiV2.post<T>('/reservations', payload),

  confirmReservation: <T = { reservation: import('../types/v2Reservation').V2Reservation }>(code: string) =>
    apiV2.post<T>(`/reservations/${code}/confirm`),

  cancelReservation: <T = { reservation: import('../types/v2Reservation').V2Reservation }>(code: string) =>
    apiV2.post<T>(`/reservations/${code}/cancel`),

  joinWaitlist: (payload: { court_id?: number; starts_at: string }) =>
    api.post('/waitlist', payload),

  reschedule: (code: string, courtId: number, slots: string[]) =>
    api.post<{ booking: Booking; payment: PaymentInfo }>(`/bookings/${code}/reschedule`, {
      court_id: courtId,
      slots,
    }),
}
