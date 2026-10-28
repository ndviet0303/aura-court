import type { TransferCandidate, TransferItemRefusal } from '../types/transfer'

export type PriceProblem = 'required' | 'invalid' | 'overCap'

/** BR-181: a listed or agreed price is whole VND, not above what was paid for that booking after voucher. */
export function priceProblem(price: number | null | undefined, max: number): PriceProblem | null {
  if (price === null || price === undefined || Number.isNaN(price)) return 'required'
  if (!Number.isInteger(price) || price < 0) return 'invalid'
  return price > max ? 'overCap' : null
}

/** BR-179: every still eligible booking of the same recurring submission ("all remaining occurrences"). */
export function remainingInGroup(candidates: TransferCandidate[], group: string): number[] {
  return candidates
    .filter((c) => c.eligible && c.recurring_group === group)
    .map((c) => c.reservation_id)
}

/** Per-booking reasons of a 422 answer, keyed by reservation id; empty for any other failure. */
export function itemRefusals(cause: unknown): Record<number, string> {
  const items = (cause as { items?: unknown } | null)?.items
  if (!Array.isArray(items)) return {}
  return Object.fromEntries(
    (items as TransferItemRefusal[]).map((item) => [item.reservation_id, item.message]),
  )
}
