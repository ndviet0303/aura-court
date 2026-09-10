import { computed, ref } from 'vue'
import { platformSubscriptionApi } from '../api/platformSubscriptionApi'
import type { PlatformPlan, PlatformSubscription, PaymentAttempt } from '../types'
import { errorMessage } from '@/core/utils/errorMessage'

export function usePlatformSubscription() {
  const plans = ref<PlatformPlan[]>([])
  const subscription = ref<PlatformSubscription | null>(null)
  const trialEligible = ref(false)
  const canTakeBookings = ref(false)
  const quotas = ref<{
    venues_count: number
    max_venues: number
    courts_count: number
    max_courts: number
    staff_count: number
    max_staff: number
  } | null>(null)

  const loading = ref(false)
  const activating = ref(false)
  const initiating = ref(false)
  const error = ref<string | null>(null)
  const notice = ref<string | null>(null)

  const hasActiveSubscription = computed(() => {
    return subscription.value?.status === 'active' && canTakeBookings.value
  })

  function clearMessages() {
    error.value = null
    notice.value = null
  }

  async function fetchPlans(): Promise<PlatformPlan[]> {
    clearMessages()
    try {
      const res = await platformSubscriptionApi.getPlans()
      plans.value = res.data || []
      return plans.value
    } catch (err) {
      error.value = errorMessage(err, 'Không thể tải danh sách gói dịch vụ.')
      return []
    }
  }

  async function fetchCurrent(organizationId: number): Promise<PlatformSubscription | null> {
    loading.value = true
    clearMessages()
    try {
      const res = await platformSubscriptionApi.getCurrentSubscription(organizationId)
      subscription.value = res.data
      trialEligible.value = Boolean(res.trial_eligible)
      canTakeBookings.value = Boolean(res.can_take_bookings)
      quotas.value = res.quotas || null
      return res.data
    } catch (err) {
      error.value = errorMessage(err, 'Không thể tải thông tin gói dịch vụ hiện tại.')
      return null
    } finally {
      loading.value = false
    }
  }

  async function activateTrial(organizationId: number, planCode: string): Promise<PlatformSubscription | null> {
    activating.value = true
    clearMessages()
    try {
      const res = await platformSubscriptionApi.activateTrial(organizationId, {
        plan_code: planCode,
        terms_version: 'v1.0',
      })
      subscription.value = res.data
      notice.value = res.message || 'Kích hoạt dùng thử thành công.'
      await fetchCurrent(organizationId)
      return res.data
    } catch (err) {
      error.value = errorMessage(err, 'Không thể kích hoạt dùng thử.')
      throw err
    } finally {
      activating.value = false
    }
  }

  async function initiatePayment(
    organizationId: number,
    planCode: string
  ): Promise<{ attempt: PaymentAttempt; checkoutUrl?: string; qrCodeUrl?: string } | null> {
    initiating.value = true
    clearMessages()
    try {
      const res = await platformSubscriptionApi.initiatePayment(organizationId, {
        plan_code: planCode,
        terms_version: 'v1.0',
      })
      return {
        attempt: res.data,
        checkoutUrl: res.checkout_url,
        qrCodeUrl: res.qr_code_url,
      }
    } catch (err) {
      error.value = errorMessage(err, 'Khởi tạo thanh toán thất bại.')
      throw err
    } finally {
      initiating.value = false
    }
  }

  /**
   * Browser return handler: only refreshes persisted server state!
   * Never fabricates client activation from URL query string.
   */
  async function confirmReturn(
    organizationId: number,
    attemptId?: number
  ): Promise<boolean> {
    loading.value = true
    clearMessages()
    try {
      if (attemptId) {
        try {
          await platformSubscriptionApi.confirmPayment({
            payment_attempt_id: attemptId,
          })
        } catch {
          // If already confirmed or callback handled it, refresh will verify
        }
      }
      const updated = await fetchCurrent(organizationId)
      if (updated?.status === 'active') {
        notice.value = 'Gói dịch vụ đã được kích hoạt thành công!'
        return true
      }
      return false
    } catch (err) {
      error.value = errorMessage(err, 'Xác nhận trạng thái thanh toán thất bại.')
      return false
    } finally {
      loading.value = false
    }
  }

  async function cancelAutoRenew(organizationId: number): Promise<boolean> {
    clearMessages()
    try {
      const res = await platformSubscriptionApi.cancelRenewal(organizationId)
      subscription.value = res.data
      notice.value = res.message || 'Đã hủy tự động gia hạn.'
      return true
    } catch (err) {
      error.value = errorMessage(err, 'Không thể hủy tự động gia hạn.')
      return false
    }
  }

  async function renew(organizationId: number): Promise<boolean> {
    clearMessages()
    try {
      const res = await platformSubscriptionApi.renew(organizationId)
      subscription.value = res.data
      notice.value = res.message || 'Gia hạn gói dịch vụ thành công.'
      return true
    } catch (err) {
      error.value = errorMessage(err, 'Gia hạn gói dịch vụ thất bại.')
      return false
    }
  }

  return {
    plans,
    subscription,
    trialEligible,
    canTakeBookings,
    quotas,
    loading,
    activating,
    initiating,
    error,
    notice,
    hasActiveSubscription,
    fetchPlans,
    fetchCurrent,
    activateTrial,
    initiatePayment,
    confirmReturn,
    cancelAutoRenew,
    renew,
  }
}
