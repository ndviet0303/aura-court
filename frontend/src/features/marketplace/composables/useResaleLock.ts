import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { ApiError } from '@/core/types/api'
import { errorMessage } from '@/core/utils/errorMessage'
import { marketplaceApi } from '../api/marketplaceApi'
import type { ResaleState } from '../types/marketplace'
import { usePolling } from './usePolling'

export type ResaleBuyerPhase = 'idle' | 'holding' | 'reported' | 'sold' | 'unresolved' | 'expired'

/** Where the buyer stands. A payment report alone is never treated as a completed payment. */
export function buyerPhase(state: ResaleState | null): ResaleBuyerPhase {
  if (!state) return 'idle'
  if (state.role === 'buyer' && state.status === 'sold' && state.transfer) return 'sold'
  if (state.lock?.held_by_me) return state.lock.payment_reported_at ? 'reported' : 'holding'
  const release = state.last_release
  if (release && (release.by === 'seller' || release.payment_reported)) return 'unresolved'
  if (release?.by === 'expiry') return 'expired'
  return 'idle'
}

const statusOf = (e: unknown) => (e as ApiError | null)?.status

/** Buyer side of a peer-to-peer resale: lock, pay the seller directly, report, wait for the seller. */
export function useResaleLock(onChanged: () => void) {
  const { t } = useI18n()
  const listingId = ref<number | null>(null)
  const state = ref<ResaleState | null>(null)
  const busy = ref(false)
  const error = ref('')
  const disputeOpened = ref(false)
  const now = ref(Date.now())

  const phase = computed(() => buyerPhase(state.value))
  const remainingSeconds = computed(() => {
    const until = state.value?.lock?.locked_until
    return until ? Math.max(0, Math.floor((Date.parse(until) - now.value) / 1000)) : 0
  })

  usePolling(
    () => phase.value === 'holding' || phase.value === 'reported',
    5000,
    () => refresh(),
  )
  usePolling(
    () => phase.value === 'holding',
    1000,
    () => (now.value = Date.now()),
  )
  // The server releases an unreported lock lazily; ask once the countdown runs out.
  watch(remainingSeconds, (left, before) => {
    if (left === 0 && before > 0) void refresh()
  })
  watch(phase, (next) => {
    if (next === 'sold') onChanged()
  })

  async function refresh() {
    const id = listingId.value
    if (id === null) return
    try {
      const next = await marketplaceApi.getListingState(id)
      if (listingId.value === id) state.value = next
    } catch (e) {
      if (listingId.value !== id) return
      if (statusOf(e) === 404) state.value = null
      else error.value = errorMessage(e, t('resale.p2p.loadFailed'))
    }
  }

  async function open(id: number) {
    listingId.value = id
    state.value = null
    error.value = ''
    disputeOpened.value = false
    now.value = Date.now()
    await refresh()
  }

  function close() {
    listingId.value = null
    state.value = null
  }

  async function run(action: (id: number) => Promise<ResaleState>) {
    const id = listingId.value
    if (id === null || busy.value) return
    busy.value = true
    error.value = ''
    try {
      const next = await action(id)
      if (listingId.value === id) state.value = next
      now.value = Date.now()
      onChanged()
    } catch (e) {
      error.value =
        statusOf(e) === 409
          ? t('resale.p2p.conflict')
          : errorMessage(e, t('resale.p2p.actionFailed'))
    } finally {
      busy.value = false
    }
  }

  const lock = (acceptFreeTransfer = false) =>
    run((id) => marketplaceApi.lockListing(id, acceptFreeTransfer))
  const reportPaid = () => run(marketplaceApi.reportPaymentSent)
  const cancel = () => run((id) => marketplaceApi.releaseLock(id))

  async function openDispute() {
    const id = listingId.value
    if (id === null || busy.value) return
    busy.value = true
    error.value = ''
    try {
      const amount = formatCurrency(state.value?.listing.asking_price ?? 0)
      await marketplaceApi.openPaymentDispute(
        id,
        t('resale.p2p.disputeTitle', { id }),
        t('resale.p2p.disputeSummary', { id, amount }),
      )
      disputeOpened.value = true
    } catch (e) {
      error.value = errorMessage(e, t('resale.p2p.actionFailed'))
    } finally {
      busy.value = false
    }
  }

  return {
    state,
    phase,
    remainingSeconds,
    busy,
    error,
    disputeOpened,
    open,
    close,
    refresh,
    lock,
    reportPaid,
    cancel,
    openDispute,
  }
}

/** Seller side: confirm the money arrived (transfers the hour) or report it did not (releases the lock). */
export function useResaleSellerActions(onChanged: () => void) {
  const { t } = useI18n()
  const busyId = ref<number | null>(null)
  const error = ref('')

  async function run(listingId: number, action: () => Promise<ResaleState>) {
    if (busyId.value !== null) return
    busyId.value = listingId
    error.value = ''
    try {
      await action()
      onChanged()
    } catch (e) {
      error.value = errorMessage(e, t('resale.p2p.actionFailed'))
    } finally {
      busyId.value = null
    }
  }

  function confirmReceipt(listingId: number) {
    if (!window.confirm(t('resale.p2p.confirmReceiptAsk'))) return
    return run(listingId, () => marketplaceApi.confirmReceipt(listingId))
  }

  function reportNotReceived(listingId: number) {
    const reason = window.prompt(t('resale.p2p.notReceivedAsk'))
    if (reason === null) return
    return run(listingId, () => marketplaceApi.releaseLock(listingId, reason.trim() || undefined))
  }

  return { busyId, error, confirmReceipt, reportNotReceived }
}
