import type { V2HoldReservationPayload, V2Reservation } from '../types/v2Reservation'

type Displayable = Pick<V2Reservation, 'code' | 'status' | 'legacy_mapping'>

// A mirrored V1 booking is stored as `LEGACY-<code>`; customers only ever see the original code.
export const displayCode = (r: Displayable): string => r.legacy_mapping?.[0]?.legacy_code ?? r.code

export const displayStatus = (r: Displayable): string =>
  r.status === 'legacy' ? (r.legacy_mapping?.[0]?.legacy_status ?? r.status) : r.status

// Mirrors POST /reviews eligibility: V2 statuses, or the legacy booking status for a terminal mirror.
export const canReview = (r: Displayable): boolean =>
  ['confirmed', 'in_use', 'completed', 'no_show'].includes(displayStatus(r))

export type LoyaltySelection = {
  loyalty_points?: number
  loyalty_policy_version?: number | null
  // Stable per court/slots from LoyaltyPointSelector; sent as the Idempotency-Key so a retry replays.
  idempotency_key?: string
} | null

// The checkout API rejects loyalty_points < 1, so loyalty fields are sent only for a real redemption.
export function loyaltyFields(selection?: LoyaltySelection): V2HoldReservationPayload {
  const points = selection?.loyalty_points ?? 0
  if (points <= 0) return {}
  return {
    loyalty_points: points,
    loyalty_policy_version: selection?.loyalty_policy_version ?? undefined,
  }
}
