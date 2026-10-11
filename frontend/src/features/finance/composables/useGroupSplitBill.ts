import { ref } from 'vue'
import { i18n } from '@/i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { financeApi } from '../api/financeApi'
import type { SplitGroup } from '../types/split'

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

export function useGroupSplitBill() {
  const group = ref<SplitGroup | null>(null)
  const loading = ref(false)
  const error = ref('')
  const stale = ref(false)
  let latestRequest = 0

  async function loadSplitGroup(identifier: string | undefined) {
    const request = ++latestRequest
    loading.value = false
    if (!identifier) {
      group.value = null
      stale.value = false
      error.value = i18n.global.t('splitBill.noGroupSelected')
      return
    }

    // The existing API identifies a split by numeric group ID, not a booking code.
    const groupId = Number(identifier)
    if (!/^[1-9]\d*$/.test(identifier) || !Number.isSafeInteger(groupId)) {
      group.value = null
      stale.value = false
      error.value = i18n.global.t('splitBill.bookingLookupUnavailable')
      return
    }

    if (group.value?.id !== groupId) group.value = null
    loading.value = true
    error.value = ''
    try {
      const response = await financeApi.getSplitGroup(groupId)
      if (request !== latestRequest) return
      if (!isSplitGroup(response?.split) || response.split.id !== groupId) {
        throw new Error(i18n.global.t('splitBill.invalidGroup'))
      }
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

  return { group, loading, error, stale, loadSplitGroup }
}
