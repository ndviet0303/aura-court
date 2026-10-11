<script setup lang="ts">
import type { PlatformSubscription } from '../types'

defineProps<{
  subscription: PlatformSubscription
  canTakeBookings: boolean
  quotas?: {
    venues_count: number
    max_venues: number
    courts_count: number
    max_courts: number
    staff_count: number
    max_staff: number
  } | null
}>()

const emit = defineEmits<{
  (e: 'renew'): void
  (e: 'cancelRenew'): void
}>()

function formatDate(dateStr?: string | null): string {
  if (!dateStr) return 'N/A'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('vi-VN', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    })
  } catch {
    return dateStr
  }
}
</script>

<template>
  <div
    class="subscription-status-card bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 space-y-6"
  >
    <!-- Header -->
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b pb-4 dark:border-gray-700"
    >
      <div>
        <span
          class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400"
        >
          Gói Đăng ký Hoạt động
        </span>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">
          {{ subscription.plan_name || `Gói #${subscription.plan_id}` }}
        </h2>
      </div>

      <div class="flex items-center gap-2">
        <span
          class="px-3 py-1 text-xs font-semibold rounded-full"
          :class="{
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300':
              subscription.is_valid && canTakeBookings,
            'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300':
              !subscription.is_valid || !canTakeBookings,
            'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300':
              subscription.status === 'cancelled',
          }"
        >
          {{
            subscription.is_trial && canTakeBookings
              ? 'Dùng thử đang kích hoạt'
              : subscription.is_valid && canTakeBookings
                ? 'Đang kích hoạt'
                : 'Hết hạn / Ngừng nhận đặt'
          }}
        </span>

        <span
          v-if="subscription.trial_ends_at"
          class="px-2.5 py-1 text-[11px] font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300"
        >
          Dùng thử đến {{ formatDate(subscription.trial_ends_at) }}
        </span>
      </div>
    </div>

    <!-- Cycle Details -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
      <div
        class="p-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-100 dark:border-gray-700"
      >
        <span class="text-gray-500 dark:text-gray-400 block">Ngày bắt đầu</span>
        <strong class="text-gray-900 dark:text-white text-sm mt-0.5 block">
          {{ formatDate(subscription.starts_at) }}
        </strong>
      </div>

      <div
        class="p-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-100 dark:border-gray-700"
      >
        <span class="text-gray-500 dark:text-gray-400 block">Hết hạn kỳ hiện tại</span>
        <strong class="text-gray-900 dark:text-white text-sm mt-0.5 block">
          {{ formatDate(subscription.ends_at) }}
        </strong>
      </div>

      <div
        class="p-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-100 dark:border-gray-700"
      >
        <span class="text-gray-500 dark:text-gray-400 block">Tự động gia hạn</span>
        <strong class="text-gray-900 dark:text-white text-sm mt-0.5 block">
          {{ subscription.auto_renew ? 'Bật (Tự động)' : 'Tắt' }}
        </strong>
      </div>
    </div>

    <!-- Quota Usage Stats -->
    <div v-if="quotas" class="space-y-3 pt-2">
      <h3 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
        Mức sử dụng tài nguyên
      </h3>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Venues -->
        <div
          class="p-3 rounded-lg border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800"
        >
          <div class="flex items-center justify-between text-xs mb-1.5">
            <span class="text-gray-500 dark:text-gray-400">Cơ sở thể thao</span>
            <strong class="text-gray-900 dark:text-white"
              >{{ quotas.venues_count }} / {{ quotas.max_venues }}</strong
            >
          </div>
          <div class="w-full bg-gray-200 dark:bg-gray-700 h-1.5 rounded-full overflow-hidden">
            <div
              class="bg-emerald-600 h-1.5 rounded-full"
              :style="{
                width: `${Math.min(100, (quotas.venues_count / quotas.max_venues) * 100)}%`,
              }"
            ></div>
          </div>
        </div>

        <!-- Courts -->
        <div
          class="p-3 rounded-lg border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800"
        >
          <div class="flex items-center justify-between text-xs mb-1.5">
            <span class="text-gray-500 dark:text-gray-400">Sân hoạt động</span>
            <strong class="text-gray-900 dark:text-white"
              >{{ quotas.courts_count }} / {{ quotas.max_courts }}</strong
            >
          </div>
          <div class="w-full bg-gray-200 dark:bg-gray-700 h-1.5 rounded-full overflow-hidden">
            <div
              class="bg-emerald-600 h-1.5 rounded-full"
              :style="{
                width: `${Math.min(100, (quotas.courts_count / quotas.max_courts) * 100)}%`,
              }"
            ></div>
          </div>
        </div>

        <!-- Staff -->
        <div
          class="p-3 rounded-lg border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800"
        >
          <div class="flex items-center justify-between text-xs mb-1.5">
            <span class="text-gray-500 dark:text-gray-400">Nhân viên / QTV</span>
            <strong class="text-gray-900 dark:text-white"
              >{{ quotas.staff_count }} / {{ quotas.max_staff }}</strong
            >
          </div>
          <div class="w-full bg-gray-200 dark:bg-gray-700 h-1.5 rounded-full overflow-hidden">
            <div
              class="bg-emerald-600 h-1.5 rounded-full"
              :style="{ width: `${Math.min(100, (quotas.staff_count / quotas.max_staff) * 100)}%` }"
            ></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between border-t pt-4 dark:border-gray-700 text-xs">
      <button
        v-if="subscription.auto_renew"
        type="button"
        class="text-gray-500 hover:text-red-600 underline"
        @click="emit('cancelRenew')"
      >
        Hủy tự động gia hạn
      </button>
      <div v-else class="text-gray-400 italic">
        Gói sẽ hết hạn vào ngày {{ formatDate(subscription.ends_at) }}
      </div>

      <button
        type="button"
        class="px-4 py-2 font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg"
        @click="emit('renew')"
      >
        Gia hạn kỳ mới ngay
      </button>
    </div>
  </div>
</template>
