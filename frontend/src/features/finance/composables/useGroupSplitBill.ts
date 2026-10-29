import { ref } from 'vue'
import { i18n } from '@/i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { financeApi } from '../api/financeApi'
import type { SplitGroup, SplitReservation } from '../types/split'

function isSplitGroup(value: unknown): value is SplitGroup {
  if (!value || typeof value !== 'object') return false
  const group = value as Partial<SplitGroup>
  return (
    Number.isSafeInteger(group.id) &&
    Number.isSafeInteger(group.expected_amount) &&
    Number.isSafeInteger(group.paid_amount) &&
    typeof group.status === 'string' &&
    typeof group.expires_at === 'string' &&
    Number.isFinite(Date.parse(group.expires_at)) &&
    !!group.reservation &&
    typeof group.reservation.code === 'string' &&
    group.reservation.total_amount === group.expected_amount &&
    Array.isArray(group.shares) &&
    group.shares.length >= 2 &&
    group.shares.every(
      (share) =>
        Number.isSafeInteger(share.id) &&
        Number.isSafeInteger(share.share_number) &&
        Number.isSafeInteger(share.amount) &&
        share.amount > 0 &&
        typeof share.status === 'string',
    ) &&
    group.shares.reduce((total, share) => total + share.amount, 0) === group.expected_amount
  )
}

// Whole VND per share; the first share (the host's) takes the remainder so the shares add up exactly.
export function splitEvenly(total: number, count: number): number[] {
  const base = Math.floor(total / count)
  return Array.from({ length: count }, (_, i) => (i === 0 ? total - base * (count - 1) : base))
}

export function useGroupSplitBill() {
  const reservation = ref<SplitReservation | null>(null)
  const group = ref<SplitGroup | null>(null)
  const loading = ref(false)
  const creating = ref(false)
  const error = ref('')
  const stale = ref(false)
  let latestRequest = 0
  let currentCode = ''

  async function loadSplit(code: string) {
    const request = ++latestRequest
    if (code !== currentCode) {
      currentCode = code
      reservation.value = null
      group.value = null
    }
    loading.value = true
    error.value = ''
    try {
      const response = await financeApi.getReservationSplit(code)
      if (request !== latestRequest) return
      if (response?.reservation?.code !== code)
        throw new Error(i18n.global.t('splitBill.loadFailed'))
      if (response.split !== null && !isSplitGroup(response.split)) {
        throw new Error(i18n.global.t('splitBill.invalidGroup'))
      }
      reservation.value = response.reservation
      group.value = response.split
      stale.value = false
    } catch (cause: unknown) {
      if (request !== latestRequest) return
      stale.value = group.value !== null
      error.value = errorMessage(cause, i18n.global.t('splitBill.loadFailed'))
    } finally {
      if (request === latestRequest) loading.value = false
    }
  }

  async function createSplit(count: number) {
    const target = reservation.value
    if (!target || creating.value) return
    creating.value = true
    error.value = ''
    try {
      const response = await financeApi.createReservationSplit(
        target.code,
        splitEvenly(target.total_amount, count),
      )
      if (!isSplitGroup(response?.split)) throw new Error(i18n.global.t('splitBill.invalidGroup'))
      if (target.code === currentCode) group.value = response.split
    } catch (cause: unknown) {
      const message = errorMessage(cause, i18n.global.t('splitBill.createFailed'))
      // The group may exist anyway (created from another tab, or a lost response): show the server state.
      await loadSplit(target.code)
      if (!group.value) error.value = message
    } finally {
      creating.value = false
    }
  }

  return { reservation, group, loading, creating, error, stale, loadSplit, createSplit }
}
