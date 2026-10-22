import type { ApiError } from '@/core/types/api'
import { errorMessage } from '@/core/utils/errorMessage'
import { i18n } from '@/i18n'
import type { PriceQuote, Reservation } from '../types/reservation'

type Displayable = Pick<Reservation, 'code' | 'status' | 'legacy_mapping' | 'metadata'>

// A mirrored legacy booking is stored as `LEGACY-<code>`; customers only ever see the original code.
export const displayCode = (r: Displayable): string => r.legacy_mapping?.[0]?.legacy_code ?? r.code

// Check-in, check-out and no-show are written to metadata.operational_status while status stays confirmed.
export const displayStatus = (r: Displayable): string => {
  if (r.status === 'legacy') return r.legacy_mapping?.[0]?.legacy_status ?? r.status
  return r.status === 'confirmed' ? (r.metadata?.operational_status ?? r.status) : r.status
}

// Report FR-79 order status labels, as booking.* i18n keys; any other status shows as is.
const STATUS_LABELS: Record<string, string> = {
  held: 'statusPending',
  confirmed: 'statusConfirmed',
  in_use: 'statusInUse',
  completed: 'statusCompleted',
  no_show: 'statusNoShow',
  cancelled: 'statusCancelled',
  expired: 'statusExpired',
  refunded: 'statusRefunded',
}
export const statusLabel = (r: Displayable, t: (key: string) => string): string => {
  const status = displayStatus(r)
  // Only the customer's own cancellation of a pending booking reads as a cancelled payment.
  if (status === 'cancelled' && r.metadata?.release_reason === 'customer_payment_cancel') {
    return t('booking.statusPaymentCancelled')
  }
  const key = STATUS_LABELS[status]
  return key ? t(`booking.${key}`) : status
}

// The order QR is shown for check-in and check-out only (report FR-80).
export const showsQr = (r: Displayable): boolean =>
  ['confirmed', 'in_use'].includes(displayStatus(r))

// Mirrors POST /reviews eligibility: reservation statuses, or the legacy booking status for a terminal mirror.
export const canReview = (r: Displayable): boolean =>
  ['confirmed', 'in_use', 'completed', 'no_show'].includes(displayStatus(r))

// The discount lines (i18n label and amount) of a booking, for the price breakdowns: its voucher
// (module 3 D17), or the coupon and membership discounts an older booking's quote recorded.
export const quoteDiscounts = (
  snapshot?: PriceQuote['benefit_snapshot'],
  voucher?: Reservation['voucher'],
) =>
  (
    [
      ['reservations.calculator.voucherDiscount', voucher?.discount],
      ['reservations.calculator.membershipDiscount', snapshot?.membership_discount_amount],
      ['reservations.calculator.couponDiscount', snapshot?.coupon_discount_amount],
    ] as const
  ).flatMap(([label, amount]) => (amount ? [{ label, amount }] : []))

// Same rule as the backend User::PHONE_PATTERN; without it a booking is refused with PHONE_REQUIRED.
export const validBookerPhone = (phone?: string | null): boolean =>
  /^(0\d{9}|\+84\d{9})$/.test(phone ?? '')

// A booking's length in words, "1 giờ 30 phút" (design D14), never a count of slots.
export function formatDuration(minutes: number): string {
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60
  return [
    hours ? i18n.global.t('common.durationHours', { count: hours }) : '',
    rest ? i18n.global.t('common.durationMinutes', { count: rest }) : '',
  ]
    .filter(Boolean)
    .join(' ')
}

const BOOKING_ERROR_KEYS: Record<string, string> = {
  PHONE_REQUIRED: 'reservations.messages.phoneRequired',
  PAYMENT_WINDOW_CLOSED: 'reservations.messages.paymentWindowClosed',
  RESERVATION_NOT_PENDING: 'reservations.messages.notPending',
}

// Booking refusals with their own copy; any other failure reads as the shared error message.
export function bookingErrorMessage(cause: unknown, fallback?: string): string {
  const key = BOOKING_ERROR_KEYS[String((cause as ApiError | null)?.code)]
  return key ? i18n.global.t(key) : errorMessage(cause, fallback)
}
