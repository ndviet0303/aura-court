import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ApiError } from '@/core/types/api'
import { errorMessage } from '@/core/utils/errorMessage'
import { marketplaceApi } from '../api/marketplaceApi'
import type { ResaleState } from '../types/marketplace'
import { usePolling } from './usePolling'

export type ResaleBuyerPhase = 'idle' | 'holding' | 'sold' | 'expired'

/** Where the buyer stands. Only the verified bank receipt moves a hold to sold. */
export function buyerPhase(state: ResaleState | null): ResaleBuyerPhase {
  if (!state) return 'idle'
  if (state.role === 'buyer' && state.status === 'sold' && state.transfer) return 'sold'
  if (state.lock?.held_by_me) return 'holding'
  if (state.last_release?.by === 'expiry') return 'expired'
  return 'idle'
}

const statusOf = (e: unknown) => (e as ApiError | null)?.status

/** Buyer side of a resale: hold the slot, pay the venue owner's QR, wait for the receipt. */
export function useResaleLock(onChanged: () => void) {
  const { t } = useI18n()
  const listingId = ref<number | null>(null)
  const state = ref<ResaleState | null>(null)
  const busy = ref(false)
  const error = ref('')
  const now = ref(Date.now())

  const phase = computed(() => buyerPhase(state.value))
  const remainingSeconds = computed(() => {
    const until = state.value?.lock?.locked_until
    return until ? Math.max(0, Math.floor((Date.parse(until) - now.value) / 1000)) : 0
  })

  // The receipt arrives through the bank webhook, so the hold polls until it turns sold.
  usePolling(
    () => phase.value === 'holding',
    5000,
    () => refresh(),
  )
  usePolling(
    () => phase.value === 'holding',
    1000,
    () => (now.value = Date.now()),
  )
  // The server releases an expired hold lazily; ask once the countdown runs out (not when a sale ends it).
  watch(remainingSeconds, (left, before) => {
    if (left === 0 && before > 0 && phase.value === 'holding') void refresh()
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
      // A 409 explains why the hold failed (taken, another hold, no verified owner account).
      error.value = errorMessage(
        e,
        t(statusOf(e) === 409 ? 'resale.p2p.conflict' : 'resale.p2p.actionFailed'),
      )
    } finally {
      busy.value = false
    }
  }

  const lock = (acceptFreeTransfer = false) =>
    run((id) => marketplaceApi.lockListing(id, acceptFreeTransfer))
  const cancel = () => run((id) => marketplaceApi.releaseLock(id))

  return { state, phase, remainingSeconds, busy, error, open, close, refresh, lock, cancel }
}
