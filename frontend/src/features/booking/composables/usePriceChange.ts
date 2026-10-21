import { ref } from 'vue'
import type { ApiError } from '@/core/types/api'
import type { PriceChange } from '../types/reservation'

function priceChangeOf(cause: unknown): PriceChange | null {
  const e = cause as (ApiError & Partial<PriceChange>) | null
  return e?.code === 'PRICE_CHANGED' && typeof e.total === 'number'
    ? { rental: e.rental ?? e.total, discount: e.discount ?? 0, total: e.total }
    : null
}

/**
 * A 409 PRICE_CHANGED keeps its new amounts on screen. Nothing is resent until the customer confirms
 * them; the resubmission then carries the new total as expected_total.
 */
export function usePriceChange() {
  const priceChange = ref<PriceChange | null>(null)
  let resubmit: ((total: number) => unknown) | null = null

  /** True when the failure was a PRICE_CHANGED; `again` repeats the submission at the new total. */
  function caught(cause: unknown, again: (total: number) => unknown): boolean {
    priceChange.value = priceChangeOf(cause)
    resubmit = priceChange.value ? again : null
    return priceChange.value !== null
  }

  function confirm() {
    const total = priceChange.value?.total
    priceChange.value = null
    return total === undefined ? undefined : resubmit?.(total)
  }

  function clear() {
    priceChange.value = null
  }

  return { priceChange, caught, confirm, clear }
}
