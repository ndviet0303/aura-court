import { computed, ref } from 'vue'
import { platformSubscriptionApi } from '../api/platformSubscriptionApi'
import type { PlatformPlan, PlatformSubscription, PaymentAttempt, SubscriptionQuotaUsage } from '../types'
import { errorMessage } from '@/core/utils/errorMessage'

export function usePlatformSubscription() {
  const plans = ref<PlatformPlan[]>([])
  const subscription = ref<PlatformSubscription | null>(null)
  const paymentAttempt = ref<PaymentAttempt | null>(null)
  const trialEligible = ref(false)
  const canTakeBookings = ref(false)
  const quotas = ref<SubscriptionQuotaUsage | null>(null)
  const loading = ref(false)
  const activating = ref(false)
  const initiating = ref(false)
  const error = ref<string | null>(null)
  const notice = ref<string | null>(null)

  const hasActiveSubscription = computed(() => subscription.value?.is_valid === true && canTakeBookings.value)

  function clearMessages() {
    error.value = null
    notice.value = null
  }

  async function fetchPlans(): Promise<PlatformPlan[]> {
    clearMessages()
    try {
      const response = await platformSubscriptionApi.getPlans()
      plans.value = response.data || []
      return plans.value
    } catch (cause) {
      error.value = errorMessage(cause, 'Không thể tải danh sách gói dịch vụ.')
      return []
    }
  }

  async function fetchCurrent(organizationId: number): Promise<PlatformSubscription | null> {
    loading.value = true
    clearMessages()
    try {
      const response = await platformSubscriptionApi.getCurrentSubscription(organizationId)
      subscription.value = response.data
      paymentAttempt.value = response.payment_attempt
      trialEligible.value = response.trial_eligible
      canTakeBookings.value = response.can_take_bookings
      quotas.value = response.quotas
      return response.data
    } catch (cause) {
      error.value = errorMessage(cause, 'Không thể tải thông tin gói dịch vụ hiện tại.')
      return null
    } finally {
      loading.value = false
    }
  }

  async function selectPlan(organizationId: number, plan: PlatformPlan) {
    return platformSubscriptionApi.selectPlan(organizationId, {
      plan_id: plan.id,
      accepted_terms_version: plan.terms.version,
    })
  }

  async function activateTrial(organizationId: number, plan: PlatformPlan): Promise<PlatformSubscription | null> {
    activating.value = true
    clearMessages()
    try {
      const selection = await selectPlan(organizationId, plan)
      const response = await platformSubscriptionApi.activateTrial(organizationId, {
        plan_id: selection.data.plan_id,
        accepted_terms_version: selection.data.accepted_terms_version,
      })
      await fetchCurrent(organizationId)
      notice.value = response.message || 'Kích hoạt dùng thử thành công.'
      return response.data
    } catch (cause) {
      error.value = errorMessage(cause, 'Không thể kích hoạt dùng thử.')
      throw cause
    } finally {
      activating.value = false
    }
  }

  async function initiatePayment(
    organizationId: number,
    plan: PlatformPlan,
    returnUrl: string,
    cancelUrl: string
  ): Promise<PaymentAttempt | null> {
    initiating.value = true
    clearMessages()
    try {
      const selection = await selectPlan(organizationId, plan)
      const response = await platformSubscriptionApi.initiatePayment(organizationId, {
        plan_id: selection.data.plan_id,
        accepted_terms_version: selection.data.accepted_terms_version,
        return_url: returnUrl,
        cancel_url: cancelUrl,
      })
      paymentAttempt.value = response.data
      await fetchCurrent(organizationId)
      notice.value = response.message || 'Đã tạo hướng dẫn thanh toán. Trạng thái sẽ cập nhật sau khi máy chủ xác minh.'
      return paymentAttempt.value
    } catch (cause) {
      error.value = errorMessage(cause, 'Khởi tạo thanh toán thất bại.')
      throw cause
    } finally {
      initiating.value = false
    }
  }

  async function confirmReturn(organizationId: number): Promise<boolean> {
    const updated = await fetchCurrent(organizationId)
    if (!updated && error.value) return false
    if (updated?.status === 'active' && updated.is_valid && paymentAttempt.value?.status === 'succeeded') {
      notice.value = 'Máy chủ đã xác nhận thanh toán và kích hoạt gói dịch vụ.'
      return true
    }
    notice.value = 'Thanh toán đang chờ máy chủ xác minh. Trạng thái hiện tại vẫn được giữ nguyên.'
    return false
  }

  async function cancelAutoRenew(organizationId: number): Promise<boolean> {
    clearMessages()
    try {
      const response = await platformSubscriptionApi.cancelRenewal(organizationId)
      await fetchCurrent(organizationId)
      notice.value = response.message || 'Đã hủy tự động gia hạn.'
      return true
    } catch (cause) {
      error.value = errorMessage(cause, 'Không thể hủy tự động gia hạn.')
      return false
    }
  }

  async function renew(
    organizationId: number,
    returnUrl: string,
    cancelUrl: string
  ): Promise<PaymentAttempt | null> {
    clearMessages()
    try {
      const response = await platformSubscriptionApi.renew(organizationId, {
        return_url: returnUrl,
        cancel_url: cancelUrl,
      })
      paymentAttempt.value = response.data
      await fetchCurrent(organizationId)
      notice.value = response.message || 'Đã tạo hướng dẫn gia hạn. Trạng thái sẽ cập nhật sau khi máy chủ xác minh.'
      return paymentAttempt.value
    } catch (cause) {
      error.value = errorMessage(cause, 'Không thể khởi tạo gia hạn.')
      return null
    }
  }

  return {
    plans,
    subscription,
    paymentAttempt,
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
    selectPlan,
    activateTrial,
    initiatePayment,
    confirmReturn,
    cancelAutoRenew,
    renew,
  }
}
