<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import QRCode from 'qrcode'
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
  paymentAttempt,
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
const paymentQrImage = ref('')
const generatingQr = ref(false)

watch(paymentAttempt, async (attempt) => {
  paymentQrImage.value = ''
  if (!attempt?.qr_code) return
  if (/^(data:image\/|https?:\/\/)/i.test(attempt.qr_code)) {
    paymentQrImage.value = attempt.qr_code
    return
  }
  generatingQr.value = true
  try {
    paymentQrImage.value = await QRCode.toDataURL(attempt.qr_code, { width: 240, margin: 2 })
  } finally {
    generatingQr.value = false
  }
}, { immediate: true })

function callbackUrls() {
  const returned = new URL(window.location.href)
  returned.searchParams.delete('payment_return')
  returned.searchParams.delete('payment_cancelled')
  const cancelled = new URL(returned)
  returned.searchParams.set('payment_return', '1')
  cancelled.searchParams.set('payment_cancelled', '1')
  return { returnUrl: returned.toString(), cancelUrl: cancelled.toString() }
}

function paymentStatusLabel(status: string): string {
  return ({ pending: 'Đang chờ xác minh', succeeded: 'Đã được máy chủ xác nhận', failed: 'Thanh toán thất bại', expired: 'Yêu cầu đã hết hạn' } as Record<string, string>)[status] || status
}

onMounted(async () => {
  const user = auth.user as any
  const activeOrganizationId = user?.capabilities?.active_organization_id ?? user?.organizations?.[0]?.organization_id
  const orgId = Number(route.params.organizationId || activeOrganizationId)
  if (!Number.isInteger(orgId) || orgId <= 0) {
    error.value = 'Không tìm thấy tổ chức đang hoạt động. Hãy chọn tổ chức trước khi quản lý thuê bao.'
    return
  }
  organizationId.value = orgId

  await fetchPlans()
  await fetchCurrent(orgId)

  // A provider return only refreshes server state; query parameters never confirm payment.
  if (route.query.payment_return || route.query.payment_cancelled) {
    await confirmReturn(orgId)
  }
})

async function handleSelectTrial(plan: PlatformPlan) {
  if (!organizationId.value) return
  try {
    await activateTrial(organizationId.value, plan)
  } catch {
    // Handled in composable
  }
}

async function handleSelectPaid(plan: PlatformPlan) {
  if (!organizationId.value) return
  try {
    const { returnUrl, cancelUrl } = callbackUrls()
    await initiatePayment(organizationId.value, plan, returnUrl, cancelUrl)
  } catch {
    // Handled in composable
  }
}

async function handleRenew() {
  if (!organizationId.value) return
  const { returnUrl, cancelUrl } = callbackUrls()
  await renew(organizationId.value, returnUrl, cancelUrl)
}

async function handleCancelRenew() {
  if (!organizationId.value) return
  await cancelAutoRenew(organizationId.value)
}

async function refreshPaymentStatus() {
  if (organizationId.value) await confirmReturn(organizationId.value)
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

    <section v-if="paymentAttempt" class="rounded-xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950/20" aria-live="polite">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 class="font-semibold text-gray-900 dark:text-white">Thanh toán gói dịch vụ</h2>
          <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
            Mã giao dịch: <strong>{{ paymentAttempt.reference_code }}</strong>
          </p>
          <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
            {{ new Intl.NumberFormat('vi-VN', { style: 'currency', currency: paymentAttempt.currency }).format(paymentAttempt.amount) }}
          </p>
          <p class="mt-2 text-sm font-medium text-blue-800 dark:text-blue-300">
            {{ paymentStatusLabel(paymentAttempt.status) }}
          </p>
        </div>
        <button type="button" :disabled="loading" class="rounded-lg border border-blue-300 px-3 py-2 text-xs font-semibold text-blue-800 disabled:opacity-50 dark:border-blue-800 dark:text-blue-300" @click="refreshPaymentStatus">
          {{ loading ? 'Đang kiểm tra...' : 'Kiểm tra trạng thái' }}
        </button>
      </div>
      <div v-if="paymentAttempt.status === 'pending' && generatingQr" class="mt-4 text-xs text-gray-500">Đang tạo mã QR…</div>
      <div v-else-if="paymentAttempt.status === 'pending' && paymentQrImage" class="mt-4 flex flex-col items-start gap-3">
        <img :src="paymentQrImage" alt="Mã QR thanh toán thuê bao" class="h-56 w-56 rounded-lg bg-white p-2" />
        <p class="text-xs text-gray-600 dark:text-gray-300">Trạng thái chỉ đổi sau khi máy chủ xác minh giao dịch.</p>
      </div>
      <a v-if="paymentAttempt.status === 'pending' && paymentAttempt.checkout_url" :href="paymentAttempt.checkout_url" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex rounded-lg bg-blue-700 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-800">
        Mở trang thanh toán
      </a>
    </section>

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
          Mô hình thuê bao cố định (tháng / năm) minh bạch, 0% hoa hồng doanh thu, tách biệt hoàn toàn phí cổng thanh toán.
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
