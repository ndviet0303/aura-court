import { ref } from 'vue'
import type { ApiError } from '@/core/types/api'
import { bookingApi } from '../api/bookingApi'
import type { PriceQuotePayload, VoucherInvalid, VoucherQuote } from '../types/reservation'
import { bookingErrorMessage } from '../utils/reservation'

function voucherInvalidOf(cause: unknown): VoucherInvalid | null {
  const e = cause as (ApiError & Partial<VoucherInvalid>) | null
  return e?.code === 'VOUCHER_INVALID' && typeof e.total === 'number'
    ? {
        reason: e.reason ?? '',
        message: e.message ?? '',
        rental: e.rental ?? e.total,
        total: e.total,
      }
    : null
}

/**
 * The voucher a booking applies (module 3 D10, D20). Picking, entering or opening a code holds nothing.
 * Another court or time prices the choice again: a voucher the customer picked or entered stays while it
 * still applies, otherwise it is dropped with a notice; with none, or the default, the default is picked.
 * A 422 VOUCHER_INVALID at checkout waits until the customer goes on without it or picks another.
 */
export function useVoucherChoice() {
  const vouchers = ref<VoucherQuote[]>([])
  const voucher = ref<VoucherQuote | null>(null)
  const loading = ref(false)
  // The name or code of a voucher dropped because it no longer applies.
  const dropped = ref('')
  const codeError = ref('')
  const invalid = ref<VoucherInvalid | null>(null)
  let picked = false
  let venueId = 0
  let interval: PriceQuotePayload | null = null
  // A ?voucher= code from a QR link, tried on every interval until it applies (design D9).
  let linkCode = ''
  let request = 0
  let resubmit: ((total: number) => unknown) | null = null

  async function priceCode(code: string): Promise<VoucherQuote> {
    return (await bookingApi.checkVoucherCode(venueId, { ...interval!, code })).data
  }

  async function load(venue: number | null, payload: PriceQuotePayload | null) {
    const id = ++request
    const kept = picked ? voucher.value : null
    venueId = venue ?? 0
    interval = payload
    voucher.value = null
    vouchers.value = []
    dropped.value = ''
    if (!venueId || !interval) return
    loading.value = true
    try {
      const res = await bookingApi.getVouchers(venueId, interval)
      let next: VoucherQuote | null = null
      const code = kept?.code ?? (kept ? '' : linkCode)
      if (code) {
        next = await priceCode(code).catch((cause) => {
          if (!kept && id === request) codeError.value = bookingErrorMessage(cause)
          return null
        })
      } else if (kept) {
        next = res.data.find((v) => v.id === kept.id) ?? null
      }
      if (id !== request) return
      vouchers.value = res.data
      if (kept && !next) dropped.value = kept.code ?? kept.name
      if (next && !kept) linkCode = ''
      picked = next !== null
      voucher.value = next ?? res.data[0] ?? null
    } catch {
      // No list: the booking goes ahead without a voucher.
    } finally {
      if (id === request) loading.value = false
    }
  }

  function choose(next: VoucherQuote | null) {
    voucher.value = next
    picked = next !== null
    codeError.value = ''
  }

  async function enterCode(code: string) {
    codeError.value = ''
    if (!venueId || !interval || !code.trim()) return
    const id = request
    try {
      const next = await priceCode(code.trim())
      if (id === request) choose(next)
    } catch (cause) {
      if (id === request) codeError.value = bookingErrorMessage(cause)
    }
  }

  function setLink(code: string) {
    linkCode = code.trim()
  }

  // What checkout sends for the voucher: a private code or a public program, never both.
  function payload() {
    if (!voucher.value) return {}
    return voucher.value.code
      ? { voucher_code: voucher.value.code }
      : { voucher_program_id: voucher.value.id }
  }

  /** True when the failure was a VOUCHER_INVALID; `again` repeats the submission at a given total. */
  function caught(cause: unknown, again: (total: number) => unknown): boolean {
    invalid.value = voucherInvalidOf(cause)
    resubmit = invalid.value ? again : null
    return invalid.value !== null
  }

  function continueWithout() {
    const total = invalid.value?.total
    invalid.value = null
    choose(null)
    return total === undefined ? undefined : resubmit?.(total)
  }

  function chooseOther() {
    invalid.value = null
    return load(venueId, interval)
  }

  function clear() {
    request++
    picked = false
    voucher.value = null
    vouchers.value = []
    loading.value = false
    dropped.value = ''
    codeError.value = ''
    invalid.value = null
  }

  return {
    vouchers,
    voucher,
    loading,
    dropped,
    codeError,
    invalid,
    load,
    choose,
    enterCode,
    setLink,
    payload,
    caught,
    continueWithout,
    chooseOther,
    clear,
  }
}
