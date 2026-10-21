import type { ApiError } from '@/core/types/api'
import { errorMessage } from '@/core/utils/errorMessage'
import { i18n } from '@/i18n'
import type { HoldReservationPayload, PriceQuote, Reservation } from '../types/reservation'

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

// The discount lines (i18n label and amount) a priced quote carries, for the price breakdowns.
export const quoteDiscounts = (snapshot?: PriceQuote['benefit_snapshot']) =>
  (
    [
      ['reservations.calculator.membershipDiscount', snapshot?.membership_discount_amount],
      ['reservations.calculator.couponDiscount', snapshot?.coupon_discount_amount],
    ] as const
  ).flatMap(([label, amount]) => (amount ? [{ label, amount }] : []))

export type LoyaltySelection = {
  loyalty_points?: number
  loyalty_policy_version?: number | null
  // Stable per court/slots from LoyaltyPointSelector; sent as the Idempotency-Key so a retry replays.
  idempotency_key?: string
  discount_vnd?: number
} | null

// The checkout API rejects loyalty_points < 1, so loyalty fields are sent only for a real redemption.
export function loyaltyFields(
  selection?: LoyaltySelection,
): Pick<HoldReservationPayload, 'loyalty_points' | 'loyalty_policy_version'> {
  const points = selection?.loyalty_points ?? 0
  if (points <= 0) return {}
  return {
    loyalty_points: points,
    loyalty_policy_version: selection?.loyalty_policy_version ?? undefined,
  }
}

// The amount payable shown before submitting and sent as expected_total: the priced total less the
// loyalty discount of a real redemption.
export const amountPayable = (total: number, loyalty?: LoyaltySelection): number =>
  Math.max(0, total - ((loyalty?.loyalty_points ?? 0) > 0 ? (loyalty?.discount_vnd ?? 0) : 0))

// Same rule as the backend User::PHONE_PATTERN; without it a booking is refused with PHONE_REQUIRED.
export const validBookerPhone = (phone?: string | null): boolean =>
  /^(0\d{9}|\+84\d{9})$/.test(phone ?? '')

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
