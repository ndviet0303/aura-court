<script setup lang="ts">
import type { PlatformPlan } from '../types'

defineProps<{
  plan: PlatformPlan
  trialEligible: boolean
  isCurrentPlan?: boolean
  activating?: boolean
  initiating?: boolean
}>()

const emit = defineEmits<{
  (e: 'selectTrial', plan: PlatformPlan): void
  (e: 'selectPaid', plan: PlatformPlan): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
}

function cycleLabel(cycle: string): string {
  return (
    (
      {
        monthly: 'tháng',
        yearly: 'năm',
        six_months: '6 tháng',
        twelve_months: '12 tháng',
      } as Record<string, string>
    )[cycle] || cycle
  )
}
</script>

<template>
  <div
    class="plan-catalog-card p-6 rounded-xl border bg-white dark:bg-gray-800 flex flex-col justify-between transition-shadow hover:shadow-md"
    :class="
      isCurrentPlan
        ? 'border-emerald-600 ring-2 ring-emerald-500/20'
        : 'border-gray-200 dark:border-gray-700'
    "
  >
    <div class="space-y-4">
      <!-- Header & Badges -->
      <div class="flex items-center justify-between">
        <span
          class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400"
        >
          {{ plan.code }}
        </span>
        <span
          v-if="isCurrentPlan"
          class="px-2.5 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
        >
          Đang sử dụng
        </span>
      </div>

      <!-- Plan Name & Pricing -->
      <div>
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
          {{ plan.name }}
        </h3>
        <div class="mt-4 flex items-baseline gap-1">
          <span class="text-2xl font-black text-gray-900 dark:text-white">
            {{ formatMoney(plan.price) }}
          </span>
          <span class="text-xs text-gray-500 dark:text-gray-400">
            / {{ cycleLabel(plan.billing_cycle) }}
          </span>
        </div>
      </div>

      <!-- Quota Allocations -->
      <div class="pt-4 border-t border-gray-100 dark:border-gray-700 space-y-2">
        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
          Hạn mức dịch vụ nền tảng:
        </span>
        <ul class="text-xs space-y-1.5 text-gray-600 dark:text-gray-300">
          <li class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm text-emerald-600">domain</span>
            <span
              >Tối đa <strong>{{ plan.quotas.max_venues }}</strong> cơ sở thể thao</span
            >
          </li>
          <li class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm text-emerald-600">sports_tennis</span>
            <span
              >Tối đa <strong>{{ plan.quotas.max_courts }}</strong> sân hoạt động</span
            >
          </li>
          <li class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm text-emerald-600">badge</span>
            <span
              >Tối đa <strong>{{ plan.quotas.max_staff }}</strong> nhân viên / quản trị viên</span
            >
          </li>
        </ul>
      </div>

      <!-- Terms and Transparency Disclosure -->
      <div
        class="pt-3 border-t border-gray-100 dark:border-gray-700 text-[11px] text-gray-500 dark:text-gray-400 space-y-1"
      >
        <p>
          Phiên bản điều khoản: <strong>{{ plan.terms.version }}</strong>
        </p>
        <p>{{ plan.terms.text }}</p>
        <p>
          Thuê bao dịch vụ cố định — <strong>0% hoa hồng nền tảng</strong> trên mọi lượt đặt sân và
          giải đấu.
        </p>
        <p>
          Phí xử lý giao dịch cổng thanh toán (nếu có) được tách biệt riêng theo thực tế đối tác.
        </p>
        <p>Tự động gia hạn mỗi kỳ; có thể hủy gia hạn bất kỳ lúc nào.</p>
      </div>
    </div>

    <!-- Actions -->
    <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700 flex flex-col gap-2">
      <button
        v-if="trialEligible && plan.trial_enabled && plan.trial_days > 0"
        type="button"
        :disabled="activating || initiating"
        class="w-full py-2 px-4 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-300 dark:border-emerald-800 disabled:opacity-50"
        @click="emit('selectTrial', plan)"
      >
        Dùng thử {{ plan.trial_days }} ngày miễn phí
      </button>

      <button
        type="button"
        :disabled="activating || initiating || isCurrentPlan"
        class="w-full py-2.5 px-4 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg disabled:opacity-50"
        @click="emit('selectPaid', plan)"
      >
        {{ isCurrentPlan ? 'Gói hiện tại' : 'Đăng ký ngay' }}
      </button>
    </div>
  </div>
</template>
