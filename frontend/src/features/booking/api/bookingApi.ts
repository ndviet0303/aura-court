import { api } from '@/core/api/client'
import type { Court, AvailabilitySlot } from '@/features/court/types/court'
import type {
  RecurringSeriesPreview,
  RecurringSeriesPreviewPayload,
  CreateRecurringSeriesPayload,
  CreateRecurringSeriesResult,
} from '../types/recurring'

export type ReservationPaymentAttempt = {
  id: number
  provider: string
  reference: string
  transfer_content: string
  amount: number
  currency: string
  status: 'pending' | 'paid' | 'expired' | 'cancelled' | string
  expires_at: string | null
  paid_at: string | null
  receiver: { bank_code: string; account_name: string; account_number: string }
  qr_url: string
}

export const bookingApi = {
  getCourts: <T = Court[]>(params?: { sport_type?: string; venue_id?: number }) => {
    const query = new URLSearchParams()
    if (params?.sport_type && params.sport_type !== 'all')
      query.set('sport_type', params.sport_type)
    if (params?.venue_id) query.set('venue_id', String(params.venue_id))
    const qs = query.toString() ? `?${query.toString()}` : ''
    return api.get<T>(`/courts${qs}`)
  },

  getAvailability: (courtId: number, date: string) =>
    api.get<AvailabilitySlot[]>(`/availability?court_id=${courtId}&date=${date}`),

  getWalletBalance: () => api.get<{ balance: number; currency?: string }>('/wallet/balance'),

  createRecurringSeries: (payload: CreateRecurringSeriesPayload, key?: string) =>
    api.post<CreateRecurringSeriesResult>(
      '/recurring-series',
      payload,
      key ? { idempotencyKey: key } : undefined,
    ),

  previewRecurringSeries: (payload: RecurringSeriesPreviewPayload) =>
    api.post<RecurringSeriesPreview>('/recurring-series/preview', payload),

  getRecurringSeries: <T = { data: import('../types/recurring').RecurringSeries[] }>() =>
    api.get<T>('/recurring-series'),

  // Reservations & Quotes
  // The caller's own reservations across every organization (Laravel paginator).
  getMyReservations: (page = 1) =>
    api.get<{ data: import('../types/reservation').Reservation[]; total: number }>(
      `/me/reservations?page=${page}`,
    ),

  getReservation: <T = { reservation: import('../types/reservation').Reservation }>(code: string) =>
    api.get<T>(`/reservations/${code}`),

  getPriceQuote: <T = { quote: import('../types/reservation').PriceQuote }>(
    payload: import('../types/reservation').PriceQuotePayload,
  ) => api.post<T>('/price-quotes', payload),

  holdReservation: <T = { reservation: import('../types/reservation').Reservation }>(
    payload: import('../types/reservation').HoldReservationPayload,
  ) => api.post<T>('/reservations', payload),

  // Public vouchers that apply to the interval, default first (module 3 D10); neither call holds a use.
  getVouchers: (venueId: number, params: import('../types/reservation').PriceQuotePayload) =>
    api.get<{
      data: import('../types/reservation').VoucherQuote[]
      default_program_id: number | null
    }>(`/venues/${venueId}/vouchers`, { params }),

  checkVoucherCode: (
    venueId: number,
    params: import('../types/reservation').PriceQuotePayload & { code: string },
  ) =>
    api.get<{ data: import('../types/reservation').VoucherQuote }>(
      `/venues/${venueId}/vouchers/check`,
      { params },
    ),

  checkoutReservation: <T = { reservation: import('../types/reservation').Reservation }>(
    payload: import('../types/reservation').HoldReservationPayload,
  ) => api.post<T>('/reservations/checkout', payload),

  confirmReservation: <T = { reservation: import('../types/reservation').Reservation }>(
    code: string,
  ) => api.post<T>(`/reservations/${code}/confirm`),

  createReservationPayment: <
    T = {
      reservation: { code: string; status: string; release_reason: string | null }
      payment_attempt: ReservationPaymentAttempt
    },
  >(
    code: string,
  ) => api.post<T>(`/reservations/${code}/payment`),

  getReservationPayment: <
    T = {
      reservation: { code: string; status: string; release_reason: string | null }
      payment_attempt: ReservationPaymentAttempt | null
    },
  >(
    code: string,
  ) => api.get<T>(`/reservations/${code}/payment`),

  // Cancels the caller's own booking while it is pending payment; repeating it is harmless.
  cancelReservationPayment: (code: string) =>
    api.post<{ reservation: import('../types/reservation').Reservation }>(
      `/reservations/${code}/cancel-payment`,
    ),

  joinWaitlist: (payload: { court_id?: number; starts_at: string }) =>
    api.post('/waitlist', payload),
}
