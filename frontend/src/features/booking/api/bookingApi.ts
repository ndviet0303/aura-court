import { api } from '@/core/api/client'
import type { Court, AvailabilitySlot } from '@/features/court/types/court'
import type {
  RefundQuote,
  RefundObligation,
  CreateRefundRequestPayload,
  ReschedulePreview,
  ReschedulePreviewPayload,
  RescheduleResult,
  CreateReschedulePayload,
} from '../types/reservationChanges'
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

  previewLoyalty: (payload: import('../types/loyalty').LoyaltyPreviewPayload) =>
    api.post<import('../types/loyalty').LoyaltyPreviewResponse>('/loyalty/preview', payload),

  submitReview: (reservationCode: string, payload: { rating: number; body?: string }) =>
    api.post('/reviews', { reservation_code: reservationCode, ...payload }),

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
  getMyReservations: () =>
    api.get<{ data: import('../types/reservation').Reservation[] }>('/me/reservations'),

  getReservation: <T = { reservation: import('../types/reservation').Reservation }>(code: string) =>
    api.get<T>(`/reservations/${code}`),

  getPriceQuote: <T = { quote: import('../types/reservation').PriceQuote }>(
    payload: import('../types/reservation').PriceQuotePayload,
  ) => api.post<T>('/price-quotes', payload),

  holdReservation: <T = { reservation: import('../types/reservation').Reservation }>(
    payload: import('../types/reservation').HoldReservationPayload,
  ) => api.post<T>('/reservations', payload),

  checkoutReservation: <T = { reservation: import('../types/reservation').Reservation }>(
    payload: import('../types/reservation').HoldReservationPayload,
    key?: string,
  ) => api.post<T>('/reservations/checkout', payload, key ? { idempotencyKey: key } : undefined),

  confirmReservation: <T = { reservation: import('../types/reservation').Reservation }>(
    code: string,
  ) => api.post<T>(`/reservations/${code}/confirm`),

  createReservationPayment: <
    T = {
      reservation: { code: string; status: string }
      payment_attempt: ReservationPaymentAttempt
    },
  >(
    code: string,
  ) => api.post<T>(`/reservations/${code}/payment`),

  getReservationPayment: <
    T = {
      reservation: { code: string; status: string }
      payment_attempt: ReservationPaymentAttempt | null
    },
  >(
    code: string,
  ) => api.get<T>(`/reservations/${code}/payment`),

  // --- Cancellation / partial-cancel refund + reschedule (policy-aware flows, by reservation code) ---

  getRefundQuote: <T = { data: RefundQuote }>(code: string, slotIds: number[]) => {
    const query = slotIds.map((id) => `slot_ids[]=${encodeURIComponent(String(id))}`).join('&')
    return api.get<T>(`/reservations/${code}/refund-quote?${query}`)
  },

  createRefundRequest: <T = { data: RefundObligation }>(
    code: string,
    payload: CreateRefundRequestPayload,
  ) =>
    api.post<T>(`/reservations/${code}/refund-requests`, payload, {
      idempotencyKey: payload.idempotency_key,
    }),

  previewReschedule: <T = { data: ReschedulePreview }>(
    code: string,
    payload: ReschedulePreviewPayload,
  ) => api.post<T>(`/reservations/${code}/reschedule-preview`, payload),

  createRescheduleRequest: <T = { data: RescheduleResult }>(
    code: string,
    payload: CreateReschedulePayload,
  ) =>
    api.post<T>(`/reservations/${code}/reschedule-requests`, payload, {
      idempotencyKey: payload.idempotency_key,
    }),

  joinWaitlist: (payload: { court_id?: number; starts_at: string }) =>
    api.post('/waitlist', payload),
}
