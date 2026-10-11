import { ref, computed } from 'vue'
import { bookingApi } from '../api/bookingApi'
import type { LoyaltyPolicy, LoyaltyPreviewPayload, LoyaltyPreviewResponse } from '../types/loyalty'
import { idempotencyKey } from '@/core/api/client'
import { errorMessage } from '@/core/utils/errorMessage'

export function useLoyaltyPreview() {
  const organizationId = ref<number | null>(null)
  const balance = ref(0)
  const policy = ref<LoyaltyPolicy | null>(null)
  const pointsSelected = ref(0)
  const discountVnd = ref(0)
  const remainingPayableVnd = ref(0)
  const maxUsablePoints = ref(0)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const noPolicy = ref(false)
  const currentKey = ref(idempotencyKey())

  let latestRequestId = 0

  /**
   * Reset / regenerate idempotency key when logical checkout parameters change (e.g. court or slots change)
   */
  function refreshIdempotencyKey(): string {
    currentKey.value = idempotencyKey()
    return currentKey.value
  }

  /**
   * Fetches authoritative loyalty preview from backend.
   * Guarded against stale in-flight responses.
   */
  async function fetchPreview(
    params: LoyaltyPreviewPayload,
  ): Promise<LoyaltyPreviewResponse | null> {
    const requestId = ++latestRequestId
    loading.value = true
    error.value = null

    try {
      const res = await bookingApi.previewLoyalty(params)

      // Stale response guard: if a newer request was issued, discard
      if (requestId !== latestRequestId) {
        return null
      }

      if (res) {
        organizationId.value = res.organization_id ?? null
        balance.value = res.balance ?? 0
        policy.value = res.policy ?? null
        pointsSelected.value = res.points_selected ?? 0
        discountVnd.value = res.discount_vnd ?? 0
        remainingPayableVnd.value = res.remaining_payable_vnd ?? 0
        maxUsablePoints.value = res.max_usable_points ?? 0
        noPolicy.value = res.policy === null
        return res
      }
      return null
    } catch (err: unknown) {
      if (requestId === latestRequestId) {
        error.value = errorMessage(err, 'Không thể tính toán giảm giá điểm.')
      }
      return null
    } finally {
      if (requestId === latestRequestId) {
        loading.value = false
      }
    }
  }

  function clearPoints(): void {
    pointsSelected.value = 0
    discountVnd.value = 0
  }

  function setQuickPoints(percentage: number): number {
    if (!maxUsablePoints.value || percentage <= 0) {
      clearPoints()
      return 0
    }
    const calculated = Math.min(
      maxUsablePoints.value,
      Math.floor((maxUsablePoints.value * percentage) / 100),
    )
    pointsSelected.value = calculated
    return calculated
  }

  const hasPoints = computed(() => balance.value > 0)
  const hasPolicy = computed(() => !noPolicy.value && policy.value !== null)
  const isAvailable = computed(
    () => hasPoints.value && hasPolicy.value && maxUsablePoints.value > 0,
  )

  return {
    organizationId,
    balance,
    policy,
    pointsSelected,
    discountVnd,
    remainingPayableVnd,
    maxUsablePoints,
    loading,
    error,
    noPolicy,
    idempotencyKey: currentKey,
    hasPoints,
    hasPolicy,
    isAvailable,
    fetchPreview,
    clearPoints,
    setQuickPoints,
    refreshIdempotencyKey,
  }
}
