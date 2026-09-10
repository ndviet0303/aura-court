<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { usePlatformSubscription } from '../composables/usePlatformSubscription'
import { useAuthStore } from '@/features/auth/stores/authStore'
import PlanCatalogCard from '../components/PlanCatalogCard.vue'
import SubscriptionStatusCard from '../components/SubscriptionStatusCard.vue'
import type { PlatformPlan } from '../types'

const route = useRoute()
const auth = useAuthStore()

const {
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
  fetchPlans,
  fetchCurrent,
  activateTrial,
  initiatePayment,
  confirmReturn,
  cancelAutoRenew,
  renew,
} = usePlatformSubscription()

const organizationId = ref<number | null>(null)

onMounted(async () => {
  const orgId = Number(route.params.organizationId || (auth.user as any)?.active_organization_id || 1)
  organizationId.value = orgId

  await fetchPlans()
  await fetchCurrent(orgId)

  // Handle PayOS return redirect
  const attemptId = route.query.payment_attempt_id
  if (attemptId) {
    await confirmReturn(orgId, Number(attemptId))
  }
})

async function handleSelectTrial(plan: PlatformPlan) {
  if (!organizationId.value) return
  try {
    await activateTrial(organizationId.value, plan.code)
  } catch {
    // Handled in composable
  }
}

async function handleSelectPaid(plan: PlatformPlan) {
  if (!organizationId.value) return
  try {
    const res = await initiatePayment(organizationId.value, plan.code)
    if (res?.checkoutUrl) {
      window.location.href = res.checkoutUrl
    }
  } catch {
    // Handled in composable
  }
}

async function handleRenew() {
  if (!organizationId.value) return
  await renew(organizationId.value)
}

async function handleCancelRenew() {
  if (!organizationId.value) return
  await cancelAutoRenew(organizationId.value)
}
</script>

<template>
  <div class="platform-subscription-view py-8 px-4 max-w-5xl mx-auto space-y-8">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        Gói Dịch vụ Nền tảng
      </h1>
      <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
        Quản lý gói thuê bao, hạn mức vận hành và trạng thái mở bán sân bãi của câu lạc bộ.
      </p>
    </div>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="p-4 text-xs font-medium text-emerald-800 bg-emerald-100 rounded-lg dark:bg-emerald-950/40 dark:text-emerald-300">
      {{ notice }}
    </div>
    <div v-if="error" class="p-4 text-xs font-medium text-red-800 bg-red-100 rounded-lg dark:bg-red-950/40 dark:text-red-300">
      {{ error }}
    </div>

    <!-- Loading State -->
    <div v-if="loading && !plans.length" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600"></div>
    </div>

    <!-- Active Subscription Section -->
    <div v-if="subscription" class="space-y-4">
      <SubscriptionStatusCard
        :subscription="subscription"
        :can-take-bookings="canTakeBookings"
        :quotas="quotas"
        @renew="handleRenew"
        @cancel-renew="handleCancelRenew"
      />
    </div>

    <!-- Plan Catalog Section -->
    <div class="space-y-4">
      <div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">
          {{ subscription ? 'Thay đổi hoặc Nâng cấp Gói' : 'Chọn Gói Dịch vụ để Kích hoạt' }}
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
          Giá dịch vụ minh bạch, bao gồm đầy đủ hạn mức vận hành và hỗ trợ kỹ thuật.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <PlanCatalogCard
          v-for="plan in plans"
          :key="plan.id"
          :plan="plan"
          :trial-eligible="trialEligible"
          :is-current-plan="subscription?.plan_id === plan.id"
          :activating="activating"
          :initiating="initiating"
          @select-trial="handleSelectTrial"
          @select-paid="handleSelectPaid"
        />
      </div>
    </div>
  </div>
</template>
